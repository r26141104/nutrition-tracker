<?php

namespace App\Console\Commands;

use App\Models\Food;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 從衛福部食品藥物管理署「食品營養成分資料庫」(TFND) 匯入官方營養資料。
 *
 * 資料來源：data.fda.gov.tw 公開資料 InfoId=20
 * https://data.fda.gov.tw/opendata/exportDataList.do?method=openData&InfoId=20
 *
 * 用法：
 *   php artisan import:tfnd                # 從 API 抓最新版
 *   php artisan import:tfnd --file=path    # 從本地 JSON 檔讀取（離線備援）
 *   php artisan import:tfnd --truncate     # 先清掉所有 official 食物再匯
 *
 * 重複執行安全：用 (name, source_type=official) 去重，已存在會更新數值
 */
class ImportTfndCommand extends Command
{
    protected $signature = 'import:tfnd
                            {--file= : 從本地 JSON 檔讀取（API 抓不到時用）}
                            {--truncate : 先清掉舊的 official 資料}
                            {--dry-run : 只看會匯入幾筆，不實際寫 DB}';

    protected $description = '從衛福部 TFND 匯入官方食品營養資料（約 2200 筆）';

    /** 試多個端點，第一個能回 JSON 的就用 */
    private const API_URLS = [
        // data.gov.tw 平台（最穩定，直接回 JSON）
        'https://data.fda.gov.tw/opendata/exportDataList.do?method=openData&InfoId=20',
        // 備援：data.gov.tw 下載連結（可能是 XML，但會在主流程被偵測）
        'https://data.fda.gov.tw/cacheData/20.json',
        // 第二備援
        'https://data.fda.gov.tw/odata/exportDataList.do?method=openData&InfoId=20',
    ];

    /** TFND 食品大類 → 我們的 category */
    private const CATEGORY_MAP = [
        '穀物類'        => 'rice_box',
        '澱粉類'        => 'rice_box',
        '堅果及種子類'  => 'snack',
        '水果類'        => 'snack',
        '蔬菜類'        => 'other',
        '藻類'          => 'other',
        '菇類'          => 'other',
        '豆類'          => 'other',
        '肉類'          => 'other',
        '魚貝類'        => 'other',
        '蛋類'          => 'other',
        '乳品類'        => 'drink',
        '油脂類'        => 'other',
        '糖類'          => 'snack',
        '嗜好性飲料類'  => 'drink',
        '調理加工食品類' => 'fast_food',
        '加工調理食品類' => 'fast_food',
    ];

