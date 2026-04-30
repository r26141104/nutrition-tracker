<?php

namespace Database\Seeders;

use App\Models\Food;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * 階段 I：台灣常見連鎖店 + 菜單種子資料
 *
 * 資料來源原則：
 *   - 速食業（麥當勞 / 肯德基 / 摩斯）：以官方營養標示為主，誤差 ±5%
 *   - 飲品業（星巴克 / 85 / 路易莎）：以官方標示為主
 *   - 手搖飲（清心 / 50嵐）：以業者公告 + 學術估算（誤差 ±15%，有糖度差異）
 *   - 其他（八方 / 三商）：以業者公告 + 食品包裝估算（誤差 ±10-15%）
 *
 * 全部標記 source_type = system_estimate, confidence_level = high（連鎖店標示）
 * 例外：手搖飲標示為 medium（因為糖度可調，實際攝取會不同）
 *
 * 重跑安全：用 firstOrCreate keyed on (name, brand, store_id) — 不會重複建立
 */
class ChainStoreSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->dataset() as $storeRow) {
            $store = Store::firstOrCreate(
                ['slug' => $storeRow['slug']],
                [
                    'name'                  => $storeRow['name'],
                    'category'              => $storeRow['category'],
                    'logo_emoji'            => $storeRow['logo_emoji'],
                    'osm_match_keywords'    => $storeRow['osm_match_keywords'],
                    'confidence_level'      => $storeRow['menu_confidence'] ?? 'high',
                    'description'           => $storeRow['description'],
                    'default_opening_hours' => $storeRow['default_opening_hours'] ?? null,
                ],
            );

            // 重跑 seed 時也要把已有的 store 補上 default_opening_hours
            if (! empty($storeRow['default_opening_hours']) && empty($store->default_opening_hours)) {
                $store->update(['default_opening_hours' => $storeRow['default_opening_hours']]);
            }

