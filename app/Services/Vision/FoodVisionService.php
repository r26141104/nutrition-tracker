<?php

namespace App\Services\Vision;

use App\Models\Food;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 食物照片辨識 service。
 *
 * 流程：
 *   1. 讀 service account JSON
 *   2. 用 JWT 換 OAuth access token（快取 50 分鐘）
 *   3. 呼叫 Google Cloud Vision REST API（LABEL_DETECTION + WEB_DETECTION）
 *   4. 把回來的標籤對映到 foods 資料表（visibleTo 過濾）
 *   5. 回傳候選食物清單給前端，使用者自己選最像的一個
 *
 * 這個 service 不會自動寫入 meals，**人在迴路裡**避免 AI 幻覺亂塞紀錄。
 */
class FoodVisionService
{
    /** OAuth token 快取 key */
    private const TOKEN_CACHE_KEY = 'google_vision_oauth_token';

    /** OAuth token 快取秒數（Google 給 1 小時，留 10 分鐘 buffer） */
    private const TOKEN_TTL = 3000;

    /** Cloud Vision API endpoint */
    private const VISION_ENDPOINT = 'https://vision.googleapis.com/v1/images:annotate';

    /** OAuth token endpoint */
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /** 每個 group 最多回幾筆候選 */
    private const MAX_CANDIDATES = 10;

    /**
     * 英文標籤對應中文關鍵字 / foods.category 的查表。
     *
     * 找不到對應的標籤會被忽略（避免「food」「meal」「dish」之類太通用的字干擾搜尋）。
     */
    private const LABEL_TO_KEYWORDS = [
        // 主食
        'fried chicken'  => ['雞排', '炸雞'],
        'chicken'        => ['雞肉', '雞排', '雞腿'],
        'pork'           => ['豬', '排骨', '焢肉'],
        'pork chop'      => ['排骨'],
        'beef'           => ['牛', '牛肉'],
        'fish'           => ['魚', '鮭魚', '鯖魚'],
        'salmon'         => ['鮭魚'],
        'shrimp'         => ['蝦'],
        'egg'            => ['蛋', '茶葉蛋'],
        'tofu'           => ['豆腐'],

        // 主食類
        'rice'           => ['飯', '便當'],
        'white rice'     => ['白飯', '便當'],
        'fried rice'     => ['炒飯'],
        'bento'          => ['便當'],
        'lunch box'      => ['便當'],
        'noodle'         => ['麵'],
        'noodles'        => ['麵'],
        'beef noodles'   => ['牛肉麵'],
        'ramen'          => ['拉麵'],
        'pasta'          => ['義大利麵'],
        'bread'          => ['麵包', '吐司'],
        'toast'          => ['吐司'],
        'sandwich'       => ['三明治'],

        // 飲料
        'tea'            => ['茶'],
        'milk tea'       => ['奶茶'],
        'bubble tea'     => ['珍珠奶茶', '奶茶'],
        'coffee'         => ['咖啡', '拿鐵'],
        'latte'          => ['拿鐵'],
        'soy milk'       => ['豆漿'],
        'juice'          => ['果汁'],
        'water'          => [],

        // 速食
        'burger'         => ['漢堡'],
        'hamburger'      => ['漢堡'],
        'pizza'          => ['披薩'],
        'fries'          => ['薯條'],
        'french fries'   => ['薯條'],
        'hot dog'        => ['熱狗'],

        // 點心 / 水果
        'salad'          => ['沙拉'],
        'apple'          => ['蘋果'],
        'banana'         => ['香蕉'],
        'cake'           => ['蛋糕'],
        'cookie'         => ['餅乾'],
        'chocolate'      => ['巧克力'],
        'biscuit'        => ['餅乾'],
        'snack'          => [],
        'candy'          => ['糖果'],

        // 中華料理
        'dumpling'       => ['餃', '水餃', '煎餃'],
        'soup'           => ['湯'],
        'congee'         => ['粥'],
        'bun'            => ['包', '饅頭'],
        'pancake'        => ['鬆餅', '蛋餅'],

        // 通用詞 → 跳過（太通用搜不到結果）
        'food'           => [],
        'meal'           => [],
        'cuisine'        => [],
        'ingredient'     => [],
        'dish'           => [],
        'recipe'         => [],
        'fast food'      => [],
        'breakfast'      => [],
        'lunch'          => [],
        'dinner'         => [],
        'baked goods'    => [],
        'staple food'    => [],
    ];

