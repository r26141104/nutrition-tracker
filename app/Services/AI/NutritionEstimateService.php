<?php

namespace App\Services\AI;

use App\Models\Food;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 用 Google Gemini API 估算食物營養成分。
 *
 * 流程：丟食物名稱 → Gemini 回 JSON → 解析後驗證 → 回給 caller。
 * 不寫 DB（caller 自己決定要不要存）。
 *
 * 注意：所有估算值都有 ±15-25% 誤差，前端必須清楚標示「AI 估算 / 可信度低」。
 */
class NutritionEstimateService
{
    /** Gemini API endpoint base */
    private const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * 模型 fallback 鏈：依序嘗試。
     * 2.5 Flash 偶爾會 503 (UNAVAILABLE)，自動降級到較穩定的版本。
     */
    private const MODEL_FALLBACKS = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
    ];

    /** 每個模型最多重試次數（指數退避） */
    private const MAX_RETRIES_PER_MODEL = 2;

    /** 合法的食物分類 */
    private const ALLOWED_CATEGORIES = [
        'rice_box', 'noodle', 'convenience', 'fast_food', 'drink', 'snack', 'other',
    ];

    /**
     * 主入口：估算指定食物的營養成分。
     *
     * @return array{
     *   name: string,
     *   calories: int,
     *   protein_g: float,
     *   fat_g: float,
     *   carbs_g: float,
     *   serving_unit: string,
     *   serving_size: float,
     *   category: string,
     *   notes: string
     * }
     */
    public function estimate(string $foodName): array
    {
        $foodName = trim($foodName);
        if ($foodName === '') {
            throw new RuntimeException('食物名稱不能為空。');
        }

        // 步驟 1：先查衛福部官方資料（誤差 0%，遠勝 AI 估算 ±20%）
        $official = $this->findOfficialMatch($foodName);
        if ($official !== null) {
            return $official;
        }

        // 步驟 2：找不到才呼叫 Gemini AI
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException(
                '尚未設定 Gemini API key。請到 https://aistudio.google.com/app/apikey 申請後加進 .env 的 GEMINI_API_KEY。',
            );
        }

        $prompt = $this->buildPrompt($foodName);

        // 依序嘗試各個模型，遇到 503/429/500/timeout 就降級
        $lastError = '';
        foreach (self::MODEL_FALLBACKS as $model) {
            try {
                $rawText = $this->callModelWithRetry($model, $apiKey, $prompt);
                $parsed  = json_decode($rawText, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
                    // JSON 解析失敗 → 換下一個模型試
                    $lastError = '回傳格式不正確';
                    continue;
                }
                return $this->normalizeAndValidate($foodName, $parsed);
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
                // 503 / 429 / 500 / timeout → 換下一個模型
                continue;
            }
        }

        throw new RuntimeException(
            'AI 服務目前忙碌中，請稍後再試（已嘗試多個備用模型仍失敗：' . $lastError . '）',
        );
    }

    /**
     * 呼叫單一模型，遇到暫時性錯誤會自動重試（指數退避）。
     * 永久錯誤（401, 400 等）直接拋。
     */
    private function callModelWithRetry(string $model, string $apiKey, string $prompt): string
    {
        $endpoint = self::ENDPOINT_BASE . '/' . $model . ':generateContent?key=' . urlencode($apiKey);

        $lastStatus = 0;
        $lastBody = '';

        for ($attempt = 0; $attempt < self::MAX_RETRIES_PER_MODEL; $attempt++) {
            if ($attempt > 0) {
                // 指數退避：1s、2s
                usleep((int) (pow(2, $attempt - 1) * 1_000_000));
            }

            try {
                $response = Http::acceptJson()
                    ->timeout(30)
                    ->post($endpoint, [
                        'contents' => [[
                            'parts' => [['text' => $prompt]],
                        ]],
                        'generationConfig' => [
                            'temperature'      => 0.2,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);
            } catch (\Throwable $e) {
                // 連線中斷 / timeout → 重試
                $lastStatus = 0;
                $lastBody = $e->getMessage();
                continue;
            }

            $status = $response->status();
            $lastStatus = $status;
            $lastBody = $response->body();

            // 成功
            if ($response->successful()) {
                $body = $response->json();
                $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (! is_string($rawText) || $rawText === '') {
                    throw new RuntimeException("模型 {$model} 沒有回傳內容");
                }
                return $rawText;
            }

            // 永久性錯誤（API key 錯、prompt 違規）→ 不重試、直接拋
            if (in_array($status, [400, 401, 403, 404], true)) {
                throw new RuntimeException("模型 {$model} 回 {$status}：" . $lastBody);
            }

            // 其他（429, 500, 503...）→ 進下一輪重試
        }

        throw new RuntimeException("模型 {$model} 重試 " . self::MAX_RETRIES_PER_MODEL . " 次仍失敗（status={$lastStatus}）");
    }

    // ========================================================================
    // 內部
    // ========================================================================

    private function buildPrompt(string $foodName): string
    {
        $allowedCats = implode(' / ', self::ALLOWED_CATEGORIES);

        return <<<PROMPT
你是一個食物營養估算助手。請根據食物名稱，估算「每 1 份」的營養成分。

食物名稱：{$foodName}

請完全用以下 JSON 格式回應，不要加任何其他文字：
{
  "calories": 整數（kcal）,
  "protein_g": 浮點數（蛋白質克數）,
  "fat_g": 浮點數（脂肪克數）,
  "carbs_g": 浮點數（碳水克數）,
  "serving_unit": 字串（單位：份/個/碗/杯/g/ml/瓶...）,
  "serving_size": 浮點數（份量數值，預設 1）,
  "category": 字串（必須是這些之一：{$allowedCats}）,
  "notes": 字串（1 句話說明估算依據，例如「常見台灣便當約 800-900 kcal」）
}

說明：
- 所有數值都是估算，誤差 ±20% 屬正常
- 如果食物名稱模糊，估算最常見的版本
- 數值務必合理（calories ≥ 0，protein_g+fat_g+carbs_g 對應的熱量應接近 calories）
- 飲料用「杯」或「瓶」、便當用「份」、麵食用「碗」
PROMPT;
    }

    /**
     * 校正並驗證 Gemini 回的資料。對應到 foods 表 schema。
     */
    private function normalizeAndValidate(string $foodName, array $data): array
    {
        $calories  = (int) max(0, $data['calories']  ?? 0);
        $proteinG  = (float) max(0, $data['protein_g']  ?? 0);
        $fatG      = (float) max(0, $data['fat_g']      ?? 0);
        $carbsG    = (float) max(0, $data['carbs_g']    ?? 0);

        $servingSize = (float) ($data['serving_size'] ?? 1.0);
        if ($servingSize <= 0) $servingSize = 1.0;

        $servingUnit = trim((string) ($data['serving_unit'] ?? '份'));
        if ($servingUnit === '') $servingUnit = '份';
        // 限制長度避免過長
        if (mb_strlen($servingUnit) > 20) {
            $servingUnit = mb_substr($servingUnit, 0, 20);
        }

        $category = (string) ($data['category'] ?? 'other');
        if (! in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $category = 'other';
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) > 200) {
            $notes = mb_substr($notes, 0, 200);
        }

        return [
            'name'         => $foodName,
            'calories'     => $calories,
            'protein_g'    => round($proteinG, 1),
            'fat_g'        => round($fatG, 1),
            'carbs_g'      => round($carbsG, 1),
            'serving_unit' => $servingUnit,
            'serving_size' => $servingSize,
            'category'     => $category,
            'notes'        => $notes,
        ];
    }

    /**
     * 嘗試在 foods 表中找衛福部官方資料的模糊比對。
     * 優先：完全相同 > name 包含 keyword > keyword 包含 name。
     *
     * @return array|null 找到回傳與 estimate() 相同格式；找不到 null
     */
    private function findOfficialMatch(string $foodName): ?array
    {
        // 全相同
        $exact = Food::where('source_type', 'official')
            ->where('name', $foodName)
            ->first();
        if ($exact) {
            return $this->foodToEstimateArray($exact, '完全相符');
        }

        // 模糊比對：name 包含使用者輸入
        $like = Food::where('source_type', 'official')
            ->where('name', 'like', "%{$foodName}%")
            ->orderByRaw('LENGTH(name) ASC')
            ->first();
        if ($like) {
            return $this->foodToEstimateArray($like, '部分符合「' . $like->name . '」');
        }

        // 反向：使用者輸入比較長，含 official name 當關鍵字
        if (mb_strlen($foodName) >= 2) {
            $shortKey = mb_substr($foodName, 0, max(2, (int) (mb_strlen($foodName) / 2)));
            $reverse = Food::where('source_type', 'official')
                ->where('name', 'like', "%{$shortKey}%")
                ->orderByRaw('LENGTH(name) ASC')
                ->first();
            if ($reverse) {
                return $this->foodToEstimateArray($reverse, '近似比對「' . $reverse->name . '」');
            }
        }

        return null;
    }

    /** 把 Food model 轉成 estimate() 的回傳格式 */
    private function foodToEstimateArray(Food $food, string $matchNote): array
    {
        return [
            'name'         => $food->name,
            'calories'     => (int) $food->calories,
            'protein_g'    => round((float) $food->protein_g, 1),
            'fat_g'        => round((float) $food->fat_g, 1),
            'carbs_g'      => round((float) $food->carbs_g, 1),
            'serving_unit' => $food->serving_unit,
            'serving_size' => (float) $food->serving_size,
            'category'     => $food->category,
            'notes'        => '✓ 衛福部官方資料（' . $matchNote . '，誤差近 0%）',
        ];
    }
}
