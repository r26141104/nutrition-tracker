<?php

namespace App\Services\Food;

use App\Models\Food;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 食物資料匯入 service。
 *
 * 設計原則（嚴格遵守）：
 *   - is_system 寫死 false（嚴禁從匯入檔建立系統食物）
 *   - created_by_user_id 寫死目前登入者 id（嚴禁假冒他人）
 *   - 重複的 name + brand → 整列失敗，不覆蓋舊資料
 *   - 缺漏的營養素不自行猜測（PFC 為 null 時以 0 寫入，並在前端顯示警示）
 *   - 檔案內重複的 name + brand → 第 2 筆以後標 invalid（保留第 1 筆）
 */
class FoodImportService
{
    /** 合法分類 */
    public const ALLOWED_CATEGORIES = [
        'rice_box', 'noodle', 'convenience',
        'fast_food', 'drink', 'snack', 'other',
    ];

    /** 必填欄位 */
    private const REQUIRED_FIELDS = [
        'name', 'category', 'serving_unit', 'serving_size', 'calories',
    ];

    // ========================================================================
    // 1) parseCsv — 解析 CSV 字串為 row 陣列
    // ========================================================================

    /**
     * @return array<int, array<string, mixed>>
     * @throws ValidationException
     */
    public function parseCsv(string $content): array
    {
        // 剝除 UTF-8 BOM（Excel 存的 .csv 都會有這個前綴）
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (trim($content) === '') {
            throw ValidationException::withMessages([
                'file' => ['CSV 檔案是空的'],
            ]);
        }

        // 用 memory stream 處理 CSV（支援多行字串、引號）
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream);
        if ($header === false || count($header) === 0) {
            fclose($stream);
            throw ValidationException::withMessages([
                'file' => ['CSV 無法解析或缺少 header 列'],
            ]);
        }

        // header 去空白
        $header = array_map(static fn ($h) => is_string($h) ? trim($h) : $h, $header);

        // 確認必要欄位都在 header 內
        $missing = array_diff(self::REQUIRED_FIELDS, $header);
        if (! empty($missing)) {
            fclose($stream);
            throw ValidationException::withMessages([
                'file' => ['CSV 缺少必要欄位：' . implode(', ', $missing)],
            ]);
        }

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            // 跳過完全空的列
            $hasContent = false;
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (! $hasContent) continue;