            foreach ($storeRow['menu'] as $item) {
                Food::firstOrCreate(
                    [
                        'name'     => $item['name'],
                        'brand'    => $storeRow['name'],
                        'store_id' => $store->id,
                    ],
                    [
                        'category'           => $item['category'] ?? $storeRow['category'],
                        'serving_unit'       => $item['serving_unit'] ?? '份',
                        'serving_size'       => $item['serving_size'] ?? 1,
                        'calories'           => $item['calories'],
                        'protein_g'          => $item['protein_g'],
                        'fat_g'              => $item['fat_g'],
                        'carbs_g'            => $item['carbs_g'],
                        'is_system'          => true,
                        'created_by_user_id' => null,
                        'source_type'        => 'system_estimate',
                        'confidence_level'   => $storeRow['menu_confidence'] ?? 'high',
                    ],
                );
            }
        }
    }

    /**
     * 連鎖店與菜單資料集
     *
     * @return array<int, array<string, mixed>>
     */
    private function dataset(): array
    {
        return [
            // ==========================================================
            // 麥當勞 McDonald's （28 項）
            // ==========================================================
            [
                'name' => '麥當勞', 'slug' => 'mcdonalds',
                'category' => 'fast_food', 'logo_emoji' => '🍔',
                'description' => '全球最大速食連鎖。涵蓋早餐、漢堡、雞塊、薯條、冰品。',
                'default_opening_hours' => '每日 06:00 – 24:00（部分分店 24 小時）',
                'osm_match_keywords' => ['麥當勞', "McDonald's", 'McDonalds', 'MCDONALDS', '麥當勞餐廳'],
                'menu' => [
                    // 早餐
                    ['name' => '滿福堡',                'calories' => 290, 'protein_g' => 14,  'fat_g' => 14, 'carbs_g' => 30],
                    ['name' => '蛋滿福堡',              'calories' => 350, 'protein_g' => 18,  'fat_g' => 18, 'carbs_g' => 30],
                    ['name' => '豬肉滿福堡加蛋',        'calories' => 460, 'protein_g' => 21,  'fat_g' => 26, 'carbs_g' => 32],
                    ['name' => '薯餅',                  'calories' => 150, 'protein_g' => 1,   'fat_g' => 9,  'carbs_g' => 16, 'serving_unit' => '個'],
                    ['name' => '鬆餅',                  'calories' => 280, 'protein_g' => 5,   'fat_g' => 7,  'carbs_g' => 50, 'serving_unit' => '份'],
                    // 漢堡
                    ['name' => '大麥克',                'calories' => 530, 'protein_g' => 26,  'fat_g' => 28, 'carbs_g' => 45],
                    ['name' => '麥香雞',                'calories' => 470, 'protein_g' => 18,  'fat_g' => 24, 'carbs_g' => 45],
                    ['name' => '雙層牛肉吉事堡',        'calories' => 440, 'protein_g' => 23,  'fat_g' => 23, 'carbs_g' => 35],
                    ['name' => '勁辣雞腿堡',            'calories' => 530, 'protein_g' => 24,  'fat_g' => 26, 'carbs_g' => 51],
                    ['name' => '四盎司牛肉吉事堡',      'calories' => 520, 'protein_g' => 28,  'fat_g' => 26, 'carbs_g' => 42],
                    ['name' => '雙層牛肉漢堡',          'calories' => 380, 'protein_g' => 22,  'fat_g' => 18, 'carbs_g' => 32],
                    ['name' => '麥香魚',                'calories' => 380, 'protein_g' => 16,  'fat_g' => 18, 'carbs_g' => 38],
                    // 雞塊
                    ['name' => '麥克雞塊 4 塊',         'calories' => 190, 'protein_g' => 11,  'fat_g' => 12, 'carbs_g' => 11, 'serving_unit' => '盒'],
                    ['name' => '麥克雞塊 6 塊',         'calories' => 280, 'protein_g' => 16,  'fat_g' => 17, 'carbs_g' => 16, 'serving_unit' => '盒'],
                    ['name' => '麥克雞塊 9 塊',         'calories' => 420, 'protein_g' => 24,  'fat_g' => 25, 'carbs_g' => 24, 'serving_unit' => '盒'],
                    ['name' => '麥克雞塊 20 塊',        'calories' => 920, 'protein_g' => 53,  'fat_g' => 55, 'carbs_g' => 53, 'serving_unit' => '盒'],
                    ['name' => '麥脆雞 原味 1 塊',      'calories' => 320, 'protein_g' => 22,  'fat_g' => 18, 'carbs_g' => 18, 'serving_unit' => '塊'],
                    // 副餐
                    ['name' => '薯條 中份',             'calories' => 340, 'protein_g' => 4,   'fat_g' => 16, 'carbs_g' => 44],
                    ['name' => '薯條 大份',             'calories' => 510, 'protein_g' => 6,   'fat_g' => 24, 'carbs_g' => 66],
                    ['name' => '玉米湯',                'calories' => 90,  'protein_g' => 2,   'fat_g' => 1,  'carbs_g' => 18, 'serving_unit' => '碗'],
                    // 甜點
                    ['name' => '蘋果派',                'calories' => 270, 'protein_g' => 2,   'fat_g' => 14, 'carbs_g' => 32],
                    ['name' => '巧克力新地',            'calories' => 280, 'protein_g' => 7,   'fat_g' => 10, 'carbs_g' => 42, 'serving_unit' => '杯'],
                    ['name' => '草莓新地',              'calories' => 270, 'protein_g' => 6,   'fat_g' => 10, 'carbs_g' => 40, 'serving_unit' => '杯'],
                    ['name' => '冰炫風 OREO',           'calories' => 380, 'protein_g' => 9,   'fat_g' => 14, 'carbs_g' => 56, 'serving_unit' => '杯'],
                    ['name' => '香草霜淇淋',            'calories' => 130, 'protein_g' => 3,   'fat_g' => 4,  'carbs_g' => 21, 'serving_unit' => '支'],
                    // 飲品
                    ['name' => '可口可樂 中杯',         'calories' => 150, 'protein_g' => 0,   'fat_g' => 0,  'carbs_g' => 38, 'serving_unit' => '杯'],
                    ['name' => '雪碧 中杯',             'calories' => 180, 'protein_g' => 0,   'fat_g' => 0,  'carbs_g' => 45, 'serving_unit' => '杯'],
                    ['name' => '冰紅茶 中杯',           'calories' => 95,  'protein_g' => 0,   'fat_g' => 0,  'carbs_g' => 24, 'serving_unit' => '杯'],
                    ['name' => '冰奶茶 中杯',           'calories' => 150, 'protein_g' => 4,   'fat_g' => 5,  'carbs_g' => 22, 'serving_unit' => '杯'],
                    ['name' => '熱巧克力 中杯',         'calories' => 280, 'protein_g' => 8,   'fat_g' => 8,  'carbs_g' => 44, 'serving_unit' => '杯'],
                    ['name' => '熱拿鐵 中杯',           'calories' => 130, 'protein_g' => 8,   'fat_g' => 5,  'carbs_g' => 12, 'serving_unit' => '杯'],
                ],
            ],

            // ==========================================================
            // 肯德基 KFC （28 項）
            // ==========================================================
            [
                'name' => '肯德基', 'slug' => 'kfc',
                'category' => 'fast_food', 'logo_emoji' => '🍗',
                'description' => '炸雞為主的速食連鎖，名菜包含香酥脆雞、紐奧良烤雞與葡式蛋撻。',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['肯德基', 'KFC', 'Kentucky', '肯德基餐廳'],
                'menu' => [
                    // 雞肉
                    ['name' => '香酥脆雞 1 塊',         'calories' => 290, 'protein_g' => 22, 'fat_g' => 19, 'carbs_g' => 8,  'serving_unit' => '塊'],
                    ['name' => '咔啦脆雞 1 塊',         'calories' => 380, 'protein_g' => 28, 'fat_g' => 24, 'carbs_g' => 12, 'serving_unit' => '塊'],
                    ['name' => '紐奧良烤雞 1 隻',       'calories' => 220, 'protein_g' => 22, 'fat_g' => 13, 'carbs_g' => 4,  'serving_unit' => '隻'],
                    ['name' => '紐奧良烤雞翅 1 隻',     'calories' => 110, 'protein_g' => 8,  'fat_g' => 7,  'carbs_g' => 2,  'serving_unit' => '隻'],
                    ['name' => '紐奧良雞翅 1 隻',       'calories' => 130, 'protein_g' => 9,  'fat_g' => 9,  'carbs_g' => 2,  'serving_unit' => '隻'],
                    ['name' => '香辣雞翅 1 隻',         'calories' => 140, 'protein_g' => 9,  'fat_g' => 10, 'carbs_g' => 3,  'serving_unit' => '隻'],
                    ['name' => '紙包雞 1 塊',           'calories' => 240, 'protein_g' => 18, 'fat_g' => 14, 'carbs_g' => 6,  'serving_unit' => '塊'],
                    ['name' => '嫩烤香雞 1 塊',         'calories' => 200, 'protein_g' => 24, 'fat_g' => 11, 'carbs_g' => 1,  'serving_unit' => '塊'],
                    // 雞塊
                    ['name' => '上校雞塊 6 塊',         'calories' => 320, 'protein_g' => 18, 'fat_g' => 19, 'carbs_g' => 18, 'serving_unit' => '盒'],
                    ['name' => '上校雞塊 9 塊',         'calories' => 480, 'protein_g' => 27, 'fat_g' => 28, 'carbs_g' => 27, 'serving_unit' => '盒'],
                    ['name' => '上校雞塊 20 塊',        'calories' => 1060,'protein_g' => 60, 'fat_g' => 62, 'carbs_g' => 60, 'serving_unit' => '盒'],
                    ['name' => '黃金雞米花 中份',       'calories' => 230, 'protein_g' => 12, 'fat_g' => 14, 'carbs_g' => 14, 'serving_unit' => '盒'],
                    ['name' => '黃金雞米花 大份',       'calories' => 350, 'protein_g' => 18, 'fat_g' => 22, 'carbs_g' => 22, 'serving_unit' => '盒'],
                    // 漢堡與捲類
                    ['name' => '雙層牛起司堡',         'calories' => 480, 'protein_g' => 26, 'fat_g' => 26, 'carbs_g' => 38],
                    ['name' => '香脆雞腿堡',            'calories' => 440, 'protein_g' => 24, 'fat_g' => 21, 'carbs_g' => 38],
                    ['name' => '咔啦雞腿堡',            'calories' => 520, 'protein_g' => 26, 'fat_g' => 26, 'carbs_g' => 42],
                    ['name' => '紐奧良烤雞腿堡',        'calories' => 440, 'protein_g' => 24, 'fat_g' => 18, 'carbs_g' => 42],
                    ['name' => '義式青醬燻雞堡',        'calories' => 480, 'protein_g' => 22, 'fat_g' => 24, 'carbs_g' => 42],
                    ['name' => '雞肉捲',                'calories' => 380, 'protein_g' => 18, 'fat_g' => 16, 'carbs_g' => 38],
                    // 副餐
                    ['name' => '起司條 4 條',           'calories' => 220, 'protein_g' => 8,  'fat_g' => 13, 'carbs_g' => 18, 'serving_unit' => '盒'],
                    ['name' => '薯條 中份',             'calories' => 320, 'protein_g' => 4,  'fat_g' => 15, 'carbs_g' => 42],
                    ['name' => '薯條 大份',             'calories' => 480, 'protein_g' => 6,  'fat_g' => 22, 'carbs_g' => 62],
                    ['name' => '薯泥',                  'calories' => 130, 'protein_g' => 2,  'fat_g' => 5,  'carbs_g' => 18, 'serving_unit' => '碗'],
                    ['name' => '玉米濃湯',              'calories' => 110, 'protein_g' => 2,  'fat_g' => 2,  'carbs_g' => 22, 'serving_unit' => '碗'],
                    // 甜點與飲品
                    ['name' => '葡式蛋撻',              'calories' => 175, 'protein_g' => 3,  'fat_g' => 9,  'carbs_g' => 21, 'serving_unit' => '個'],
                    ['name' => '巧克力布朗尼',          'calories' => 320, 'protein_g' => 4,  'fat_g' => 18, 'carbs_g' => 38, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '可口可樂 中杯',         'calories' => 150, 'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 38, 'serving_unit' => '杯'],
                    ['name' => '紅茶 中杯',             'calories' => 95,  'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 24, 'serving_unit' => '杯'],
                ],
            ],

            // ==========================================================
            // 摩斯漢堡 MOS Burger （22 項）
            // ==========================================================
            [
                'name' => '摩斯漢堡', 'slug' => 'mos-burger',
                'category' => 'fast_food', 'logo_emoji' => '🍔',
                'description' => '日系速食連鎖，以米漢堡與蔬菜量大聞名。',
                'default_opening_hours' => '每日 07:00 – 22:00',
                'osm_match_keywords' => ['摩斯', '摩斯漢堡', 'MOS Burger', 'MOS', 'モスバーガー'],
                'menu' => [
                    // 經典漢堡
                    ['name' => '摩斯漢堡',              'calories' => 360, 'protein_g' => 16, 'fat_g' => 18, 'carbs_g' => 35],
                    ['name' => '摩斯吉事堡',            'calories' => 410, 'protein_g' => 19, 'fat_g' => 22, 'carbs_g' => 36],
                    ['name' => '摩斯辣味吉事堡',        'calories' => 430, 'protein_g' => 20, 'fat_g' => 23, 'carbs_g' => 38],
                    ['name' => '摩斯雙牛吉事堡',        'calories' => 540, 'protein_g' => 28, 'fat_g' => 32, 'carbs_g' => 36],
                    ['name' => '摩斯起司堡',            'calories' => 420, 'protein_g' => 20, 'fat_g' => 22, 'carbs_g' => 38],
                    ['name' => '燒肉珍珠堡',            'calories' => 410, 'protein_g' => 16, 'fat_g' => 20, 'carbs_g' => 42],
                    ['name' => '海洋珍珠堡',            'calories' => 380, 'protein_g' => 14, 'fat_g' => 18, 'carbs_g' => 42],
                    ['name' => '咖哩熱狗堡',            'calories' => 420, 'protein_g' => 14, 'fat_g' => 22, 'carbs_g' => 42],
                    // 米漢堡
                    ['name' => '米漢堡 牛肉',           'calories' => 440, 'protein_g' => 18, 'fat_g' => 22, 'carbs_g' => 45],
                    ['name' => '米漢堡 雞肉',           'calories' => 380, 'protein_g' => 18, 'fat_g' => 14, 'carbs_g' => 45],
                    ['name' => '米漢堡 海鮮',           'calories' => 410, 'protein_g' => 16, 'fat_g' => 18, 'carbs_g' => 46],
                    ['name' => '蜜汁烤雞米漢堡',        'calories' => 420, 'protein_g' => 20, 'fat_g' => 16, 'carbs_g' => 48],
                    // 副餐
                    ['name' => '黃金薯',                'calories' => 260, 'protein_g' => 3,  'fat_g' => 14, 'carbs_g' => 32, 'serving_unit' => '份'],
                    ['name' => '雞塊 4 塊',             'calories' => 250, 'protein_g' => 14, 'fat_g' => 15, 'carbs_g' => 14, 'serving_unit' => '盒'],
                    ['name' => '雞塊 6 塊',             'calories' => 380, 'protein_g' => 21, 'fat_g' => 23, 'carbs_g' => 21, 'serving_unit' => '盒'],
                    ['name' => '起司薯條',              'calories' => 380, 'protein_g' => 6,  'fat_g' => 22, 'carbs_g' => 38, 'serving_unit' => '份'],
                    ['name' => '玉米濃湯',              'calories' => 130, 'protein_g' => 3,  'fat_g' => 4,  'carbs_g' => 22, 'serving_unit' => '碗'],
                    // 甜點與飲品
                    ['name' => '紅豆派',                'calories' => 240, 'protein_g' => 4,  'fat_g' => 10, 'carbs_g' => 32, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '芒果布丁',              'calories' => 160, 'protein_g' => 3,  'fat_g' => 4,  'carbs_g' => 28, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '紅茶 中杯',             'calories' => 85,  'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 22, 'serving_unit' => '杯'],
                    ['name' => '蜜茶 中杯',             'calories' => 130, 'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 32, 'serving_unit' => '杯'],
                    ['name' => '熱可可 中杯',           'calories' => 230, 'protein_g' => 7,  'fat_g' => 7,  'carbs_g' => 35, 'serving_unit' => '杯'],
                ],
            ],

            // ==========================================================
            // 星巴克 Starbucks （25 項）
            // ==========================================================
            [
                'name' => '星巴克', 'slug' => 'starbucks',
                'category' => 'drink', 'logo_emoji' => '☕',
                'description' => '美式咖啡連鎖。中杯為標準容量 360ml（12oz）。',
                'default_opening_hours' => '每日 07:00 – 22:00',
                'osm_match_keywords' => ['星巴克', 'Starbucks', 'STARBUCKS'],
                'menu' => [
                    // 咖啡
                    ['name' => '美式咖啡 中杯',          'calories' => 5,   'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 1,  'serving_unit' => '杯'],
                    ['name' => '美式咖啡 大杯',          'calories' => 10,  'protein_g' => 1,  'fat_g' => 0,  'carbs_g' => 2,  'serving_unit' => '杯'],
                    ['name' => '拿鐵 中杯（全脂）',      'calories' => 230, 'protein_g' => 12, 'fat_g' => 12, 'carbs_g' => 18, 'serving_unit' => '杯'],
                    ['name' => '拿鐵 中杯（脫脂）',      'calories' => 130, 'protein_g' => 12, 'fat_g' => 0.5,'carbs_g' => 18, 'serving_unit' => '杯'],
                    ['name' => '拿鐵 大杯',              'calories' => 290, 'protein_g' => 15, 'fat_g' => 15, 'carbs_g' => 22, 'serving_unit' => '杯'],
                    ['name' => '卡布奇諾 中杯',          'calories' => 130, 'protein_g' => 8,  'fat_g' => 6,  'carbs_g' => 11, 'serving_unit' => '杯'],
                    ['name' => '焦糖瑪奇朵 中杯',        'calories' => 290, 'protein_g' => 11, 'fat_g' => 11, 'carbs_g' => 35, 'serving_unit' => '杯'],
                    ['name' => '摩卡 中杯',              'calories' => 290, 'protein_g' => 11, 'fat_g' => 13, 'carbs_g' => 33, 'serving_unit' => '杯'],
                    ['name' => '香草拿鐵 中杯',          'calories' => 270, 'protein_g' => 11, 'fat_g' => 11, 'carbs_g' => 32, 'serving_unit' => '杯'],
                    ['name' => '榛果拿鐵 中杯',          'calories' => 280, 'protein_g' => 11, 'fat_g' => 11, 'carbs_g' => 33, 'serving_unit' => '杯'],
                    // 星冰樂
                    ['name' => '焦糖星冰樂 中杯',        'calories' => 360, 'protein_g' => 5,  'fat_g' => 14, 'carbs_g' => 56, 'serving_unit' => '杯'],
                    ['name' => '抹茶星冰樂 中杯',        'calories' => 350, 'protein_g' => 7,  'fat_g' => 13, 'carbs_g' => 53, 'serving_unit' => '杯'],
                    ['name' => '巧克力星冰樂 中杯',      'calories' => 380, 'protein_g' => 6,  'fat_g' => 16, 'carbs_g' => 56, 'serving_unit' => '杯'],
                    ['name' => '咖啡星冰樂 中杯',        'calories' => 230, 'protein_g' => 5,  'fat_g' => 9,  'carbs_g' => 35, 'serving_unit' => '杯'],
                    // 茶飲
                    ['name' => '抹茶拿鐵 中杯',          'calories' => 240, 'protein_g' => 11, 'fat_g' => 8,  'carbs_g' => 32, 'serving_unit' => '杯'],
                    ['name' => '伯爵紅茶拿鐵 中杯',      'calories' => 220, 'protein_g' => 9,  'fat_g' => 8,  'carbs_g' => 28, 'serving_unit' => '杯'],
                    // 食物
                    ['name' => '巧克力可頌',            'calories' => 380, 'protein_g' => 7,  'fat_g' => 22, 'carbs_g' => 38, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '原味可頌',              'calories' => 290, 'protein_g' => 6,  'fat_g' => 16, 'carbs_g' => 32, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '帕里尼三明治',          'calories' => 420, 'protein_g' => 18, 'fat_g' => 18, 'carbs_g' => 45, 'serving_unit' => '份', 'category' => 'snack'],
                    ['name' => '雞肉凱薩沙拉',          'calories' => 320, 'protein_g' => 24, 'fat_g' => 18, 'carbs_g' => 14, 'serving_unit' => '份', 'category' => 'snack'],
                    ['name' => '貝果',                  'calories' => 280, 'protein_g' => 10, 'fat_g' => 2,  'carbs_g' => 56, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '藍莓馬芬',              'calories' => 360, 'protein_g' => 6,  'fat_g' => 16, 'carbs_g' => 48, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '紐約起司蛋糕',          'calories' => 410, 'protein_g' => 7,  'fat_g' => 26, 'carbs_g' => 36, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '抹茶蛋糕',              'calories' => 320, 'protein_g' => 5,  'fat_g' => 18, 'carbs_g' => 36, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '巧克力布朗尼',          'calories' => 410, 'protein_g' => 5,  'fat_g' => 22, 'carbs_g' => 48, 'serving_unit' => '塊', 'category' => 'snack'],
                ],
            ],

            // ==========================================================
            // 85 度 C （22 項）
            // ==========================================================
            [
                'name' => '85度C', 'slug' => '85cafe',
                'category' => 'drink', 'logo_emoji' => '🍰',
                'description' => '台灣本土咖啡 + 烘焙連鎖。蛋糕、麵包、咖啡、茶飲一應俱全。',
                'default_opening_hours' => '每日 07:00 – 22:00',
                'osm_match_keywords' => ['85度C', '85度c', '85°C', '85度C咖啡', '85 度 C', '85cafe'],
                'menu' => [
                    // 咖啡
                    ['name' => '美式咖啡',              'calories' => 5,   'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 1,  'serving_unit' => '杯'],
                    ['name' => '拿鐵咖啡',              'calories' => 230, 'protein_g' => 12, 'fat_g' => 12, 'carbs_g' => 18, 'serving_unit' => '杯'],
                    ['name' => '卡布奇諾',              'calories' => 130, 'protein_g' => 8,  'fat_g' => 6,  'carbs_g' => 11, 'serving_unit' => '杯'],
                    ['name' => '焦糖瑪奇朵',            'calories' => 290, 'protein_g' => 11, 'fat_g' => 11, 'carbs_g' => 35, 'serving_unit' => '杯'],
                    ['name' => '摩卡咖啡',              'calories' => 290, 'protein_g' => 11, 'fat_g' => 13, 'carbs_g' => 33, 'serving_unit' => '杯'],
                    // 茶飲
                    ['name' => '鮮奶茶',                'calories' => 320, 'protein_g' => 9,  'fat_g' => 11, 'carbs_g' => 45, 'serving_unit' => '杯'],
                    ['name' => '紅茶拿鐵',              'calories' => 280, 'protein_g' => 8,  'fat_g' => 9,  'carbs_g' => 38, 'serving_unit' => '杯'],
                    ['name' => '抹茶拿鐵',              'calories' => 240, 'protein_g' => 10, 'fat_g' => 8,  'carbs_g' => 32, 'serving_unit' => '杯'],
                    ['name' => '冰沙',                  'calories' => 380, 'protein_g' => 4,  'fat_g' => 12, 'carbs_g' => 62, 'serving_unit' => '杯'],
                    // 蛋糕
                    ['name' => '蛋塔',                  'calories' => 220, 'protein_g' => 4,  'fat_g' => 12, 'carbs_g' => 25, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '黑森林蛋糕',            'calories' => 320, 'protein_g' => 5,  'fat_g' => 18, 'carbs_g' => 35, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '提拉米蘇',              'calories' => 290, 'protein_g' => 6,  'fat_g' => 18, 'carbs_g' => 28, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '厚奶磚 蛋糕',           'calories' => 340, 'protein_g' => 6,  'fat_g' => 20, 'carbs_g' => 32, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '草莓鮮奶酪',            'calories' => 180, 'protein_g' => 4,  'fat_g' => 8,  'carbs_g' => 22, 'serving_unit' => '杯', 'category' => 'snack'],
                    ['name' => '雙層巧克力蛋糕',        'calories' => 380, 'protein_g' => 5,  'fat_g' => 22, 'carbs_g' => 42, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '檸檬塔',                'calories' => 290, 'protein_g' => 4,  'fat_g' => 14, 'carbs_g' => 38, 'serving_unit' => '個', 'category' => 'snack'],
                    // 麵包
                    ['name' => '波羅麵包',              'calories' => 280, 'protein_g' => 6,  'fat_g' => 8,  'carbs_g' => 45, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '蔥花麵包',              'calories' => 240, 'protein_g' => 7,  'fat_g' => 8,  'carbs_g' => 38, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '巧克力可頌',            'calories' => 320, 'protein_g' => 5,  'fat_g' => 18, 'carbs_g' => 35, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '起司條麵包',            'calories' => 280, 'protein_g' => 8,  'fat_g' => 10, 'carbs_g' => 40, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '紅豆麵包',              'calories' => 260, 'protein_g' => 7,  'fat_g' => 5,  'carbs_g' => 48, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '熱狗麵包',              'calories' => 310, 'protein_g' => 10, 'fat_g' => 14, 'carbs_g' => 36, 'serving_unit' => '個', 'category' => 'snack'],
                ],
            ],

            // ==========================================================
            // 路易莎咖啡 Louisa （20 項）
            // ==========================================================
            [
                'name' => '路易莎', 'slug' => 'louisa',
                'category' => 'drink', 'logo_emoji' => '☕',
                'description' => '台灣本土平價咖啡連鎖，以單品咖啡與輕食為主。',
                'default_opening_hours' => '每日 07:00 – 22:00',
                'osm_match_keywords' => ['路易莎', 'Louisa', 'LOUISA', '路易莎咖啡'],
                'menu' => [
                    // 咖啡
                    ['name' => '美式咖啡 中杯',          'calories' => 5,   'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 1,  'serving_unit' => '杯'],
                    ['name' => '美式咖啡 大杯',          'calories' => 10,  'protein_g' => 1,  'fat_g' => 0,  'carbs_g' => 2,  'serving_unit' => '杯'],
                    ['name' => '拿鐵 中杯',              'calories' => 220, 'protein_g' => 12, 'fat_g' => 11, 'carbs_g' => 18, 'serving_unit' => '杯'],
                    ['name' => '拿鐵 大杯',              'calories' => 280, 'protein_g' => 15, 'fat_g' => 14, 'carbs_g' => 22, 'serving_unit' => '杯'],
                    ['name' => '卡布奇諾',              'calories' => 120, 'protein_g' => 8,  'fat_g' => 6,  'carbs_g' => 11, 'serving_unit' => '杯'],
                    ['name' => '焦糖拿鐵',              'calories' => 280, 'protein_g' => 10, 'fat_g' => 11, 'carbs_g' => 35, 'serving_unit' => '杯'],
                    ['name' => '榛果拿鐵',              'calories' => 270, 'protein_g' => 10, 'fat_g' => 11, 'carbs_g' => 32, 'serving_unit' => '杯'],
                    // 茶飲
                    ['name' => '抹茶拿鐵',              'calories' => 240, 'protein_g' => 10, 'fat_g' => 8,  'carbs_g' => 32, 'serving_unit' => '杯'],
                    ['name' => '伯爵奶茶',              'calories' => 280, 'protein_g' => 6,  'fat_g' => 10, 'carbs_g' => 38, 'serving_unit' => '杯'],
                    ['name' => '烏龍奶茶',              'calories' => 270, 'protein_g' => 6,  'fat_g' => 9,  'carbs_g' => 36, 'serving_unit' => '杯'],
                    // 食物
                    ['name' => '紐奧良雞腿堡',          'calories' => 480, 'protein_g' => 22, 'fat_g' => 22, 'carbs_g' => 48, 'serving_unit' => '個', 'category' => 'fast_food'],
                    ['name' => '燻雞蛋三明治',          'calories' => 320, 'protein_g' => 16, 'fat_g' => 12, 'carbs_g' => 36, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '蔬菜蛋三明治',          'calories' => 280, 'protein_g' => 12, 'fat_g' => 10, 'carbs_g' => 35, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '火腿起司貝果',          'calories' => 360, 'protein_g' => 16, 'fat_g' => 12, 'carbs_g' => 50, 'serving_unit' => '個', 'category' => 'snack'],
                    // 甜點與烘焙
                    ['name' => '布朗尼',                'calories' => 290, 'protein_g' => 4,  'fat_g' => 16, 'carbs_g' => 35, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '提拉米蘇',              'calories' => 280, 'protein_g' => 5,  'fat_g' => 18, 'carbs_g' => 26, 'serving_unit' => '塊', 'category' => 'snack'],
                    ['name' => '檸檬塔',                'calories' => 280, 'protein_g' => 4,  'fat_g' => 14, 'carbs_g' => 36, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '可頌',                  'calories' => 290, 'protein_g' => 6,  'fat_g' => 16, 'carbs_g' => 32, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '蘋果派',                'calories' => 270, 'protein_g' => 3,  'fat_g' => 14, 'carbs_g' => 36, 'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => '雞肉凱薩沙拉',          'calories' => 280, 'protein_g' => 22, 'fat_g' => 14, 'carbs_g' => 14, 'serving_unit' => '份', 'category' => 'snack'],
                ],
            ],

            // ==========================================================
            // 清心福全（手搖飲，標準大杯 700ml，全糖正常冰）（22 項）
            // ==========================================================
            [
                'name' => '清心福全', 'slug' => 'cingshin',
                'category' => 'drink', 'logo_emoji' => '🧋',
                'description' => '台灣最大手搖飲連鎖。標示為大杯 700ml、全糖正常冰，可視糖度調整熱量（半糖約 -25%、無糖約 -50%）。',
                'menu_confidence' => 'medium',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['清心福全', '清心', 'Ching Shin'],
                'menu' => [
                    // 純茶
                    ['name' => '紅茶 全糖',                  'calories' => 280, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 70,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '綠茶 全糖',                  'calories' => 280, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 70,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '青茶 無糖',                  'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '烏龍茶 無糖',                'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '四季春 全糖',                'calories' => 270, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 68,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 奶茶系列
                    ['name' => '紅茶拿鐵 全糖',              'calories' => 380, 'protein_g' => 6, 'fat_g' => 10,'carbs_g' => 65,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '紅茶拿鐵 半糖',              'calories' => 320, 'protein_g' => 6, 'fat_g' => 10,'carbs_g' => 50,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '珍珠奶茶 全糖',              'calories' => 580, 'protein_g' => 6, 'fat_g' => 12,'carbs_g' => 110, 'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '珍珠奶茶 半糖',              'calories' => 460, 'protein_g' => 6, 'fat_g' => 12,'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '珍珠奶茶 無糖',              'calories' => 320, 'protein_g' => 6, 'fat_g' => 12,'carbs_g' => 50,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '波霸奶茶 全糖',              'calories' => 620, 'protein_g' => 6, 'fat_g' => 12,'carbs_g' => 120, 'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '冬瓜奶茶 全糖',              'calories' => 420, 'protein_g' => 4, 'fat_g' => 8, 'carbs_g' => 78,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 鮮奶系列
                    ['name' => '鮮奶綠 全糖',                'calories' => 380, 'protein_g' => 9, 'fat_g' => 10,'carbs_g' => 60,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '鮮奶紅 全糖',                'calories' => 380, 'protein_g' => 9, 'fat_g' => 10,'carbs_g' => 60,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 水果茶
                    ['name' => '翡翠檸檬 全糖',              'calories' => 320, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '冬瓜檸檬 全糖',              'calories' => 320, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '百香綠 全糖',                'calories' => 350, 'protein_g' => 1, 'fat_g' => 0, 'carbs_g' => 88,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '冰淇淋紅茶',                 'calories' => 460, 'protein_g' => 4, 'fat_g' => 14,'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 多多系列
                    ['name' => '多多綠茶 全糖',              'calories' => 350, 'protein_g' => 2, 'fat_g' => 0, 'carbs_g' => 88,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '多多檸檬 全糖',              'calories' => 380, 'protein_g' => 2, 'fat_g' => 0, 'carbs_g' => 95,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 加料
                    ['name' => '仙草凍奶茶 全糖',            'calories' => 480, 'protein_g' => 6, 'fat_g' => 11,'carbs_g' => 92,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '布丁奶茶 全糖',              'calories' => 540, 'protein_g' => 8, 'fat_g' => 14,'carbs_g' => 96,  'serving_unit' => '杯', 'serving_size' => 700],
                ],
            ],

            // ==========================================================
            // 50嵐（22 項）
            // ==========================================================
            [
                'name' => '50嵐', 'slug' => '50lan',
                'category' => 'drink', 'logo_emoji' => '🧋',
                'description' => '台灣手搖飲指標品牌。標示為大杯 700ml、全糖正常冰。',
                'menu_confidence' => 'medium',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['50嵐', '50 嵐', '50Lan'],
                'menu' => [
                    // 經典奶茶
                    ['name' => '招牌奶茶 全糖',              'calories' => 460, 'protein_g' => 5, 'fat_g' => 10,'carbs_g' => 90,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '招牌奶茶 半糖',              'calories' => 360, 'protein_g' => 5, 'fat_g' => 10,'carbs_g' => 65,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '波霸奶茶 全糖',              'calories' => 620, 'protein_g' => 6, 'fat_g' => 12,'carbs_g' => 120, 'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '珍珠奶茶 全糖',              'calories' => 560, 'protein_g' => 6, 'fat_g' => 12,'carbs_g' => 105, 'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '咖啡凍奶茶',                 'calories' => 480, 'protein_g' => 6, 'fat_g' => 11,'carbs_g' => 92,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '布丁奶茶 全糖',              'calories' => 540, 'protein_g' => 8, 'fat_g' => 14,'carbs_g' => 96,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 純茶
                    ['name' => '茉莉綠茶 全糖',              'calories' => 280, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 70,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '茉莉綠茶 無糖',              'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '阿薩姆紅茶 全糖',            'calories' => 290, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 72,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '四季春青茶 無糖',            'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '鐵觀音 無糖',                'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '烏龍青茶 無糖',              'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    // 鮮奶系列
                    ['name' => '鮮奶綠 全糖',                'calories' => 380, 'protein_g' => 9, 'fat_g' => 10,'carbs_g' => 60,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '鐵觀音鮮奶 全糖',            'calories' => 380, 'protein_g' => 9, 'fat_g' => 10,'carbs_g' => 60,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 水果茶
                    ['name' => '檸檬紅茶 全糖',              'calories' => 320, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '梅子綠茶 全糖',              'calories' => 320, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '百香果綠 全糖',              'calories' => 340, 'protein_g' => 1, 'fat_g' => 0, 'carbs_g' => 84,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 多多系列
                    ['name' => '養樂多綠 全糖',              'calories' => 340, 'protein_g' => 2, 'fat_g' => 0, 'carbs_g' => 85,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '養樂多檸檬 全糖',            'calories' => 360, 'protein_g' => 2, 'fat_g' => 0, 'carbs_g' => 90,  'serving_unit' => '杯', 'serving_size' => 700],
                    // 冰沙
                    ['name' => '芒果冰沙',                   'calories' => 380, 'protein_g' => 1, 'fat_g' => 1, 'carbs_g' => 92,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '草莓冰沙',                   'calories' => 360, 'protein_g' => 1, 'fat_g' => 1, 'carbs_g' => 88,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '巧克力冰沙',                 'calories' => 480, 'protein_g' => 5, 'fat_g' => 14,'carbs_g' => 84,  'serving_unit' => '杯', 'serving_size' => 700],
                ],
            ],

            // ==========================================================
            // 八方雲集（20 項）
            // ==========================================================
            [
                'name' => '八方雲集', 'slug' => '8way',
                'category' => 'noodle', 'logo_emoji' => '🥟',
                'description' => '台灣鍋貼水餃連鎖。',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['八方雲集', '八方', '8way', 'Bafang'],
                'menu' => [
                    // 鍋貼
                    ['name' => '原味鍋貼 1 顆',         'calories' => 65,  'protein_g' => 2.5, 'fat_g' => 4,   'carbs_g' => 5,    'serving_unit' => '顆'],
                    ['name' => '原味鍋貼 10 顆',        'calories' => 650, 'protein_g' => 25,  'fat_g' => 40,  'carbs_g' => 50,   'serving_unit' => '盒'],
                    ['name' => '原味鍋貼 20 顆',        'calories' => 1300,'protein_g' => 50,  'fat_g' => 80,  'carbs_g' => 100,  'serving_unit' => '盒'],
                    ['name' => '玉米鍋貼 10 顆',        'calories' => 680, 'protein_g' => 25,  'fat_g' => 40,  'carbs_g' => 56,   'serving_unit' => '盒'],
                    ['name' => '辣味鍋貼 10 顆',        'calories' => 670, 'protein_g' => 25,  'fat_g' => 41,  'carbs_g' => 51,   'serving_unit' => '盒'],
                    ['name' => '咖哩鍋貼 10 顆',        'calories' => 680, 'protein_g' => 25,  'fat_g' => 40,  'carbs_g' => 54,   'serving_unit' => '盒'],
                    // 水餃
                    ['name' => '韭菜水餃 1 顆',         'calories' => 50,  'protein_g' => 2.5, 'fat_g' => 2,   'carbs_g' => 6,    'serving_unit' => '顆'],
                    ['name' => '韭菜水餃 10 顆',        'calories' => 500, 'protein_g' => 25,  'fat_g' => 20,  'carbs_g' => 60,   'serving_unit' => '盒'],
                    ['name' => '玉米水餃 10 顆',        'calories' => 530, 'protein_g' => 25,  'fat_g' => 20,  'carbs_g' => 66,   'serving_unit' => '盒'],
                    ['name' => '泡菜水餃 10 顆',        'calories' => 520, 'protein_g' => 25,  'fat_g' => 21,  'carbs_g' => 62,   'serving_unit' => '盒'],
                    // 麵食
                    ['name' => '蛋花酸辣湯麵',          'calories' => 580, 'protein_g' => 22,  'fat_g' => 14,  'carbs_g' => 88,   'serving_unit' => '碗'],
                    ['name' => '紅燒牛肉麵',            'calories' => 720, 'protein_g' => 38,  'fat_g' => 22,  'carbs_g' => 92,   'serving_unit' => '碗'],
                    ['name' => '招牌牛肉麵',            'calories' => 700, 'protein_g' => 35,  'fat_g' => 20,  'carbs_g' => 90,   'serving_unit' => '碗'],
                    // 湯品
                    ['name' => '酸辣湯 大碗',           'calories' => 200, 'protein_g' => 8,   'fat_g' => 8,   'carbs_g' => 24,   'serving_unit' => '碗'],
                    ['name' => '酸辣湯 小碗',           'calories' => 130, 'protein_g' => 5,   'fat_g' => 5,   'carbs_g' => 16,   'serving_unit' => '碗'],
                    ['name' => '玉米濃湯',              'calories' => 180, 'protein_g' => 4,   'fat_g' => 5,   'carbs_g' => 30,   'serving_unit' => '碗'],
                    ['name' => '貢丸湯',                'calories' => 130, 'protein_g' => 8,   'fat_g' => 7,   'carbs_g' => 8,    'serving_unit' => '碗'],
                    // 配菜與飲品
                    ['name' => '滷蛋',                  'calories' => 80,  'protein_g' => 7,   'fat_g' => 5,   'carbs_g' => 1,    'serving_unit' => '顆'],
                    ['name' => '滷豆乾',                'calories' => 90,  'protein_g' => 8,   'fat_g' => 5,   'carbs_g' => 3,    'serving_unit' => '塊'],
                    ['name' => '豆漿',                  'calories' => 150, 'protein_g' => 7,   'fat_g' => 5,   'carbs_g' => 18,   'serving_unit' => '杯'],
                ],
            ],

            // ==========================================================
            // 三商巧福（18 項）
            // ==========================================================
            [
                'name' => '三商巧福', 'slug' => 'sanshang',
                'category' => 'noodle', 'logo_emoji' => '🍜',
                'description' => '台灣牛肉麵連鎖。',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['三商巧福', '三商', 'Sanshang'],
                'menu' => [
                    // 牛肉麵
                    ['name' => '紅燒牛肉麵',            'calories' => 750, 'protein_g' => 40, 'fat_g' => 22, 'carbs_g' => 95,  'serving_unit' => '碗'],
                    ['name' => '半筋半肉牛肉麵',        'calories' => 800, 'protein_g' => 42, 'fat_g' => 28, 'carbs_g' => 95,  'serving_unit' => '碗'],
                    ['name' => '清燉牛肉麵',            'calories' => 680, 'protein_g' => 42, 'fat_g' => 18, 'carbs_g' => 88,  'serving_unit' => '碗'],
                    ['name' => '番茄牛肉麵',            'calories' => 720, 'protein_g' => 38, 'fat_g' => 20, 'carbs_g' => 92,  'serving_unit' => '碗'],
                    ['name' => '麻辣牛肉麵',            'calories' => 780, 'protein_g' => 40, 'fat_g' => 26, 'carbs_g' => 95,  'serving_unit' => '碗'],
                    ['name' => '蔥燒牛肉麵',            'calories' => 760, 'protein_g' => 40, 'fat_g' => 24, 'carbs_g' => 95,  'serving_unit' => '碗'],
                    ['name' => '牛筋牛肉麵',            'calories' => 800, 'protein_g' => 42, 'fat_g' => 28, 'carbs_g' => 92,  'serving_unit' => '碗'],
                    ['name' => '牛肉湯麵',              'calories' => 480, 'protein_g' => 22, 'fat_g' => 10, 'carbs_g' => 78,  'serving_unit' => '碗'],
                    // 飯類
                    ['name' => '牛肉燴飯',              'calories' => 720, 'protein_g' => 32, 'fat_g' => 18, 'carbs_g' => 105, 'serving_unit' => '份', 'category' => 'rice_box'],
                    ['name' => '牛筋飯',                'calories' => 740, 'protein_g' => 35, 'fat_g' => 22, 'carbs_g' => 102, 'serving_unit' => '份', 'category' => 'rice_box'],
                    ['name' => '滷肉飯',                'calories' => 580, 'protein_g' => 18, 'fat_g' => 22, 'carbs_g' => 78,  'serving_unit' => '碗', 'category' => 'rice_box'],
                    // 湯品
                    ['name' => '牛肉湯',                'calories' => 220, 'protein_g' => 22, 'fat_g' => 10, 'carbs_g' => 8,   'serving_unit' => '碗'],
                    ['name' => '半筋半肉湯',            'calories' => 280, 'protein_g' => 24, 'fat_g' => 14, 'carbs_g' => 6,   'serving_unit' => '碗'],
                    // 配菜
                    ['name' => '燙青菜',                'calories' => 60,  'protein_g' => 2,  'fat_g' => 3,  'carbs_g' => 6,   'serving_unit' => '份'],
                    ['name' => '滷蛋',                  'calories' => 80,  'protein_g' => 7,  'fat_g' => 5,  'carbs_g' => 1,   'serving_unit' => '顆'],
                    ['name' => '滷豆乾',                'calories' => 90,  'protein_g' => 8,  'fat_g' => 5,  'carbs_g' => 3,   'serving_unit' => '塊'],
                    ['name' => '海帶',                  'calories' => 25,  'protein_g' => 1,  'fat_g' => 0,  'carbs_g' => 5,   'serving_unit' => '份'],
                    ['name' => '滷味拼盤',              'calories' => 280, 'protein_g' => 18, 'fat_g' => 14, 'carbs_g' => 18,  'serving_unit' => '份'],
                ],
            ],

            // ==========================================================
            // 7-11
            // ==========================================================
            [
                'name' => '7-11', 'slug' => 'seven-eleven',
                'category' => 'convenience', 'logo_emoji' => '🏪',
                'description' => '全台最大便利商店連鎖，24 小時營業，熱食、御飯糰、便當、咖啡、零食一應俱全。',
                'default_opening_hours' => '24 小時營業',
                'osm_match_keywords' => ['7-11', '7-Eleven', 'Seven Eleven', '統一超商', '小七'],
                'menu' => [
                    ['name' => '茶葉蛋',                'calories' => 75,  'protein_g' => 7,  'fat_g' => 5,   'carbs_g' => 0.6, 'serving_unit' => '顆'],
                    ['name' => '御飯糰 鮪魚',           'calories' => 195, 'protein_g' => 6,  'fat_g' => 4,   'carbs_g' => 35,  'serving_unit' => '個'],
                    ['name' => '御飯糰 鮭魚',           'calories' => 210, 'protein_g' => 8,  'fat_g' => 5,   'carbs_g' => 33,  'serving_unit' => '個'],
                    ['name' => '御飯糰 燒肉',           'calories' => 220, 'protein_g' => 7,  'fat_g' => 6,   'carbs_g' => 35,  'serving_unit' => '個'],
                    ['name' => '大亨堡',                'calories' => 310, 'protein_g' => 12, 'fat_g' => 18,  'carbs_g' => 28,  'serving_unit' => '個'],
                    ['name' => '雞腿便當',              'calories' => 720, 'protein_g' => 32, 'fat_g' => 28,  'carbs_g' => 85,  'serving_unit' => '份', 'category' => 'rice_box'],
                    ['name' => '排骨便當',              'calories' => 760, 'protein_g' => 28, 'fat_g' => 32,  'carbs_g' => 88,  'serving_unit' => '份', 'category' => 'rice_box'],
                    ['name' => '義大利麵 起司白醬',     'calories' => 540, 'protein_g' => 18, 'fat_g' => 22,  'carbs_g' => 68,  'serving_unit' => '盒'],
                    ['name' => '關東煮 高麗菜捲',       'calories' => 60,  'protein_g' => 3,  'fat_g' => 1,   'carbs_g' => 10,  'serving_unit' => '個'],
                    ['name' => '關東煮 米血',           'calories' => 145, 'protein_g' => 4,  'fat_g' => 1,   'carbs_g' => 30,  'serving_unit' => '塊'],
                    ['name' => '關東煮 玉米',           'calories' => 80,  'protein_g' => 2,  'fat_g' => 1,   'carbs_g' => 18,  'serving_unit' => '塊'],
                    ['name' => 'CITY CAFE 美式 中杯',   'calories' => 5,   'protein_g' => 0,  'fat_g' => 0,   'carbs_g' => 1,   'serving_unit' => '杯'],
                    ['name' => 'CITY CAFE 拿鐵 中杯',   'calories' => 230, 'protein_g' => 12, 'fat_g' => 12,  'carbs_g' => 18,  'serving_unit' => '杯'],
                    ['name' => '茶碗蒸',                'calories' => 110, 'protein_g' => 7,  'fat_g' => 5,   'carbs_g' => 9,   'serving_unit' => '個'],
                ],
            ],

            // ==========================================================
            // 全家
            // ==========================================================
            [
                'name' => '全家', 'slug' => 'family-mart',
                'category' => 'convenience', 'logo_emoji' => '🏪',
                'description' => '台灣第二大便利商店連鎖，24 小時營業，特色為烤地瓜、Let\'s Café。',
                'default_opening_hours' => '24 小時營業',
                'osm_match_keywords' => ['全家', '全家便利商店', 'FamilyMart', 'Family Mart'],
                'menu' => [
                    ['name' => '茶葉蛋',                'calories' => 75,  'protein_g' => 7,  'fat_g' => 5,   'carbs_g' => 0.6, 'serving_unit' => '顆'],
                    ['name' => '御飯糰 鮪魚',           'calories' => 195, 'protein_g' => 6,  'fat_g' => 4,   'carbs_g' => 35,  'serving_unit' => '個'],
                    ['name' => '烤地瓜 一條',           'calories' => 220, 'protein_g' => 4,  'fat_g' => 0.5, 'carbs_g' => 50,  'serving_unit' => '條'],
                    ['name' => '日式炸雞便當',          'calories' => 740, 'protein_g' => 30, 'fat_g' => 30,  'carbs_g' => 85,  'serving_unit' => '份', 'category' => 'rice_box'],
                    ['name' => '香烤雞腿便當',          'calories' => 680, 'protein_g' => 32, 'fat_g' => 22,  'carbs_g' => 90,  'serving_unit' => '份', 'category' => 'rice_box'],
                    ['name' => '焗烤系列',              'calories' => 480, 'protein_g' => 18, 'fat_g' => 22,  'carbs_g' => 52,  'serving_unit' => '盒'],
                    ['name' => '蛋餅',                  'calories' => 280, 'protein_g' => 9,  'fat_g' => 12,  'carbs_g' => 35,  'serving_unit' => '個'],
                    ['name' => '三明治 火腿蛋',         'calories' => 320, 'protein_g' => 14, 'fat_g' => 15,  'carbs_g' => 32,  'serving_unit' => '個', 'category' => 'snack'],
                    ['name' => "Let's Café 美式 中杯", 'calories' => 5,   'protein_g' => 0,  'fat_g' => 0,   'carbs_g' => 1,   'serving_unit' => '杯'],
                    ['name' => "Let's Café 拿鐵 中杯", 'calories' => 220, 'protein_g' => 11, 'fat_g' => 11,  'carbs_g' => 18,  'serving_unit' => '杯'],
                    ['name' => '霜淇淋',                'calories' => 160, 'protein_g' => 3,  'fat_g' => 5,   'carbs_g' => 26,  'serving_unit' => '支'],
                    ['name' => '布丁',                  'calories' => 180, 'protein_g' => 4,  'fat_g' => 6,   'carbs_g' => 28,  'serving_unit' => '個'],
                ],
            ],

            // ==========================================================
            // 鬍鬚張
            // ==========================================================
            [
                'name' => '鬍鬚張', 'slug' => 'formosa-chang',
                'category' => 'rice_box', 'logo_emoji' => '🍚',
                'description' => '台灣魯肉飯名店連鎖，特色為香醇魯肉、雞肉飯。',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['鬍鬚張', 'Hu Hsu Chang', 'Formosa Chang'],
                'menu' => [
                    ['name' => '招牌魯肉飯 大碗',       'calories' => 650, 'protein_g' => 20, 'fat_g' => 26, 'carbs_g' => 80,  'serving_unit' => '碗'],
                    ['name' => '招牌魯肉飯 小碗',       'calories' => 450, 'protein_g' => 14, 'fat_g' => 18, 'carbs_g' => 56,  'serving_unit' => '碗'],
                    ['name' => '雞肉飯 大碗',           'calories' => 580, 'protein_g' => 25, 'fat_g' => 18, 'carbs_g' => 78,  'serving_unit' => '碗'],
                    ['name' => '雞肉飯 小碗',           'calories' => 400, 'protein_g' => 18, 'fat_g' => 12, 'carbs_g' => 55,  'serving_unit' => '碗'],
                    ['name' => '雞腿便當',              'calories' => 820, 'protein_g' => 38, 'fat_g' => 30, 'carbs_g' => 95,  'serving_unit' => '份'],
                    ['name' => '排骨便當',              'calories' => 880, 'protein_g' => 32, 'fat_g' => 38, 'carbs_g' => 95,  'serving_unit' => '份'],
                    ['name' => '香腸 一條',             'calories' => 220, 'protein_g' => 10, 'fat_g' => 17, 'carbs_g' => 6,   'serving_unit' => '條', 'category' => 'snack'],
                    ['name' => '燙青菜',                'calories' => 60,  'protein_g' => 2,  'fat_g' => 3,  'carbs_g' => 6,   'serving_unit' => '份'],
                    ['name' => '滷蛋',                  'calories' => 80,  'protein_g' => 7,  'fat_g' => 5,  'carbs_g' => 1,   'serving_unit' => '顆'],
                    ['name' => '貢丸湯',                'calories' => 130, 'protein_g' => 8,  'fat_g' => 7,  'carbs_g' => 8,   'serving_unit' => '碗'],
                    ['name' => '味噌湯',                'calories' => 50,  'protein_g' => 3,  'fat_g' => 2,  'carbs_g' => 5,   'serving_unit' => '碗'],
                ],
            ],

            // ==========================================================
            // 早安美芝城（早餐連鎖）
            // ==========================================================
            [
                'name' => '早安美芝城', 'slug' => 'mei-zhi-cheng',
                'category' => 'fast_food', 'logo_emoji' => '🍳',
                'description' => '台灣早餐連鎖，蛋餅、漢堡、奶茶為主。',
                'default_opening_hours' => '每日 06:00 – 13:00',
                'osm_match_keywords' => ['早安美芝城', '美芝城', 'Mei Zhi Cheng'],
                'menu' => [
                    ['name' => '原味蛋餅',              'calories' => 280, 'protein_g' => 9,  'fat_g' => 12, 'carbs_g' => 35,  'serving_unit' => '個'],
                    ['name' => '玉米蛋餅',              'calories' => 320, 'protein_g' => 10, 'fat_g' => 13, 'carbs_g' => 40,  'serving_unit' => '個'],
                    ['name' => '起司蛋餅',              'calories' => 350, 'protein_g' => 13, 'fat_g' => 18, 'carbs_g' => 36,  'serving_unit' => '個'],
                    ['name' => '培根蛋餅',              'calories' => 380, 'protein_g' => 14, 'fat_g' => 20, 'carbs_g' => 36,  'serving_unit' => '個'],
                    ['name' => '燻雞起司漢堡',          'calories' => 480, 'protein_g' => 22, 'fat_g' => 22, 'carbs_g' => 48,  'serving_unit' => '個'],
                    ['name' => '豬排堡',                'calories' => 520, 'protein_g' => 25, 'fat_g' => 24, 'carbs_g' => 50,  'serving_unit' => '個'],
                    ['name' => '鐵板麵 黑胡椒',         'calories' => 580, 'protein_g' => 18, 'fat_g' => 22, 'carbs_g' => 78,  'serving_unit' => '份'],
                    ['name' => '蘿蔔糕',                'calories' => 220, 'protein_g' => 4,  'fat_g' => 8,  'carbs_g' => 35,  'serving_unit' => '份'],
                    ['name' => '冰奶茶 中杯',           'calories' => 280, 'protein_g' => 4,  'fat_g' => 8,  'carbs_g' => 48,  'serving_unit' => '杯'],
                    ['name' => '紅茶 中杯',             'calories' => 140, 'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 35,  'serving_unit' => '杯'],
                ],
            ],

            // ==========================================================
            // 麥味登（早餐連鎖）
            // ==========================================================
            [
                'name' => '麥味登', 'slug' => 'mwd',
                'category' => 'fast_food', 'logo_emoji' => '🍳',
                'description' => '台灣早午餐連鎖，多元中西式餐點。',
                'default_opening_hours' => '每日 06:00 – 14:00',
                'osm_match_keywords' => ['麥味登', 'MWD'],
                'menu' => [
                    ['name' => '香雞蛋餅',              'calories' => 360, 'protein_g' => 16, 'fat_g' => 16, 'carbs_g' => 38,  'serving_unit' => '個'],
                    ['name' => '雙倍起司蛋餅',          'calories' => 420, 'protein_g' => 18, 'fat_g' => 22, 'carbs_g' => 38,  'serving_unit' => '個'],
                    ['name' => '燻雞堡',                'calories' => 480, 'protein_g' => 22, 'fat_g' => 22, 'carbs_g' => 46,  'serving_unit' => '個'],
                    ['name' => '黑胡椒鐵板麵',          'calories' => 600, 'protein_g' => 20, 'fat_g' => 24, 'carbs_g' => 78,  'serving_unit' => '份'],
                    ['name' => '蕃茄義大利麵',          'calories' => 540, 'protein_g' => 16, 'fat_g' => 18, 'carbs_g' => 80,  'serving_unit' => '份'],
                    ['name' => '美式炒蛋早餐',          'calories' => 480, 'protein_g' => 22, 'fat_g' => 26, 'carbs_g' => 38,  'serving_unit' => '份'],
                    ['name' => '法式吐司',              'calories' => 380, 'protein_g' => 12, 'fat_g' => 16, 'carbs_g' => 48,  'serving_unit' => '份'],
                    ['name' => '冰紅茶 中杯',           'calories' => 140, 'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 35,  'serving_unit' => '杯'],
                    ['name' => '冰奶茶 中杯',           'calories' => 280, 'protein_g' => 4,  'fat_g' => 8,  'carbs_g' => 48,  'serving_unit' => '杯'],
                ],
            ],

            // ==========================================================
            // 繼光香香雞
            // ==========================================================
            [
                'name' => '繼光香香雞', 'slug' => 'jl-fc',
                'category' => 'fast_food', 'logo_emoji' => '🍗',
                'description' => '台灣經典香香雞連鎖，雞肉小塊裹粉酥炸。',
                'default_opening_hours' => '每日 11:00 – 22:00',
                'osm_match_keywords' => ['繼光香香雞', '香香雞'],
                'menu' => [
                    ['name' => '香香雞 小份',           'calories' => 320, 'protein_g' => 22, 'fat_g' => 18, 'carbs_g' => 18,  'serving_unit' => '份'],
                    ['name' => '香香雞 中份',           'calories' => 480, 'protein_g' => 32, 'fat_g' => 26, 'carbs_g' => 26,  'serving_unit' => '份'],
                    ['name' => '香香雞 大份',           'calories' => 720, 'protein_g' => 48, 'fat_g' => 38, 'carbs_g' => 40,  'serving_unit' => '份'],
                    ['name' => '雞排',                  'calories' => 380, 'protein_g' => 26, 'fat_g' => 22, 'carbs_g' => 18,  'serving_unit' => '塊'],
                    ['name' => '辣雞排',                'calories' => 400, 'protein_g' => 26, 'fat_g' => 23, 'carbs_g' => 20,  'serving_unit' => '塊'],
                    ['name' => '黃金薯條',              'calories' => 320, 'protein_g' => 4,  'fat_g' => 16, 'carbs_g' => 42,  'serving_unit' => '份'],
                    ['name' => '甜不辣',                'calories' => 280, 'protein_g' => 12, 'fat_g' => 14, 'carbs_g' => 26,  'serving_unit' => '份'],
                    ['name' => '雞米花',                'calories' => 350, 'protein_g' => 18, 'fat_g' => 22, 'carbs_g' => 22,  'serving_unit' => '盒'],
                    ['name' => '香酥地瓜薯條',          'calories' => 360, 'protein_g' => 3,  'fat_g' => 18, 'carbs_g' => 48,  'serving_unit' => '份'],
                ],
            ],

            // ==========================================================
            // 必勝客
            // ==========================================================
            [
                'name' => '必勝客', 'slug' => 'pizza-hut',
                'category' => 'fast_food', 'logo_emoji' => '🍕',
                'description' => '美式披薩連鎖，特色為厚片披薩、義大利麵與雞翅。',
                'default_opening_hours' => '每日 11:00 – 22:00',
                'osm_match_keywords' => ['必勝客', 'Pizza Hut'],
                'menu' => [
                    ['name' => '夏威夷披薩 一片',       'calories' => 230, 'protein_g' => 10, 'fat_g' => 9,  'carbs_g' => 28,  'serving_unit' => '片'],
                    ['name' => '海鮮披薩 一片',         'calories' => 240, 'protein_g' => 12, 'fat_g' => 9,  'carbs_g' => 28,  'serving_unit' => '片'],
                    ['name' => '燻雞披薩 一片',         'calories' => 260, 'protein_g' => 13, 'fat_g' => 11, 'carbs_g' => 28,  'serving_unit' => '片'],
                    ['name' => '總匯披薩 一片',         'calories' => 280, 'protein_g' => 13, 'fat_g' => 13, 'carbs_g' => 28,  'serving_unit' => '片'],
                    ['name' => '起司披薩 一片',         'calories' => 250, 'protein_g' => 12, 'fat_g' => 11, 'carbs_g' => 28,  'serving_unit' => '片'],
                    ['name' => '九吋小披薩 整份',       'calories' => 1200,'protein_g' => 50, 'fat_g' => 50, 'carbs_g' => 140, 'serving_unit' => '份'],
                    ['name' => '雞翅 一隻',             'calories' => 130, 'protein_g' => 9,  'fat_g' => 10, 'carbs_g' => 1,   'serving_unit' => '隻'],
                    ['name' => '義大利麵 茄汁肉醬',     'calories' => 580, 'protein_g' => 22, 'fat_g' => 22, 'carbs_g' => 75,  'serving_unit' => '份'],
                    ['name' => '玉米濃湯',              'calories' => 130, 'protein_g' => 3,  'fat_g' => 4,  'carbs_g' => 22,  'serving_unit' => '碗'],
                    ['name' => '可樂 中杯',             'calories' => 150, 'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 38,  'serving_unit' => '杯'],
                ],
            ],

            // ==========================================================
            // CoCo 都可（手搖飲）
            // ==========================================================
            [
                'name' => 'CoCo 都可', 'slug' => 'coco',
                'category' => 'drink', 'logo_emoji' => '🧋',
                'description' => '台灣手搖飲連鎖，全球門市眾多。標示為大杯 700ml、全糖正常冰。',
                'menu_confidence' => 'medium',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['CoCo', '都可', 'CoCo 都可', 'Coco Tea'],
                'menu' => [
                    ['name' => '珍珠奶茶 全糖',          'calories' => 580, 'protein_g' => 6, 'fat_g' => 12, 'carbs_g' => 110, 'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '珍珠奶茶 半糖',          'calories' => 460, 'protein_g' => 6, 'fat_g' => 12, 'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '阿薩姆奶茶 全糖',        'calories' => 380, 'protein_g' => 5, 'fat_g' => 10, 'carbs_g' => 68,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '波霸鮮奶茶 全糖',        'calories' => 580, 'protein_g' => 9, 'fat_g' => 14, 'carbs_g' => 100, 'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '檸檬綠茶 全糖',          'calories' => 320, 'protein_g' => 0, 'fat_g' => 0,  'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '百香雙響砲 全糖',        'calories' => 360, 'protein_g' => 1, 'fat_g' => 0,  'carbs_g' => 90,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '奇蹟雙響砲 全糖',        'calories' => 380, 'protein_g' => 1, 'fat_g' => 0,  'carbs_g' => 95,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '茉莉綠茶 無糖',          'calories' => 5,   'protein_g' => 0, 'fat_g' => 0,  'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '冬瓜茶 全糖',            'calories' => 320, 'protein_g' => 0, 'fat_g' => 0,  'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                ],
            ],

            // ==========================================================
            // 一芳水果茶
            // ==========================================================
            [
                'name' => '一芳水果茶', 'slug' => 'yifang',
                'category' => 'drink', 'logo_emoji' => '🍵',
                'description' => '台灣水果茶連鎖，主打天然水果茶。標示為大杯 700ml、全糖正常冰。',
                'menu_confidence' => 'medium',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['一芳', '一芳水果茶', 'Yi Fang'],
                'menu' => [
                    ['name' => '招牌水果茶 全糖',        'calories' => 380, 'protein_g' => 1, 'fat_g' => 0, 'carbs_g' => 95,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '招牌水果茶 半糖',        'calories' => 280, 'protein_g' => 1, 'fat_g' => 0, 'carbs_g' => 70,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '芒果綠茶 全糖',          'calories' => 360, 'protein_g' => 1, 'fat_g' => 0, 'carbs_g' => 90,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '日月潭紅茶 全糖',        'calories' => 280, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 70,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '冬瓜檸檬 全糖',          'calories' => 320, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '甘蔗青茶 全糖',          'calories' => 300, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 75,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '老欉鳳梨茶 全糖',        'calories' => 340, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 85,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '花漾紅茶 無糖',          'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                ],
            ],

            // ==========================================================
            // 大苑子
            // ==========================================================
            [
                'name' => '大苑子', 'slug' => 'dayungs',
                'category' => 'drink', 'logo_emoji' => '🍊',
                'description' => '台灣鮮榨水果茶連鎖。標示為大杯 700ml、全糖正常冰。',
                'menu_confidence' => 'medium',
                'default_opening_hours' => '每日 10:00 – 22:00',
                'osm_match_keywords' => ['大苑子', "Dayung's", 'Dayungs'],
                'menu' => [
                    ['name' => '檸檬大苑 全糖',          'calories' => 340, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 85,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '芒果柳橙 全糖',          'calories' => 360, 'protein_g' => 1, 'fat_g' => 0, 'carbs_g' => 90,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '葡萄柚綠茶 全糖',        'calories' => 320, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 80,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '鳳梨百香 全糖',          'calories' => 350, 'protein_g' => 1, 'fat_g' => 0, 'carbs_g' => 88,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '草莓鮮奶 全糖',          'calories' => 420, 'protein_g' => 8, 'fat_g' => 11,'carbs_g' => 75,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '蘋果茶 全糖',            'calories' => 300, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 75,  'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '純茶 無糖',              'calories' => 5,   'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 1,   'serving_unit' => '杯', 'serving_size' => 700],
                    ['name' => '原味檸檬綠茶 半糖',      'calories' => 220, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 55,  'serving_unit' => '杯', 'serving_size' => 700],
                ],
            ],
        ];
    }
}