    public function handle(): int
    {
        $this->info('=== TFND 官方食品營養資料匯入 ===');

        // 1. 取得資料（API or 本地檔）
        $rawJson = $this->fetchData();
        if ($rawJson === null) {
            $this->error('無法取得資料，請檢查網路或用 --file 指定本地檔');
            return self::FAILURE;
        }

        // 印前 300 字幫忙 debug（API 偶爾會回 HTML 錯誤頁、XML、或 BOM）
        $preview = mb_substr(trim($rawJson), 0, 300);
        $this->line('[debug] 回傳前 300 字：' . $preview);

        // 去掉常見的 BOM 和前後空白
        $rawJson = preg_replace('/^\xEF\xBB\xBF/', '', trim($rawJson));

        $items = json_decode($rawJson, true);
        if (! is_array($items)) {
            // 試試看是不是 XML（FDA 預設可能是 XML）
            if (str_starts_with($rawJson, '<?xml') || str_contains($rawJson, '<DataList')) {
                $this->error('API 回的是 XML，需要改用其他端點。');
                $this->line('請改用備援方案：');
                $this->line('  1. 到 https://data.gov.tw/dataset/8543');
                $this->line('  2. 找到「JSON」格式的下載連結');
                $this->line('  3. 下載後存成 storage/app/tfnd.json');
                $this->line('  4. 重跑 php artisan import:tfnd --file=storage/app/tfnd.json');
                return self::FAILURE;
            }

            // 試試看是 HTML 錯誤頁
            if (str_starts_with($rawJson, '<')) {
                $this->error('API 回的是 HTML（可能是錯誤頁面或維護中）');
                return self::FAILURE;
            }

            $this->error('回傳的不是合法 JSON：' . json_last_error_msg());
            return self::FAILURE;
        }

        $this->info('已下載 ' . count($items) . ' 筆原始資料');

        // 2. 整理為 food → nutrient 的結構
        // TFND 的格式是 long form：每筆 = 一個食物的「一個營養素」
        // 同一食物會用「整合編號」串起來
        $foods = $this->groupByFood($items);
        $this->info('合併後共 ' . count($foods) . ' 筆食物');

        if ($this->option('dry-run')) {
            $sample = array_slice($foods, 0, 3, true);
            $this->info('--- 前 3 筆預覽 ---');
            foreach ($sample as $row) {
                $this->line(sprintf(
                    '%s | %s | %d kcal | P %.1f / F %.1f / C %.1f',
                    $row['category_raw'] ?? '?',
                    $row['name'],
                    $row['calories'],
                    $row['protein_g'],
                    $row['fat_g'],
                    $row['carbs_g'],
                ));
            }
            $this->info('（dry-run 結束，未寫入 DB）');
            return self::SUCCESS;
        }

        // 3. 視情況清空舊的 official
        if ($this->option('truncate')) {
            $deleted = Food::where('source_type', 'official')->delete();
            $this->warn("已刪除 {$deleted} 筆舊的 official 資料");
        }

        // 4. 寫入
        $bar = $this->output->createProgressBar(count($foods));
        $bar->start();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($foods as $row) {
            $bar->advance();

            // 0 卡的（如水）也要進，但跳過完全沒蛋白脂肪碳水的（資料不全）
            if ($row['calories'] === 0 && $row['protein_g'] === 0
                && $row['fat_g'] === 0 && $row['carbs_g'] === 0) {
                $skipped++;
                continue;
            }
            if ($row['name'] === '' || mb_strlen($row['name']) > 100) {
                $skipped++;
                continue;
            }

            $existing = Food::where('name', $row['name'])
                ->where('source_type', 'official')
                ->first();

            $payload = [
                'name'             => $row['name'],
                'brand'            => '衛福部',
                'category'         => $this->mapCategory($row['category_raw']),
                'serving_unit'     => 'g',
                'serving_size'     => 100,        // TFND 都是每 100g
                'calories'         => $row['calories'],
                'protein_g'        => $row['protein_g'],
                'fat_g'            => $row['fat_g'],
                'carbs_g'          => $row['carbs_g'],
                'is_system'        => true,
                'created_by_user_id' => null,
                'source_type'      => 'official',
                'confidence_level' => 'high',
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Food::create($payload);
                $created++;
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ 新增 {$created} 筆，更新 {$updated} 筆，跳過 {$skipped} 筆（資料不完整）");
        $this->info('完成！打開食物資料庫應該就能看到「✓ 衛福部官方」標籤');

        return self::SUCCESS;
    }

    private function fetchData(): ?string
    {
        $file = (string) $this->option('file');
        if ($file !== '') {
            if (! file_exists($file)) {
                $this->error("找不到檔案：{$file}");
                return null;
            }
            $this->info("從本地檔讀取：{$file}");
            return file_get_contents($file) ?: null;
        }

        // 試多個 API 端點
        foreach (self::API_URLS as $url) {
            $this->info("嘗試：{$url}");
            try {
                $response = Http::timeout(120)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($url);
                if (! $response->successful()) {
                    $this->warn('  → HTTP ' . $response->status() . '，試下一個端點');
                    continue;
                }
                $body = $response->body();
                if (str_starts_with(trim($body), '[') || str_starts_with(trim($body), '{')) {
                    $this->info('  → ✓ 取到 JSON');
                    return $body;
                }
                $this->warn('  → 不是 JSON 格式（前 50 字：' . mb_substr(trim($body), 0, 50) . '...），試下一個');
            } catch (\Throwable $e) {
                $this->warn('  → 連線失敗：' . $e->getMessage());
                continue;
            }
        }

        $this->error('所有 API 端點都失敗。');
        $this->line('');
        $this->line('備援方案：');
        $this->line('  1. 到 https://data.gov.tw/dataset/8543');
        $this->line('  2. 找 JSON 格式下載連結');
        $this->line('  3. 存到 storage/app/tfnd.json');
        $this->line('  4. 跑 php artisan import:tfnd --file=storage/app/tfnd.json');
        return null;
    }

    /**
     * TFND API 是 long form：每筆代表「某食物的某營養素」。
     * 用「整合編號」當 key 把同一食物的所有營養素聚合起來。
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function groupByFood(array $items): array
    {
        $grouped = [];
        foreach ($items as $row) {
            if (! is_array($row)) continue;

            $id   = (string) ($row['整合編號']   ?? '');
            $name = trim((string) ($row['樣品名稱'] ?? ''));
            if ($id === '' || $name === '') continue;

            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'name'         => $name,
                    'category_raw' => trim((string) ($row['食品分類'] ?? '')),
                    'calories'     => 0,
                    'protein_g'    => 0.0,
                    'fat_g'        => 0.0,
                    'carbs_g'      => 0.0,
                ];
            }

            $analysisItem = trim((string) ($row['分析項'] ?? ''));
            // 「每100公克含量」是 string，可能是「-」表示沒測
            $rawValue = $row['每100公克含量'] ?? $row['每100克含量'] ?? null;
            $value = is_numeric($rawValue) ? (float) $rawValue : 0.0;

            // 對應營養素（TFND 用「修正熱量」較準，沒有再用「熱量」）
            switch ($analysisItem) {
                case '修正熱量':
                case '熱量':
                    if ($grouped[$id]['calories'] === 0) {
                        $grouped[$id]['calories'] = (int) round($value);
                    } elseif ($analysisItem === '修正熱量') {
                        $grouped[$id]['calories'] = (int) round($value);
                    }
                    break;
                case '粗蛋白':
                case '蛋白質':
                    $grouped[$id]['protein_g'] = round($value, 1);
                    break;
                case '粗脂肪':
                case '脂肪':
                case '總脂肪':
                    $grouped[$id]['fat_g'] = round($value, 1);
                    break;
                case '總碳水化合物':
                case '碳水化合物':
                    $grouped[$id]['carbs_g'] = round($value, 1);
                    break;
            }
        }
        return $grouped;
    }

    private function mapCategory(string $rawCategory): string
    {
        foreach (self::CATEGORY_MAP as $keyword => $cat) {
            if (str_contains($rawCategory, $keyword)) {
                return $cat;
            }
        }
        return 'other';
    }
}
