<?php

namespace App\Services\Stores;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 用 OpenStreetMap Overpass API 查詢使用者附近的餐廳。
 * 完全免費、不需要 API key。
 *
 * 流程：
 *   1. 接收使用者 lat/lon
 *   2. 用 Overpass QL 查 amenity=restaurant|cafe|fast_food|bar|food_court 的節點
 *   3. 解析 JSON、計算每個節點到使用者的距離
 *   4. 比對 stores 表的 osm_match_keywords，標記哪些是我們有菜單的連鎖店
 *   5. 排序：有菜單的優先 + 距離由近到遠
 *
 * Overpass API 有 QPS 限制，加 5 分鐘快取避免砲他們的 server。
 */
class NearbyStoresService
{
    /** 公開的 Overpass API endpoint（多備援，有狀況可換） */
    private const OVERPASS_ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /** 預設搜尋半徑（公尺） */
    private const DEFAULT_RADIUS_METERS = 1000;

    /** 最多回傳幾筆（避免極端情況回 1000+ 筆把瀏覽器拖垮） */
    private const MAX_RESULTS = 300;

    /** 同名同地點去重距離（公尺）：node + way 同地點時保留最近的一筆 */
    private const DEDUPE_PROXIMITY_METERS = 50;

    /** 快取時間（秒） */
    private const CACHE_TTL_SECONDS = 300;

    /**
     * 主入口：找指定位置附近的餐廳。
     *
     * @return array<int, array{
     *   osm_id: int,
     *   name: string,
     *   amenity: string,
     *   lat: float,
     *   lon: float,
     *   distance_m: int,
     *   matched_store: array{id: int, name: string, slug: string, logo_emoji: ?string}|null,
     * }>
     */
    public function findNearby(float $lat, float $lon, ?int $radius = null): array
    {
        $radius ??= self::DEFAULT_RADIUS_METERS;

        // 快取 key：v2 = 擴大 query 範圍版本（含 way + shop + cuisine）
        // 改 query 邏輯時記得 bump 版號，否則舊 cache 會擋住新結果
        $cacheKey = sprintf('osm_nearby_v2:%.4f:%.4f:%d', $lat, $lon, $radius);

        $rawResults = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->queryOverpass($lat, $lon, $radius),
        );

        // 載入所有 stores 一次（後面比對用）
        $allStores = Store::all();

        $results = [];
        foreach ($rawResults as $row) {
            $name = $row['name'];
            if ($name === '') continue;

            $distance = $this->haversineDistance($lat, $lon, $row['lat'], $row['lon']);

            $matched = $this->matchToStore($name, $allStores);

            $results[] = [
                'osm_id'        => $row['osm_id'],
                'name'          => $name,
                'amenity'       => $row['amenity'],
                'lat'           => $row['lat'],
                'lon'           => $row['lon'],
                'distance_m'    => (int) round($distance),
                'matched_store' => $matched ? [
                    'id'         => $matched->id,
                    'name'       => $matched->name,
                    'slug'       => $matched->slug,
                    'logo_emoji' => $matched->logo_emoji,
                ] : null,
            ];
        }

        // 先依距離排序（後面 dedupe 邏輯會用到）
        usort($results, fn ($a, $b) => $a['distance_m'] - $b['distance_m']);

        // Dedupe：
        //   (a) 連鎖店：同 store_id 只留最近一家（+ 記錄分店數）
        //   (b) 非連鎖店：同名 + 50 公尺內視為同一店（OSM node + way 重複）
        $deduped = [];
        $seenStoreIds = [];
        $unmatchedSeen = [];   // [name => [['idx'=>i, 'lat'=>..., 'lon'=>...], ...]]
        foreach ($results as $row) {
            $storeId = $row['matched_store']['id'] ?? null;
            if ($storeId !== null) {
                // 連鎖店：依 store_id 去重
                if (isset($seenStoreIds[$storeId])) {
                    $deduped[$seenStoreIds[$storeId]]['nearby_branch_count']++;
                    continue;
                }
                $seenStoreIds[$storeId] = count($deduped);
                $row['nearby_branch_count'] = 1;
                $deduped[] = $row;
                continue;
            }

            // 非連鎖店：依名稱 + 50m 距離去重
            $name = $row['name'];
            $isDup = false;
            if (isset($unmatchedSeen[$name])) {
                foreach ($unmatchedSeen[$name] as $prev) {
                    $d = $this->haversineDistance(
                        $prev['lat'], $prev['lon'],
                        $row['lat'], $row['lon'],
                    );
                    if ($d < self::DEDUPE_PROXIMITY_METERS) {
                        $isDup = true;
                        break;
                    }
                }
            }
            if ($isDup) continue;
            $unmatchedSeen[$name][] = ['lat' => $row['lat'], 'lon' => $row['lon']];
            $row['nearby_branch_count'] = 1;
            $deduped[] = $row;
        }

