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
     * 主入口：給定店名，生成 Store（含菜單）
     */
    public function generateForStoreName(string $storeName): Store
    {
        $storeName = trim($storeName);
        if ($storeName === '') {
            throw new RuntimeException('店家名稱不能為空。');
        }
        if (mb_strlen($storeName) > 80) {
            throw new RuntimeException('店家名稱過長。');
        }

        // slug：用 md5 前 12 碼做唯一識別（不含中文，避免 URL/DB 麻煩）
        $slug = 'guess-' . substr(md5($storeName), 0, 12);

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

        $rawText = $this->callGeminiWithFallback($apiKey, $this->buildPrompt($storeName));

        $parsed = json_decode($rawText, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            throw new RuntimeException('AI 回的不是合法 JSON：' . substr($rawText, 0, 200));
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

    private function buildPrompt(string $storeName): string
    {
        $categoryList = implode(' / ', self::ALLOWED_CATEGORIES);

        return <<<PROMPT
你是台灣餐飲分析助手。我會給你一個台灣店家名稱，請你推測這是什麼類型的店，並列出該類型最常見的菜單品項。

店家名稱：{$storeName}

請完全用以下 JSON 格式回應，不要加任何其他文字：
{
  "store_type": "簡短說明你判斷的店類型",
  "items": [
    {
      "name": "品項名稱",
      "calories": 整數（kcal）,
      "protein_g": 浮點數,
      "fat_g": 浮點數,
      "carbs_g": 浮點數,
      "serving_unit": "份/個/碗/支/塊/碗/盤/杯",
      "serving_size": 浮點數（份量數值，預設 1）,
      "category": "{$categoryList} 之一"
    }
  ]
}

要求：
- 列出 15 到 20 個最常見品項，按熱量由低到高排序
- 用台灣消費者熟悉的品項名稱（例如：米血糕、鴨翅、王子麵、滷蛋、蛋餅、肉燥飯、牛肉麵）
- 每個品項是「實際點餐時的單位份量」（如：滷味一塊、麵一碗、便當一份）
- 數值為合理估算，誤差 ±20% 屬正常
- 各種店家類型應對：
   * 滷味/鹹酥雞 → 列各式滷味品項（每份 1 顆/塊）
   * 麵店 → 列各式麵類（每份 1 碗）
   * 便當店 → 列便當組合（每份 1 個便當）
   * 早餐店 → 列三明治、蛋餅、漢堡（每份 1 份）
   * 飲料店 → 列各式飲品（每份 1 杯 700ml）
   * 自助餐 → 列常見配菜（每份 1 份）
   * 夜市/小吃 → 列鹹酥雞、章魚燒、地瓜球等（每份 1 份）
- 如果店名太模糊（例如「阿姨的店」），store_type 設為「綜合小吃店」並列台灣常見小吃
- 數值務必合理：calories ≥ 0，protein_g + fat_g + carbs_g 對應的熱量應接近 calories（蛋白質/碳水 4 kcal/g、脂肪 9 kcal/g）
PROMPT;
    }

    private function callGeminiWithFallback(string $apiKey, string $prompt): string
    {
        $lastError = '';
        foreach (self::MODEL_FALLBACKS as $model) {
            try {
                $endpoint = self::ENDPOINT_BASE . '/' . $model . ':generateContent?key=' . urlencode($apiKey);
                $response = Http::acceptJson()
                    ->timeout(45)
                    ->post($endpoint, [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature'      => 0.3,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);

                if (! $response->successful()) {
                    $lastError = "model {$model} status {$response->status()}";
                    if (in_array($response->status(), [400, 401, 403], true)) {
                        // 永久錯誤直接拋
                        throw new RuntimeException($lastError . ' ' . substr($response->body(), 0, 200));
                    }
                    continue; // 503/429 等暫時錯 → 試下一個 model
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

        throw new RuntimeException('AI 服務目前忙碌中，請稍後再試（' . $lastError . '）');
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
