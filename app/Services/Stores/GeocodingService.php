<?php

namespace App\Services\Stores;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 地理編碼服務 — 用 OpenStreetMap Nominatim API 把地址/地名轉成經緯度。
 * 完全免費、不需 API key，但有使用條款限制：
 *   - 必須設置 User-Agent
 *   - 最多 1 req/sec（我們加 cache 處理）
 *   - 不能拿來做大量批次查詢
 *
 * 適合：使用者主動輸入地址 → 一筆查詢，這個量級沒問題。
 */
class GeocodingService
{
    private const NOMINATIM_ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /** 快取時間（秒）— 同一個地址 1 小時內查過就用 cache */
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * 把字串地址/地名轉成經緯度（取第一筆最相關的）。
     *
     * @return array{
     *   lat: float,
     *   lon: float,
     *   display_name: string,
     *   type: string,
     *   importance: float
     * }
     */
    public function geocode(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new RuntimeException('請輸入地址或地名。');
        }
        if (mb_strlen($query) > 200) {
            throw new RuntimeException('查詢字串過長。');
        }

        $cacheKey = 'geocode:' . md5($query);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($query) {
            return $this->callNominatim($query);
        });
    }

    /**
     * 呼叫 Nominatim API。
     *
     * Nominatim 使用條款：
     *   - 1 req/sec
     *   - 必須帶 User-Agent
     *   - 不能拿來做大量批次查詢
     *
     * 為了禮貌使用，發請求前先等 1 秒（避免短時間內連發）。
     * 收到 429 時用更友善的訊息。
     */
    private function callNominatim(string $query): array
    {
        // Nominatim 速率限制：禮貌等待 1 秒，避免被 ban
        usleep(1_100_000); // 1.1 秒

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->withHeaders([
                    // Nominatim 強制要 User-Agent
                    'User-Agent' => 'NutritionTracker/1.0 (school-project; contact: chishare66@gmail.com)',
                ])
                ->get(self::NOMINATIM_ENDPOINT, [
                    'q'              => $query,
                    'format'         => 'json',
                    'addressdetails' => 0,
                    'limit'          => 1,
                    // 限定台灣，避免「中正路」搜到日本中正路之類
                    'countrycodes'   => 'tw',
                    // 接受中文回應
                    'accept-language' => 'zh-TW',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Nominatim call failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('地址查詢服務暫時無法使用，請稍後再試。');
        }

        // 429 = 限流，給友善訊息
        if ($response->status() === 429) {
            Log::info('Nominatim rate limited', ['query' => $query]);
            throw new RuntimeException(
                '地址搜尋次數過多，OpenStreetMap 服務正在限流。請等 30 秒後再試，或暫時改用「📍 我的位置」。',
            );
        }

        if (! $response->successful()) {
            Log::warning('Nominatim returned error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
            throw 