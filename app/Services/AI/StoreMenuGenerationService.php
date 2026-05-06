<?php

namespace App\Services\AI;

use App\Models\Food;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 用 Gemini AI 為任意「非連鎖店家」生成推測菜單。
 *
 * 流程：
 *   店家名稱（如「南夜滷味」）
 *     → AI 推斷類型（傳統滷味店）
 *     → AI 列出 15-20 個常見品項 + 每份營養估算
 *     → 建立 Store record（slug = guess-XXX，confidence = low）
 *     → 建立 Food records 連到該 Store
 *   結果：可以直接用既有的 /stores/{id} 頁面顯示菜單
 *
 * 重複呼叫安全：用 slug 去重，第二次同名直接回快取的 Store。
 */
class StoreMenuGenerationService
{
    private const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** 模型 fallback 鏈（與 NutritionEstimateService 一致） */
    private const MODEL_FALLBACKS = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
    ];

    private const ALLOWED_CATEGORIES = [
        'rice_box', 'noodle', 'convenience', 'fast_food', 'drink', 'snack', 'other',
    ];

    /**
     * 主入口：給定店名（可選提示），生成 Store（含菜單）
     *
     * @param  string       $storeName  店家名稱
     * @param  string|null  $hint       使用者提示，例：「賣健康餐」、「便當店」、「咖啡輕食」
     */
    public function generateForStoreName(string $storeName, ?string $hint = null): Store
    {
        $storeName = trim($storeName);
        if ($storeName === '') {
            throw new RuntimeException('店家名稱不能為空。');
        }
        if (mb_strlen($storeName) > 80) {
            throw new RuntimeException('店家名稱過長。');
        }

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '' && mb_strlen($hint) > 200) {
            $hint = mb_substr($hint, 0, 200);
        }

        // slug：用 md5 前 12 碼做唯一識別（含 hint 確保不同提示產出獨立菜單）
        $slug = 'guess-' . substr(md5($storeName . '|' . $hint), 0, 12);

        // 已存在且有菜單 → 直接回（避免重複燒 AI 配額）
        $existing = Store::where('slug', $slug)->first();
        if ($existing && $existing->menuItems()->count() > 0) {
            return $existing;
        }

        // 呼叫 AI
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('尚未設定 Gemini API key。');
        }

        // 兩階段嘗試：
        //   階段 1：用 google_search grounding（準確度高但可能超時）
        //   階段 2：失敗就 fallback 到純 prompt 版（快但只能用店名猜）
        // 確保使用者一定拿到結果
        $parsed = null;
        try {
            $rawText = $this->callGeminiWithFallback($apiKey, $this->buildPrompt($storeName, $hint), useSearch: true);
            $jsonText = $this->extractJson($rawText);
            $parsed = json_decode($jsonText, true);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('AI menu generation: search version failed, falling back', [
                'store' => $storeName,
                'error' => $e->getMessage(),
            ]);
        }

        if (! is_array($parsed) || empty($parsed['items'])) {
            // Fallback：不用 search、用結構化 JSON 模式
            $rawText = $this->callGeminiWithFallback($apiKey, $this->buildFallbackPrompt($storeName, $hint), useSearch: false);
            $jsonText = $this->extractJson($rawText);
            $parsed = json_decode($jsonText, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
                throw new RuntimeException(
                    'AI 回的不是合法 JSON：' . substr($jsonText !== '' ? $jsonText : $rawText, 0, 300),
                );
            }
        }

        $items = $parsed['items'] ?? [];
        if (! is_array($items) || count($items) === 0) {
            throw new RuntimeException('AI 沒有回傳菜單品項，可能無法判斷店家類型');
        }

        $storeType = trim((string) ($parsed['store_type'] ?? '推測類型未知'));

        // 建立 Store（已存在就用既有的）
        $store = Store::firstOrCreate(
            ['slug' => $slug],
            [
                'name'               => $storeName,
                'category'           => $this->guessCategory($storeType),
                'logo_emoji'         => '🍽️',
                'osm_match_keywords' => null,
                'confidence_level'   => 'low',
                'description'        => '🤖 AI 推測：' . $storeType . '（每項誤差 ±20%，僅供參考）',
            ],
        );

        // 建立菜單
        $createdCount = 0;
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $itemName = trim((string) ($item['name'] ?? ''));
            if ($itemName === '' || mb_strlen($itemName) > 100) continue;

            $category = (string) ($item['category'] ?? 'other');
            if (! in_array($category, self::ALLOWED_CATEGORIES, true)) {
                $category = 'other';
            }

            $servingUnit = trim((string) ($item['serving_unit'] ?? '份'));
            if ($servingUnit === '' || mb_strlen($servingUnit) > 20) {
                $servingUnit = '份';
            }

            $servingSize = (float) ($item['serving_size'] ?? 1);
            if ($servingSize <= 0) $servingSize = 1;

            Food::firstOrCreate(
                [
                    'name'     => $itemName,
                    'brand'    => $storeName,
                    'store_id' => $store->id,
                ],
                [
                    'category'           => $category,
                    'serving_unit'       => $servingUnit,
                    'serving_size'       => $servingSize,
                    'calories'           => (int) max(0, $item['calories']  ?? 0),
                    'protein_g'          => round((float) max(0, $item['protein_g'] ?? 0), 1),
                    'fat_g'              => round((float) max(0, $item['fat_g']     ?? 0), 1),
                    'carbs_g'            => round((float) max(0, $item['carbs_g']   ?? 0), 1),
                    'is_system'          => true,
                    'created_by_user_id' => null,
                    'source_type'        => 'ai_estimate',
                    'confidence_level'   => 'low',
                ],
            );
            $createdCount++;
        }

        if ($createdCount === 0) {
            // 全部 item 都格式不對 → 把空 store 砍掉
            $store->delete();
            throw new RuntimeException('AI 回傳的菜單格式不正確');
        }

        return $store->fresh();
    }

    // =================================================================
    // 內部
    // =================================================================

    private function buildPrompt(string $storeName, string $hint = ''): string
    {
        $categoryList = implode(' / ', self::ALLOWED_CATEGORIES);
        $hintBlock = $hint !== '' ? "使用者提供的關鍵線索（務必嚴格遵守）：{$hint}" : '使用者沒有提供額外線索，請完全依賴搜尋結果判斷類型。';
        $hintRule  = $hint !== ''
            ? "★★★ 最重要：使用者已經告訴你這家店的類型是「{$hint}」，**不要違背**這個線索。\n   即使搜尋結果矛盾，也要以使用者提示為主，列出符合「{$hint}」的品項。\n   舉例：使用者說「健康餐」就只能列健身餐、沙拉、雞胸肉等；說「咖啡」就只能列咖啡飲品 + 輕食，不能跑出滷肉飯。"
            : '';

        return <<<PROMPT
你是台灣餐飲分析助手，可以使用 Google 搜尋。

{$hintBlock}

我會給你一個**台灣店家名稱**。你的任務分兩步：

【步驟 1】用 Google 搜尋這家店的實際菜單
搜尋詞建議：「{$storeName} 菜單」、「{$storeName} 價目表」、「{$storeName} 評論」、「{$storeName} 招牌」
從搜尋結果（Google Maps 評論、部落格、店家粉絲頁、外送平台）找出**這家店實際在賣什麼**。

【步驟 2】根據搜尋結果 + 使用者線索，列出 15-20 個**這家店真實會賣的品項**並估算營養成分。

{$hintRule}

店家名稱：{$storeName}

請最後用以下 JSON 格式回應（可以在 JSON 之前先用幾句話說明你搜尋到什麼，然後用 ```json ... ``` 包住 JSON）：
{
  "store_type": "簡短說明這家店真實的類型（例如：日式便當店、麵店、咖啡廳）",
  "items": [
    {
      "name": "品項名稱",
      "calories": 整數（kcal）,
      "protein_g": 浮點數,
      "fat_g": 浮點數,
      "carbs_g": 浮點數,
      "serving_unit": "份/個/碗/支/塊/盤/杯",
      "serving_size": 浮點數（份量數值，預設 1）,
      "category": "{$categoryList} 之一"
    }
  ]
}

要求：
- **務必先用 Google 搜尋這家店的實際菜單再回答**，不要憑店名猜測
- 列出 15 到 20 個品項，**優先列搜尋結果中真的有看到的品項**
- 如果搜尋找到部分品項但不到 15 個，補上**該店類型最常見的台灣品項**湊滿
- 如果搜尋完全沒有資料（例如店家很冷門），明確在 store_type 標明「無法搜尋到資料，以店名類型推測」，再列該類型的常見台灣品項
- 用台灣消費者熟悉的品項名稱（例：米血糕、鴨翅、王子麵、滷蛋、蛋餅、肉燥飯、牛肉麵、雞腿便當、招牌奶茶）
- 每個品項是「實際點餐時的單位份量」（滷味一塊、麵一碗、便當一份、飲料一杯）
- 按熱量由低到高排序
- 數值務必合理：誤差 ±20% 屬正常，但 protein_g + fat_g + carbs_g 對應的熱量應接近 calories（蛋白質/碳水 4 kcal/g、脂肪 9 kcal/g）

回應格式範例：
"我搜尋了「飯島屋 菜單」，從外送平台看到這家店主要賣日式便當，招牌是雞腿便當、豬排便當..."

```json
{
  "store_type": "...",
  "items": [...]
}
```
PROMPT;
    }

    private function callGeminiWithFallback(string $apiKey, string $prompt, bool $useSearch = false): string
    {
        $lastError = '';
        foreach (self::MODEL_FALLBACKS as $model) {
            try {
                $endpoint = self::ENDPOINT_BASE . '/' . $model . ':generateContent?key=' . urlencode($apiKey);

                $payload = [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                    ],
                ];
                if ($useSearch) {
                    // search 模式：開 google_search tool，不能同時用 responseMimeType
                    $payload['tools'] = [['google_search' => new \stdClass()]];
                } else {
                    // 快速模式：要求結構化 JSON 輸出
                    $payload['generationConfig']['responseMimeType'] = 'application/json';
                }

                $response = Http::acceptJson()
                    ->timeout($useSearch ? 50 : 30)
                    ->post($endpoint, $payload);

                if (! $response->successful()) {
                    $status = $response->status();
                    $body = substr($response->body(), 0, 300);
                    $lastError = "model {$model} status {$status} {$body}";

                    // 401/403：API key 錯誤 → 直接拋（fallback 也救不了）
                    if (in_array($status, [401, 403], true)) {
                        throw new RuntimeException($lastError);
                    }

                    // 其他錯誤（含 400 FAILED_PRECONDITION / User location not supported / 429 / 503）
                    // → fallback 到下一個 model 試試看
                    Log::warning('Gemini status error, trying next model', ['model' => $model, 'status' => $status]);
                    continue;
                }

                $body = $response->json();
                $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (! is_string($rawText) || $rawText === '') {
                    $lastError = "model {$model} 沒有回傳內容";
                    continue;
                }

                return $rawText;
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('Gemini call failed', ['model' => $model, 'error' => $e->getMessage()]);
                continue;
            }
        }

        // 根據錯誤類型給友善訊息
        if (str_contains($lastError, '429') || str_contains($lastError, 'RESOURCE_EXHAUSTED') || str_contains($lastError, 'exceeded your current quota')) {
            throw new RuntimeException('今日 AI 推測配額已用盡，請明天再試（每日免費額度有限）');
        }
        if (str_contains($lastError, 'User location is not supported')) {
            throw new RuntimeException('目前網路位置不支援 Gemini AI（伺服器需在支援的地區）');
        }
        if (str_contains($lastError, '503') || str_contains($lastError, 'UNAVAILABLE')) {
            throw new RuntimeException('Gemini 服務暫時不可用，請稍後再試');
        }
        throw new RuntimeException('AI 服務目前忙碌中，請稍後再試');
    }

    /**
     * Fallback prompt：不用 search、純粹靠店名類型推測。
     * 比 search 版本快很多（5-10 秒），適合搜尋失敗時的備援。
     */
    private function buildFallbackPrompt(string $storeName, string $hint = ''): string
    {
        $categoryList = implode(' / ', self::ALLOWED_CATEGORIES);
        $hintBlock = $hint !== ''
            ? "★★★ 使用者已經告訴你這家店是「{$hint}」，**直接用這個類型**，不要再依店名亂猜。"
            : '';

        return <<<PROMPT
你是台灣餐飲分析助手。給你一個台灣店家名稱，請根據店名判斷類型並列出該類型常見品項。

{$hintBlock}

店家名稱：{$storeName}

判斷店類型的線索：
- 店名含「便當/飯」→ 便當店：列雞腿便當、排骨便當、焢肉便當等台式便當
- 店名含「麵/麵店/麵館」→ 麵店：列牛肉麵、陽春麵、肉燥飯、餛飩湯
- 店名含「滷味/鹹酥雞」→ 滷味/鹹酥雞攤：列米血、豆乾、海帶、雞翅
- 店名含「飲料/茶」→ 飲料店：列珍奶、紅茶、綠茶（每杯 700ml）
- 店名含「咖啡/cafe」→ 咖啡店：列美式、拿鐵、卡布奇諾、輕食
- 店名含「燒/烤」→ 燒烤店：列烤雞、烤肉飯、燒肉
- 店名含「炒」→ 熱炒/小吃：列炒飯、炒麵、炒青菜
- 店名含「壽司/丼/拉麵」→ 日式：列日式品項
- 其他不明 → 列台灣綜合小吃

請完全用以下 JSON 格式回應，不要加任何其他文字：
{
  "store_type": "你判斷的類型",
  "items": [
    {
      "name": "品項名稱",
      "calories": 整數,
      "protein_g": 浮點數,
      "fat_g": 浮點數,
      "carbs_g": 浮點數,
      "serving_unit": "份/碗/個/杯/塊",
      "serving_size": 1.0,
      "category": "{$categoryList} 之一"
    }
  ]
}

要求：
- 列 15 個品項，按熱量低到高排序
- 用台灣消費者熟悉的中文名稱
- 數值合理（蛋白質 4 kcal/g、脂肪 9 kcal/g、碳水 4 kcal/g）
- 不要列出店家可能沒賣的品項（例如便當店不會有日式定食）
PROMPT;
    }

    /**
     * 從 AI 回應的 markdown 文字中抽出 JSON。
     *
     * AI 開了 search grounding 之後，回應通常長這樣：
     *   "我搜尋了xxx，找到這家店是yyy...
     *
     *   ```json
     *   { ... }
     *   ```
     *   "
     *
     * 抽取邏輯：
     *   1. 優先抓 ```json ... ``` 區塊
     *   2. fallback 抓第一個 { 到最後一個 } 之間的內容
     */
    private function extractJson(string $text): string
    {
        // 嘗試 ```json ... ``` markdown 區塊
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            return trim($matches[1]);
        }
        // fallback：```...``` 任意語言標籤
        if (preg_match('/```\w*\s*(.*?)\s*```/s', $text, $matches)) {
            return trim($matches[1]);
        }
        // fallback：第一個 { 到最後一個 }
        $firstBrace = strpos($text, '{');
        $lastBrace = strrpos($text, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            return substr($text, $firstBrace, $lastBrace - $firstBrace + 1);
        }
        return trim($text);
    }

    private function guessCategory(string $storeType): string
    {
        $type = mb_strtolower($storeType);
        if (str_contains($type, '飲') || str_contains($type, '咖啡') || str_contains($type, '茶'))   return 'drink';
        if (str_contains($type, '麵') || str_contains($type, '滷'))                                  return 'noodle';
        if (str_contains($type, '便當') || str_contains($type, '飯'))                                return 'rice_box';
        if (str_contains($type, '速食') || str_contains($type, '漢堡') || str_contains($type, '炸')) return 'fast_food';
        if (str_contains($type, '便利') || str_contains($type, '商店'))                              return 'convenience';
        if (str_contains($type, '小吃') || str_contains($type, '夜市') || str_contains($type, '點心')) return 'snack';
        return 'other';
    }
}