            // 列長補齊
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), null);
            }
            $rows[] = array_combine($header, array_slice($row, 0, count($header)));
        }
        fclose($stream);

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => ['CSV 沒有任何資料列'],
            ]);
        }

        return $rows;
    }

    // ========================================================================
    // 2) parseJson — 解析 JSON 字串為 row 陣列
    // ========================================================================

    /**
     * @return array<int, array<string, mixed>>
     * @throws ValidationException
     */
    public function parseJson(string $content): array
    {
        // 剝除 BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'file' => ['JSON 格式錯誤：' . json_last_error_msg()],
            ]);
        }

        if (! is_array($data) || array_is_list($data) === false) {
            throw ValidationException::withMessages([
                'file' => ['JSON 必須是 array of objects（物件陣列）'],
            ]);
        }

        if (empty($data)) {
            throw ValidationException::withMessages([
                'file' => ['JSON 沒有任何資料'],
            ]);
        }

        // 每個元素必須是 object（PHP 解析後是 associative array）
        foreach ($data as $i => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'file' => ['JSON 第 ' . ($i + 1) . ' 筆不是物件，必須是 array of objects'],
                ]);
            }
        }

        return $data;
    }

    // ========================================================================
    // 3) normalizeRow — 整理單筆資料（trim、空字串轉 null、數字轉型）
    // ========================================================================

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalizeRow(array $row): array
    {
        // 嚴禁從匯入檔取這兩個欄位（防止假冒系統食物或他人資料）
        unset($row['is_system'], $row['created_by_user_id']);

        $normalized = [
            'name'         => null,
            'brand'        => null,
            'category'     => null,
            'serving_unit' => null,
            'serving_size' => null,
            'calories'     => null,
            'protein_g'    => null,
            'fat_g'        => null,
            'carbs_g'      => null,
        ];

        // 字串欄位：trim、空字串→null
        foreach (['name', 'brand', 'category', 'serving_unit'] as $field) {
            $value = $row[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') $value = null;
            } elseif ($value === '') {
                $value = null;
            }
            $normalized[$field] = $value;
        }

        // 數字欄位：trim、空字串→null、數字→cast
        foreach (['serving_size', 'calories', 'protein_g', 'fat_g', 'carbs_g'] as $field) {
            $value = $row[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                } elseif (is_numeric($value)) {
                    $value = $field === 'calories' ? (int) $value : (float) $value;
                }
                // 非數字字串 → 留原值給 validate 抓
            } elseif ($value === null || $value === '') {
                $value = null;
            } elseif (is_numeric($value)) {
                $value = $field === 'calories' ? (int) $value : (float) $value;
            }
            $normalized[$field] = $value;
        }

        return $normalized;
    }

    // ========================================================================
    // 4) validateRow — 驗證單筆資料，回 errors 陣列
    // ========================================================================

    /**
     * @param  array<string, mixed>  $row  已經 normalize 過
     * @return array<int, string>
     */
    public function validateRow(array $row): array
    {
        $errors = [];

        // name: required, max 100
        if ($row['name'] === null) {
            $errors[] = 'name 為必填';
        } elseif (! is_string($row['name'])) {
            $errors[] = 'name 必須是字串';
        } elseif (mb_strlen($row['name']) > 100) {
            $errors[] = 'name 最多 100 字';
        }

        // brand: optional, max 50
        if ($row['brand'] !== null) {
            if (! is_string($row['brand'])) {
                $errors[] = 'brand 必須是字串';
            } elseif (mb_strlen($row['brand']) > 50) {
                $errors[] = 'brand 最多 50 字';
            }
        }

        // category: required, in ALLOWED
        if ($row['category'] === null) {
            $errors[] = 'category 為必填';
        } elseif (! in_array($row['category'], self::ALLOWED_CATEGORIES, true)) {
            $errors[] = 'category 必須是 ' . implode(' / ', self::ALLOWED_CATEGORIES) . ' 其中之一';
        }

        // serving_unit: required, max 20
        if ($row['serving_unit'] === null) {
            $errors[] = 'serving_unit 為必填';
        } elseif (! is_string($row['serving_unit'])) {
            $errors[] = 'serving_unit 必須是字串';
        } elseif (mb_strlen($row['serving_unit']) > 20) {
            $errors[] = 'serving_unit 最多 20 字';
        }

        // serving_size: required, > 0, ≤ 99999.99
        if ($row['serving_size'] === null) {
            $errors[] = 'serving_size 為必填';
        } elseif (! is_numeric($row['serving_size'])) {
            $errors[] = 'serving_size 必須是數字';
        } else {
            $v = (float) $row['serving_size'];
            if ($v <= 0)         $errors[] = 'serving_size 必須大於 0';
            elseif ($v > 99999.99) $errors[] = 'serving_size 不能超過 99999.99';
        }

        // calories: required, ≥ 0, ≤ 99999
        if ($row['calories'] === null) {
            $errors[] = 'calories 為必填';
        } elseif (! is_numeric($row['calories'])) {
            $errors[] = 'calories 必須是數字';
        } else {
            $v = (int) $row['calories'];
            if ($v < 0)        $errors[] = 'calories 不能為負';
            elseif ($v > 99999) $errors[] = 'calories 不能超過 99999';
        }

        // protein_g / fat_g / carbs_g: optional, ≥ 0, ≤ 9999
        foreach (['protein_g', 'fat_g', 'carbs_g'] as $field) {
            if ($row[$field] === null) continue;
            if (! is_numeric($row[$field])) {
                $errors[] = "{$field} 必須是數字";
                continue;
            }
            $v = (float) $row[$field];
            if ($v < 0)        $errors[] = "{$field} 不能為負";
            elseif ($v > 9999) $errors[] = "{$field} 不能超過 9999";
        }

        return $errors;
    }

    // ========================================================================
    // 5) detectDuplicate — 檢查 DB 內是否有重複
    // ========================================================================

    public function detectDuplicate(User $user, string $name, ?string $brand): bool
    {
        $key = $this->normalizeKey($name, $brand);
        return in_array($key, $this->loadExistingKeys($user), true);
    }

    // ========================================================================
    // 6) previewImport — 預覽匯入（不寫入 DB）
    // ========================================================================

    /**
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return array<string, mixed>
     */
    public function previewImport(User $user, array $rawRows): array
    {
        $existingKeys = $this->loadExistingKeys($user);
        $batchKeys    = []; // 偵測同檔案內重複

        $valid   = [];
        $invalid = [];

        foreach ($rawRows as $i => $rawRow) {
            $rowNumber = $i + 1; // 1-indexed 給人看

            // 為了顯示用，先記下原始資料
            $originalForDisplay = is_array($rawRow) ? $rawRow : [];

            // normalize
            $normalized = $this->normalizeRow(is_array($rawRow) ? $rawRow : []);

            // validate
            $errors = $this->validateRow($normalized);

            // 重複檢查（只在基本欄位 OK 時做，避免疊加錯誤訊息）
            if (empty($errors) && is_string($normalized['name'])) {
                $key = $this->normalizeKey($normalized['name'], $normalized['brand']);

                if (in_array($key, $existingKeys, true)) {
                    $errors[] = '已存在同名 + 同品牌的自訂食物，無法匯入';
                } elseif (in_array($key, $batchKeys, true)) {
                    $errors[] = '檔案內已有重複的 name + brand，本筆不會匯入';
                } else {
                    $batchKeys[] = $key; // 記下避免後續同檔重複
                }
            }

            if (empty($errors)) {
                $valid[] = [
                    'row_number' => $rowNumber,
                    'data'       => $normalized,
                ];
            } else {
                $invalid[] = [
                    'row_number' => $rowNumber,
                    'data'       => $originalForDisplay,
                    'errors'     => $errors,
                ];
            }
        }

        return [
            'total_rows'    => count($rawRows),
            'valid_count'   => count($valid),
            'invalid_count' => count($invalid),
            'valid_rows'    => $valid,
            'invalid_rows'  => $invalid,
        ];
    }

    // ========================================================================
    // 7) importRows — 正式匯入（valid rows 寫入 DB）
    // ========================================================================

    /**
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return array<string, mixed>
     */
    public function importRows(User $user, array $rawRows): array
    {
        $preview = $this->previewImport($user, $rawRows);

        $importedFoods = [];
        $failed = array_map(static fn ($r) => [
            'row_number' => $r['row_number'],
            'errors'     => $r['errors'],
        ], $preview['invalid_rows']);

        // 沒有 valid rows → 不開 transaction、不建任何資料
        if ($preview['valid_count'] > 0) {
            DB::transaction(function () use ($user, $preview, &$importedFoods) {
                foreach ($preview['valid_rows'] as $validRow) {
                    $food = $this->createFoodFromRow($user, $validRow['data']);
                    $importedFoods[] = [
                        'id'       => $food->id,
                        'name'     => $food->name,
                        'brand'    => $food->brand,
                        'category' => $food->category,
                        'calories' => (int) $food->calories,
                    ];
                }
            });
        }

        return [
            'total_rows'     => $preview['total_rows'],
            'imported_count' => count($importedFoods),
            'failed_count'   => count($failed),
            'imported_foods' => $importedFoods,
            'failed_rows'    => $failed,
        ];
    }

    // ========================================================================
    // 8) createFoodFromRow — 建立 food，強制 is_system=false / owner=auth user
    // ========================================================================

    /**
     * @param  array<string, mixed>  $row  已經 normalize + validate 通過
     */
    public function createFoodFromRow(User $user, array $row): Food
    {
        return Food::create([
            'name'               => $row['name'],
            'brand'              => $row['brand'],
            'category'           => $row['category'],
            'serving_unit'       => $row['serving_unit'],
            'serving_size'       => $row['serving_size'],
            'calories'           => (int) $row['calories'],
            // 缺漏的 PFC 以 0 寫入（不自行猜測，沿用現有 schema NOT NULL 限制）
            'protein_g'          => $row['protein_g'] ?? 0,
            'fat_g'              => $row['fat_g']     ?? 0,
            'carbs_g'            => $row['carbs_g']   ?? 0,
            // ↓ 嚴格寫死，不從 row 取
            'is_system'          => false,
            'created_by_user_id' => $user->id,
            // 修正四：匯入資料 → imported + low（外部來源、未經官方驗證）
            'source_type'        => 'imported',
            'confidence_level'   => 'low',
        ]);
    }

    // ========================================================================
    // 內部工具
    // ========================================================================

    /**
     * 把 name + brand 標準化成單一 key（小寫 + trim），給重複偵測比對用。
     */
    private function normalizeKey(string $name, ?string $brand): string
    {
        $n = mb_strtolower(trim($name));
        $b = $brand === null ? '' : mb_strtolower(trim($brand));
        return $n . '|' . $b;
    }

    /**
     * 撈使用者既有的自訂食物 keys，給重複偵測用。
     *
     * @return array<int, string>
     */
    private function loadExistingKeys(User $user): array
    {
        $keys = [];
        Food::query()
            ->where('created_by_user_id', $user->id)
            ->where('is_system', false)
            ->select(['name', 'brand'])
            ->each(function ($food) use (&$keys) {
                $keys[] = $this->normalizeKey($food->name, $food->brand);
            });
        return $keys;
    }
}