    /**
     * 標籤對映到 foods.category（次要 fallback）。
     */
    private const LABEL_TO_CATEGORY = [
        'lunch box'   => 'rice_box',
        'bento'       => 'rice_box',
        'rice'        => 'rice_box',
        'noodle'      => 'noodle',
        'noodles'     => 'noodle',
        'tea'         => 'drink',
        'coffee'      => 'drink',
        'juice'       => 'drink',
        'soy milk'    => 'drink',
        'beverage'    => 'drink',
        'drink'       => 'drink',
        'burger'      => 'fast_food',
        'pizza'       => 'fast_food',
        'fries'       => 'fast_food',
        'fast food'   => 'fast_food',
        'snack'       => 'snack',
        'cookie'      => 'snack',
        'cake'        => 'snack',
        'chocolate'   => 'snack',
        'fruit'       => 'snack',
    ];

    /**
     * 主入口：把照片送去 Google Vision，回傳辨識結果 + 候選食物。
     *
     * @param  string  $imageContent  二進位圖片資料
     * @return array<string, mixed>
     */
    public function analyze(User $user, string $imageContent): array
    {
        $rawLabels = $this->callVisionApi($imageContent);

        // 整理出所有候選關鍵字
        $allLabels = $rawLabels['labels'];     // [['name' => 'fried chicken', 'score' => 0.92], ...]
        $allEntities = $rawLabels['entities']; // [['name' => '雞排', 'score' => 0.85], ...]

        // 對映到 foods 候選清單
        $candidates = $this->matchToFoods($user, $allLabels, $allEntities);

        return [
            'labels'     => $allLabels,
            'entities'   => $allEntities,
            'candidates' => $candidates,
            'notes'      => [
                'AI 辨識結果僅供參考，實際食物與份量請自行確認。',
                'Google Cloud Vision 給的標籤是英文，系統已嘗試對應到食物資料庫。',
                '若沒有合適候選，請手動到「食物資料庫」搜尋或新增。',
            ],
        ];
    }

    // ========================================================================
    // Google Cloud Vision API 呼叫
    // ========================================================================

    /**
     * 呼叫 Vision API，回傳整理過的標籤。
     *
     * @return array{labels: array<int, array{name: string, score: float}>, entities: array<int, array{name: string, score: float}>}
     */
    private function callVisionApi(string $imageContent): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post(self::VISION_ENDPOINT, [
                'requests' => [[
                    'image' => [
                        'content' => base64_encode($imageContent),
                    ],
                    'features' => [
                        ['type' => 'LABEL_DETECTION', 'maxResults' => 15],
                        ['type' => 'WEB_DETECTION',   'maxResults' => 10],
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Cloud Vision API 呼叫失敗：' . $response->status() . ' ' . $response->body(),
            );
        }

        $body = $response->json();
        $r = $body['responses'][0] ?? [];

        if (isset($r['error'])) {
            throw new RuntimeException(
                'Cloud Vision API 回錯誤：' . ($r['error']['message'] ?? 'unknown'),
            );
        }

        $labels = [];
        foreach ($r['labelAnnotations'] ?? [] as $label) {
            $labels[] = [
                'name'  => (string) ($label['description'] ?? ''),
                'score' => (float) ($label['score'] ?? 0),
            ];
        }

        $entities = [];
        foreach ($r['webDetection']['webEntities'] ?? [] as $entity) {
            $name = (string) ($entity['description'] ?? '');
            if ($name === '') continue;
            $entities[] = [
                'name'  => $name,
                'score' => (float) ($entity['score'] ?? 0),
            ];
        }

        return ['labels' => $labels, 'entities' => $entities];
    }

    // ========================================================================
    // OAuth access token（用 service account JWT 換）
    // ========================================================================

    private function getAccessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->loadCredentials();

        $now = time();
        $payload = [
            'iss'   => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-vision',
            'aud'   => self::TOKEN_ENDPOINT,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $assertion = $this->signJwtRs256($payload, $credentials['private_key']);

        $response = Http::asForm()
            ->timeout(15)
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google OAuth token 申請失敗：' . $response->status() . ' ' . $response->body(),
            );
        }

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new RuntimeException('Google OAuth 沒回傳 access_token');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL);