        // 最終排序：有 matched_store 優先 + 距離近的優先
        usort($deduped, function ($a, $b) {
            $aMatched = $a['matched_store'] !== null ? 0 : 1;
            $bMatched = $b['matched_store'] !== null ? 0 : 1;
            if ($aMatched !== $bMatched) return $aMatched - $bMatched;
            return $a['distance_m'] - $b['distance_m'];
        });

        return array_slice($deduped, 0, self::MAX_RESULTS);
    }

    // ========================================================================
    // 內部
    // ========================================================================

    /**
     * 真的呼叫 Overpass API。
     *
     * 涵蓋類型：
     *   amenity: restaurant / cafe / fast_food / bar / pub / food_court / ice_cream / biergarten
     *   shop:    bakery / confectionery / convenience / supermarket / deli / pastry / butcher
     *   cuisine: 任何有 cuisine tag 的（補一些只標 cuisine 沒標 amenity 的店）
     *
     * 同時查 node + way（很多大店在 OSM 是用 way 表示建築物多邊形）。
     * 用 `out center` 讓 way 自動帶上代表座標。
     *
     * @return array<int, array{osm_id: int, name: string, amenity: string, lat: float, lon: float}>
     */
    private function queryOverpass(float $lat, float $lon, int $radius): array
    {
        // Overpass QL：盡可能涵蓋所有跟「吃」有關的場所
        $amenityRegex = 'restaurant|cafe|fast_food|bar|pub|food_court|ice_cream|biergarten';
        $shopRegex    = 'bakery|confectionery|convenience|supermarket|deli|pastry|butcher';

        $query = <<<OVERPASS
        [out:json][timeout:30];
        (
          node["amenity"~"^({$amenityRegex})\$"](around:{$radius},{$lat},{$lon});
          way ["amenity"~"^({$amenityRegex})\$"](around:{$radius},{$lat},{$lon});
          node["shop"~"^({$shopRegex})\$"](around:{$radius},{$lat},{$lon});
          way ["shop"~"^({$shopRegex})\$"](around:{$radius},{$lat},{$lon});
          node["cuisine"](around:{$radius},{$lat},{$lon});
          way ["cuisine"](around:{$radius},{$lat},{$lon});
        );
        out center;
        OVERPASS;

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->withHeaders(['User-Agent' => 'NutritionTracker/1.0 (school-project)'])
                ->post(self::OVERPASS_ENDPOINT, ['data' => $query]);
        } catch (\Throwable $e) {
            Log::warning('Overpass API call failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('附近店家查詢服務暫時無法使用，請稍後再試。');
        }

        if (! $response->successful()) {
            Log::warning('Overpass API returned error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException('附近店家查詢失敗（status: ' . $response->status() . '）');
        }

        $body = $response->json();
        $elements = $body['elements'] ?? [];
        if (! is_array($elements)) return [];

        $results = [];
        foreach ($elements as $el) {
            $tags = $el['tags'] ?? [];
            // 嘗試多種 name tag（中文、繁中、英文）
            $name = trim((string) (
                $tags['name:zh-Hant']
                ?? $tags['name:zh']
                ?? $tags['name']
                ?? $tags['name:en']
                ?? ''
            ));
            if ($name === '') continue;

            // node 有 lat/lon、way 用 out center 後會在 center 欄位
            $rowLat = $el['lat'] ?? $el['center']['lat'] ?? null;
            $rowLon = $el['lon'] ?? $el['center']['lon'] ?? null;
            if ($rowLat === null || $rowLon === null) continue;

            // 標籤優先順序：amenity > shop > cuisine（給前端顯示用）
            $amenityType = (string) (
                $tags['amenity']
                ?? $tags['shop']
                ?? ($tags['cuisine'] ? 'restaurant' : 'restaurant')
            );

            $results[] = [
                'osm_id'  => (int) ($el['id'] ?? 0),
                'name'    => $name,
                'amenity' => $amenityType,
                'lat'     => (float) $rowLat,
                'lon'     => (float) $rowLon,
            ];
        }

        return $results;
    }

    /**
     * 把一個 OSM 店名比對到我們的 Store 連鎖店資料。
     */
    private function matchToStore(string $osmName, $allStores): ?Store
    {
        $osmLower = mb_strtolower($osmName);

        foreach ($allStores as $store) {
            $keywords = $store->osm_match_keywords ?? [];
            if (! is_array($keywords)) continue;
            // 把 store name 自己也加進去，雙保險
            $keywords[] = $store->name;

            foreach ($keywords as $kw) {
                $kwLower = mb_strtolower(trim((string) $kw));
                if ($kwLower === '') continue;
                if (mb_strpos($osmLower, $kwLower) !== false) {
                    return $store;
                }
            }
        }

        return null;
    }

    /**
     * Haversine：兩個經緯度之間的距離（公尺）。
     * 地球半徑取 6371000 m（標準近似）。
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
