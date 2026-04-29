<?php

namespace Database\Seeders;

use App\Models\Food;
use Illuminate\Database\Seeder;

/**
 * 內建一批台灣常見外食。
 * 數值為估算（資料來源：包裝標示、業者公開資訊與 NTU 食品營養資料庫近似值），
 * 主要供 demo 與練習用途，不做為精確營養諮詢依據。
 */
class FoodSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->dataset() as $row) {
            // firstOrCreate 以 (name, brand) 為唯一鍵，重跑 seeder 不會重複建立
            Food::firstOrCreate(
                ['name' => $row['name'], 'brand' => $row['brand'] ?? null],
                array_merge($row, [
                    'is_system'          => true,
                    'created_by_user_id' => null,
                    // 修正四：系統內建食物 → 估算來源、可信度中等（標示牌或業者公開資料）
                    'source_type'        => 'system_estimate',
                    'confidence_level'   => 'medium',
                ]),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dataset(): array
    {
        return [
            // ====== rice_box（便當）======
            ['name' => '雞腿便當',       'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 850,  'protein_g' => 35, 'fat_g' => 30, 'carbs_g' => 95],
            ['name' => '排骨便當',       'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 900,  'protein_g' => 32, 'fat_g' => 38, 'carbs_g' => 95],
            ['name' => '焢肉便當',       'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 920,  'protein_g' => 28, 'fat_g' => 42, 'carbs_g' => 100],
            ['name' => '鯖魚便當',       'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 750,  'protein_g' => 38, 'fat_g' => 22, 'carbs_g' => 90],
            ['name' => '三寶便當',       'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 1100, 'protein_g' => 50, 'fat_g' => 45, 'carbs_g' => 110],
            ['name' => '雞排便當',       'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 950,  'protein_g' => 38, 'fat_g' => 35, 'carbs_g' => 100],
            ['name' => '蝦仁炒飯便當',   'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 800,  'protein_g' => 22, 'fat_g' => 25, 'carbs_g' => 110],
            ['name' => '燒肉便當',       'brand' => null, 'category' => 'rice_box',    'serving_unit' => '份', 'serving_size' => 1, 'calories' => 880,  'protein_g' => 32, 'fat_g' => 30, 'carbs_g' => 100],

            // ====== noodle（麵店 / 飯麵類）======
            ['name' => '牛肉麵',         'brand' => null, 'category' => 'noodle',      'serving_unit' => '碗', 'serving_size' => 1, 'calories' => 600,  'protein_g' => 35, 'fat_g' => 18, 'carbs_g' => 75],
            ['name' => '陽春麵',         'brand' => null, 'category' => 'noodle',      'serving_unit' => '碗', 'serving_size' => 1, 'calories' => 380,  'protein_g' => 12, 'fat_g' => 8,  'carbs_g' => 65],
            ['name' => '餛飩麵',         'brand' => null, 'category' => 'noodle',      'serving_unit' => '碗', 'serving_size' => 1, 'calories' => 550,  'protein_g' => 28, 'fat_g' => 18, 'carbs_g' => 70],
            ['name' => '滷肉飯',         'brand' => null, 'category' => 'noodle',      'serving_unit' => '碗', 'serving_size' => 1, 'calories' => 560,  'protein_g' => 18, 'fat_g' => 22, 'carbs_g' => 70],
            ['name' => '乾麵',           'brand' => null, 'category' => 'noodle',      'serving_unit' => '碗', 'serving_size' => 1, 'calories' => 480,  'protein_g' => 14, 'fat_g' => 18, 'carbs_g' => 70],
            ['name' => '米粉湯',         'brand' => null, 'category' => 'noodle',      'serving_unit' => '碗', 'serving_size' => 1, 'calories' => 350,  'protein_g' => 15, 'fat_g' => 8,  'carbs_g' => 55],
            ['name' => '擔仔麵',         'brand' => null, 'category' => 'noodle',      'serving_unit' => '碗', 'serving_size' => 1, 'calories' => 420,  'protein_g' => 14, 'fat_g' => 14, 'carbs_g' => 60],

            // ====== convenience（便利商店）======
            ['name' => '茶葉蛋',           'brand' => '7-11',  'category' => 'convenience', 'serving_unit' => '顆', 'serving_size' => 1, 'calories' => 75,  'protein_g' => 7,  'fat_g' => 5,   'carbs_g' => 0.6],
            ['name' => '御飯糰-鮪魚',      'brand' => '7-11',  'category' => 'convenience', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 195, 'protein_g' => 6,  'fat_g' => 4,   'carbs_g' => 35],
            ['name' => '御飯糰-鮭魚',      'brand' => '7-11',  'category' => 'convenience', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 210, 'protein_g' => 8,  'fat_g' => 5,   'carbs_g' => 33],
            ['name' => '火腿起司三明治',   'brand' => '全家',  'category' => 'convenience', 'serving_unit' => '份', 'serving_size' => 1, 'calories' => 290, 'protein_g' => 12, 'fat_g' => 12,  'carbs_g' => 32],
            ['name' => '大亨堡',           'brand' => null,    'category' => 'convenience', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 380, 'protein_g' => 14, 'fat_g' => 18,  'carbs_g' => 38],
            ['name' => '烤地瓜',           'brand' => null,    'category' => 'convenience', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 220, 'protein_g' => 3,  'fat_g' => 0.5, 'carbs_g' => 50],
            ['name' => '關東煮-米血糕',    'brand' => null,    'category' => 'convenience', 'serving_unit' => '串', 'serving_size' => 1, 'calories' => 90,  'protein_g' => 3,  'fat_g' => 1,   'carbs_g' => 18],
            ['name' => '關東煮-蘿蔔',      'brand' => null,    'category' => 'convenience', 'serving_unit' => '塊', 'serving_size' => 1, 'calories' => 25,  'protein_g' => 1,  'fat_g' => 0.1, 'carbs_g' => 5],
            ['name' => '關東煮-黑輪',      'brand' => null,    'category' => 'convenience', 'serving_unit' => '串', 'serving_size' => 1, 'calories' => 75,  'protein_g' => 4,  'fat_g' => 4,   'carbs_g' => 6],
            ['name' => '涼拌雞絲沙拉',     'brand' => '7-11',  'category' => 'convenience', 'serving_unit' => '盒', 'serving_size' => 1, 'calories' => 180, 'protein_g' => 25, 'fat_g' => 6,   'carbs_g' => 8],

            // ====== fast_food（速食）======
            ['name' => '大麥克',                 'brand' => '麥當勞',   'category' => 'fast_food', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 540, 'protein_g' => 25, 'fat_g' => 28, 'carbs_g' => 46],
            ['name' => '麥克雞塊 6 塊',          'brand' => '麥當勞',   'category' => 'fast_food', 'serving_unit' => '份', 'serving_size' => 1, 'calories' => 280, 'protein_g' => 14, 'fat_g' => 18, 'carbs_g' => 17],
            ['name' => '中薯',                   'brand' => '麥當勞',   'category' => 'fast_food', 'serving_unit' => '份', 'serving_size' => 1, 'calories' => 340, 'protein_g' => 4,  'fat_g' => 16, 'carbs_g' => 44],
            ['name' => '安格斯黑牛堡',           'brand' => '麥當勞',   'category' => 'fast_food', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 660, 'protein_g' => 35, 'fat_g' => 35, 'carbs_g' => 50],
            ['name' => '摩斯漢堡 蛋堡',          'brand' => '摩斯',     'category' => 'fast_food', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 290, 'protein_g' => 12, 'fat_g' => 13, 'carbs_g' => 30],
            ['name' => '摩斯薯條 中',            'brand' => '摩斯',     'category' => 'fast_food', 'serving_unit' => '份', 'serving_size' => 1, 'calories' => 320, 'protein_g' => 4,  'fat_g' => 14, 'carbs_g' => 42],
            ['name' => '原味炸雞 1 塊',          'brand' => '肯德基',   'category' => 'fast_food', 'serving_unit' => '塊', 'serving_size' => 1, 'calories' => 250, 'protein_g' => 18, 'fat_g' => 16, 'carbs_g' => 8],
            ['name' => 'Subway 6 吋雞肉三明治',  'brand' => 'Subway',   'category' => 'fast_food', 'serving_unit' => '個', 'serving_size' => 1, 'calories' => 320, 'protein_g' => 22, 'fat_g' => 6,  'carbs_g' => 47],

            // ====== drink（飲料）======
            ['name' => '全糖珍珠奶茶 700ml',     'brand' => '50 嵐',     'category' => 'drink', 'serving_unit' => '杯', 'serving_size' => 1, 'calories' => 540, 'protein_g' => 4,  'fat_g' => 8,  'carbs_g' => 110],
            ['name' => '半糖珍珠奶茶 700ml',     'brand' => '50 嵐',     'category' => 'drink', 'serving_unit' => '杯', 'serving_size' => 1, 'calories' => 420, 'protein_g' => 4,  'fat_g' => 8,  'carbs_g' => 80],
            ['name' => '紅茶 全糖 700ml',        'brand' => null,        'category' => 'drink', 'serving_unit' => '杯', 'serving_size' => 1, 'calories' => 240, 'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 60],
            ['name' => '拿鐵 大杯',              'brand' => '星巴克',    'category' => 'drink', 'serving_unit' => '杯', 'serving_size' => 1, 'calories' => 240, 'protein_g' => 13, 'fat_g' => 12, 'carbs_g' => 19],
            ['name' => '美式咖啡 大杯',          'brand' => '星巴克',    'category' => 'drink', 'serving_unit' => '杯', 'serving_size' => 1, 'calories' => 15,  'protein_g' => 1,  'fat_g' => 0,  'carbs_g' => 2],
            ['name' => '蜜豆奶',                 'brand' => '統一',      'category' => 'drink', 'serving_unit' => '瓶', 'serving_size' => 1, 'calories' => 240, 'protein_g' => 9,  'fat_g' => 6,  'carbs_g' => 38],
            ['name' => '玄米煎茶 600ml',         'brand' => '御茶園',    'category' => 'drink', 'serving_unit' => '瓶', 'serving_size' => 1, 'calories' => 0,   'protein_g' => 0,  'fat_g' => 0,  'carbs_g' => 0],

            // ====== snack（點心 / 小吃）======
            ['name' => '蛋餅',           'brand' => null, 'category' => 'snack', 'serving_unit' => '份', 'serving_size' => 1, 'calories' => 280, 'protein_g' => 10, 'fat_g' => 14, 'carbs_g' => 28],
            ['name' => '蘿蔔糕',         'brand' => null, 'category' => 'snack', 'serving_unit' => '份', 'serving_size' => 1, 'calories' => 320, 'protein_g' => 5,  'fat_g' => 14, 'carbs_g' => 42],
            ['name' => '鹽酥雞',         'brand' => null, 'category' => 'snack', 'serving_unit' => '份', 'serving_size' => 1, 'calories' => 480, 'protein_g' => 28, 'fat_g' => 28, 'carbs_g' => 25],
            ['name' => '雞排',           'brand' => null, 'category' => 'snack', 'serving_unit' => '片', 'serving_size' => 1, 'calories' => 420, 'protein_g' => 28, 'fat_g' => 25, 'carbs_g' => 18],
            ['name' => '烤香腸',         'brand' => null, 'category' => 'snack', 'serving_unit' => '支', 'serving_size' => 1, 'calories' => 180, 'protein_g' => 8,  'fat_g' => 14, 'carbs_g' => 4],
        ];
    }
}