        return $token;
    }

    /**
     * 用 PHP 內建 openssl_sign 簽 RS256 JWT（取代 firebase/php-jwt 套件）。
     *
     * 結構：base64url(header).base64url(payload).base64url(signature)
     *
     * @param  array<string, mixed>  $payload
     */
    private function signJwtRs256(array $payload, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signingInput = implode('.', $segments);

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if ($ok === false) {
            throw new RuntimeException('JWT 簽名失敗：openssl_sign 回傳 false');
        }

        $segments[] = $this->base64UrlEncode($signature);
        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * 讀 service account JSON 憑證。
     *
     * 兩種來源（依下列順序）：
     *   1. GOOGLE_CREDENTIALS_JSON_BASE64：把整個 JSON 用 base64 編碼後放在環境變數
     *      （雲端部署用，避免要在伺服器上放檔案）
     *   2. GOOGLE_CREDENTIALS_PATH：本機檔案路徑（本機開發用）
     *
     * @return array{client_email: string, private_key: string}
     */
    private function loadCredentials(): array
    {
        // === 來源 1：base64 環境變數（雲端用）===
        $base64 = (string) config('services.google_vision.credentials_json_base64');
        if ($base64 !== '') {
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                throw new RuntimeException(
                    'GOOGLE_CREDENTIALS_JSON_BASE64 不是合法的 base64 字串',
                );
            }
            $data = json_decode($decoded, true);
            if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
                throw new RuntimeException('GOOGLE_CREDENTIALS_JSON_BASE64 解碼後 JSON 格式錯誤');
            }
            return $data;
        }

        // === 來源 2：本機檔案路徑（本機開發用）===
        $path = (string) config('services.google_vision.credentials_path');
        if ($path === '') {
            throw new RuntimeException(
                '尚未設定 Google Vision 憑證。請在 .env 加 GOOGLE_CREDENTIALS_PATH（本機）'
                . '或 GOOGLE_CREDENTIALS_JSON_BASE64（雲端）。',
            );
        }

        if (! is_readable($path)) {
            throw new RuntimeException(
                "找不到或無法讀取 Google Vision 憑證檔：{$path}",
            );
        }

        $content = file_get_contents($path);
        $data = json_decode((string) $content, true);
        if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new RuntimeException('Google Vision 憑證檔格式錯誤');
        }

        return $data;
    }

    // ========================================================================
    // 標籤 → foods 模糊比對
    // ========================================================================

    /**
     * 把 Vision 給的標籤對應到使用者可見的 foods。
     *
     * 策略：
     *   1. 先收集所有可能的「中文關鍵字」（從英文標籤翻譯 + 直接用 web entities）
     *   2. 用每個關鍵字對 foods.name LIKE 比對
     *   3. 把匹配的食物去重、按 Vision 信心 + 出現次數排序
     *
     * @param  array<int, array{name: string, score: float}>  $labels
     * @param  array<int, array{name: string, score: float}>  $entities
     * @return array<int, array<string, mixed>>
     */
    private function matchToFoods(User $user, array $labels, array $entities): array
    {
        // === Step 1：收集關鍵字（中文）===
        $keywords = []; // [keyword => max_score]

        // 英文標籤 → 翻譯成中文關鍵字
        foreach ($labels as $label) {
            $key = strtolower(trim($label['name']));
            $score = (float) $label['score'];

            if (isset(self::LABEL_TO_KEYWORDS[$key])) {
                foreach (self::LABEL_TO_KEYWORDS[$key] as $cn) {
                    $keywords[$cn] = max($keywords[$cn] ?? 0, $score);
                }
            }
        }

        // Web entities → 如果是中文直接用，是英文也試查表
        foreach ($entities as $entity) {
            $name = trim($entity['name']);
            $score = (float) $entity['score'];
            if ($name === '') continue;

            // 含中文字 → 直接當關鍵字
            if (preg_match('/\p{Han}/u', $name)) {
                $keywords[$name] = max($keywords[$name] ?? 0, $score);
            } else {
                // 英文 entity → 查表翻譯
                $lower = strtolower($name);
                if (isset(self::LABEL_TO_KEYWORDS[$lower])) {
                    foreach (self::LABEL_TO_KEYWORDS[$lower] as $cn) {
                        $keywords[$cn] = max($keywords[$cn] ?? 0, $score);
                    }
                }
            }
        }

        // === Step 2：標籤對映到 category（fallback）===
        $categories = []; // [category => max_score]
        foreach ($labels as $label) {
            $key = strtolower(trim($label['name']));
            if (isset(self::LABEL_TO_CATEGORY[$key])) {
                $cat = self::LABEL_TO_CATEGORY[$key];
                $categories[$cat] = max($categories[$cat] ?? 0, (float) $label['score']);
            }
        }

        // === Step 3：用每個關鍵字對 foods 做 LIKE 比對 ===
        $matchedFoodIds = []; // [food_id => score]

        if (! empty($keywords)) {
            $query = Food::query()->visibleTo($user->id);
            $query->where(function ($q) use ($keywords) {
                foreach (array_keys($keywords) as $kw) {
                    $q->orWhere('name', 'like', "%{$kw}%");
                }
            });

            foreach ($query->limit(50)->get() as $food) {
                // 算這筆 food 的 score（被多少個關鍵字命中、命中關鍵字的最高分）
                $score = 0.0;
                foreach ($keywords as $kw => $kwScore) {
                    if (mb_stripos($food->name, $kw) !== false) {
                        $score = max($score, $kwScore);
                    }
                }
                $matchedFoodIds[$food->id] = ['food' => $food, 'score' => $score];
            }
        }

        // category fallback：如果關鍵字命中不多，再用 category 補
        if (count($matchedFoodIds) < 5 && ! empty($categories)) {
            $catFoods = Food::query()
                ->visibleTo($user->id)
                ->whereIn('category', array_keys($categories))
                ->limit(20)
                ->get();

            foreach ($catFoods as $food) {
                if (! isset($matchedFoodIds[$food->id])) {
                    $catScore = $categories[$food->category] ?? 0.5;
                    $matchedFoodIds[$food->id] = [
                        'food'  => $food,
                        'score' => $catScore * 0.7, // category fallback 降權重
                    ];
                }
            }
        }

        // === Step 4：排序輸出 ===
        usort($matchedFoodIds, fn ($a, $b) => $b['score'] <=> $a['score']);

        $candidates = [];
        foreach (array_slice($matchedFoodIds, 0, self::MAX_CANDIDATES) as $row) {
            $f = $row['food'];
            $candidates[] = [
                'id'               => $f->id,
                'name'             => $f->name,
                'brand'            => $f->brand,
                'category'         => $f->category,
                'serving_unit'     => $f->serving_unit,
                'serving_size'     => (float) $f->serving_size,
                'calories'         => (int) $f->calories,
                'protein_g'        => (float) $f->protein_g,
                'fat_g'            => (float) $f->fat_g,
                'carbs_g'          => (float) $f->carbs_g,
                'source_type'      => $f->source_type,
                'confidence_level' => $f->confidence_level,
                'match_score'      => round($row['score'], 3),
            ];
        }

        return $candidates;
    }
}
