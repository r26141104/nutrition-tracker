<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 用 Google Gemini 多模態模型直接「看圖」並回傳中文食物名稱。
 *
 * 比起 Cloud Vision 的英文標籤翻譯（ex: Roti canai → 蔥抓餅），
 * Gemini 可以直接識別這是「蔥抓餅」、「滷肉飯」、「珍珠奶茶」這類台式食物，
 * 因為它的訓練資料涵蓋全球料理（含台灣常見食物）。
 *
 * 流程：
 *   1. 把使用者上傳的圖片用 base64 編碼塞進 Gemini 請求
 *   2. 用結構化 prompt 要求 Gemini 回傳 JSON：[{name, score}, ...]
 *   3. caller 用這些中文名稱去 foods 表搜尋
 */
class GeminiVisionService
{
    private const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** 使用支援多模態的模型 */
    private const MODEL_FALLBACKS = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
    ];

    /**
     * 給定圖片 binary，回傳 Gemini 識別的食物名稱列表。
     *
     * @return array{names: array<int, array{name: string, score: float}>, raw: string}
     */
    public function recognize(string $imageContent, string $mimeType = 'image/jpeg'): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('未設定 Gemini API key');
        }

        $prompt = <<<PROMPT
你是台灣食物辨識專家。我會給你一張食物照片，請你判斷這張照片裡的「最可能食物名稱」。

要求：
1. 用台灣消費者熟悉的中文名稱（例：蔥抓餅、滷肉飯、雞腿便當、珍珠奶茶）
2. 列出最可能的 3-5 個候選，由高到低排序
3. score 是 0-1 信心值
4. 只列食物本身，不要列「碗」「盤子」「醬料」等容器或配菜
5. 如果是台灣特有食物（蛋餅、蔥油餅、刈包、雞排等），優先用台式名稱
6. 如果看起來像國際料理（漢堡、披薩、義大利麵）就用通俗名稱
7. 完全用 JSON 格式回應，不要加額外文字

範例輸出：
[
  {"name": "蔥抓餅", "score": 0.85},
  {"name": "蛋餅", "score": 0.55},
  {"name": "蔥油餅", "score": 0.45}
]
PROMPT;

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data'      => base64_encode($imageContent),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature'      => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        $lastError = '';
        foreach (self::MODEL_FALLBACKS as $model) {
            try {
                $endpoint = self::ENDPOINT_BASE . '/' . $model . ':generateContent?key=' . urlencode($apiKey);
                $response = Http::acceptJson()
                    ->timeout(45)
                    ->post($endpoint, $payload);

                if (! $response->successful()) {
                    $status = $response->status();
                    $body = substr($response->body(), 0, 300);
                    $lastError = "model {$model} status {$status} {$body}";

                    // 401/403：key 錯 → 直接拋
                    if (in_array($status, [401, 403], true)) {
                        throw new RuntimeException($lastError);
                    }

                    // 400 FAILED_PRECONDITION / User location not supported / 429 / 503
                    // → fallback 到下一個 model
                    continue;
                }

                $body = $response->json();
                $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (! is_string($rawText) || $rawText === '') {
                    $lastError = "model {$model} 無回應";
                    continue;
                }

                $parsed = json_decode($rawText, true);
                if (! is_array($parsed)) {
                    $lastError = "model {$model} 回的不是 JSON";
                    continue;
                }

                $names = [];
                foreach ($parsed as $item) {
                    if (! is_array($item)) continue;
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name === '' || mb_strlen($name) > 50) continue;
                    $names[] = [
                        'name'  => $name,
                        'score' => (float) ($item['score'] ?? 0.5),
                    ];
                }

                if (empty($names)) {
                    $lastError = "model {$model} 沒列出食物";
                    continue;
                }

                return ['names' => $names, 'raw' => $rawText];
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }

        throw new RuntimeException('Gemini Vision 失敗：' . $lastError);
    }
}
