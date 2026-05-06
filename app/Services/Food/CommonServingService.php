<?php

namespace App\Services\Food;

/**
 * 從食物名稱推測常見份量（克數）。
 *
 * 因為 TFND 全部是「每 100g 含量」，但一般人記飲食不會說「我吃了 130g 蔥抓餅」，
 * 而是「我吃了一張蔥抓餅」。這個 service 用 keyword 比對提供常見份量按鈕。
 *
 * 使用方法：
 *   $presets = CommonServingService::guess('蔥抓餅');
 *   // [['label' => '1 張', 'grams' => 90], ['label' => '半張', 'grams' => 45], ['label' => '100g', 'grams' => 100]]
 */
class CommonServingService
{
    /**
     * keyword → 常見份量列表
     * 每個食物都會自動加上「100g」當參考基準。
     *
     * 數值參考：衛福部「食物代換表」+ 一般市售份量
     */
    private const PRESETS = [
        // 主食類
        '飯'      => [['label' => '1 碗',     'grams' => 200], ['label' => '半碗', 'grams' => 100]],
        '便當'    => [['label' => '1 份',     'grams' => 450], ['label' => '半份', 'grams' => 225]],
        '麵'      => [['label' => '1 碗',     'grams' => 250], ['label' => '小碗', 'grams' => 150]],
        '麵包'    => [['label' => '1 片',     'grams' => 30],  ['label' => '1 個', 'grams' => 80]],
        '吐司'    => [['label' => '1 片',     'grams' => 30],  ['label' => '2 片', 'grams' => 60]],
        '饅頭'    => [['label' => '1 個',     'grams' => 80]],
        '包子'    => [['label' => '1 個',     'grams' => 100]],
        '水餃'    => [['label' => '5 顆',     'grams' => 100], ['label' => '10 顆', 'grams' => 200]],
        '小籠包'  => [['label' => '1 顆',     'grams' => 18],  ['label' => '8 顆', 'grams' => 144]],
        '蛋餅'    => [['label' => '1 份',     'grams' => 130]],
        '蔥抓餅'  => [['label' => '1 張',     'grams' => 90],  ['label' => '半張', 'grams' => 45]],
        '抓餅'    => [['label' => '1 張',     'grams' => 90]],
        '燒餅'    => [['label' => '1 個',     'grams' => 80]],
        '油條'    => [['label' => '1 根',     'grams' => 50]],
        '蘿蔔糕'  => [['label' => '1 塊',     'grams' => 60],  ['label' => '3 塊', 'grams' => 180]],
        '三明治'  => [['label' => '1 個',     'grams' => 150]],
        '漢堡'    => [['label' => '1 個',     'grams' => 200]],
        '披薩'    => [['label' => '1 片',     'grams' => 100]],

        // 蛋白質
        '雞胸'    => [['label' => '1 片',     'grams' => 120]],
        '雞腿'    => [['label' => '1 隻',     'grams' => 180]],
        '雞排'    => [['label' => '1 片',     'grams' => 150]],
        '排骨'    => [['label' => '1 塊',     'grams' => 100]],
        '滷蛋'    => [['label' => '1 顆',     'grams' => 55]],
        '茶葉蛋'  => [['label' => '1 顆',     'grams' => 55]],
        '雞蛋'    => [['label' => '1 顆',     'grams' => 55]],
        '全蛋'    => [['label' => '1 顆',     'grams' => 55]],
        '蛋'      => [['label' => '1 顆',     'grams' => 55]],
        '香腸'    => [['label' => '1 條',     'grams' => 50]],
        '熱狗'    => [['label' => '1 條',     'grams' => 40]],
        '貢丸'    => [['label' => '1 顆',     'grams' => 15],  ['label' => '5 顆', 'grams' => 75]],

        // 海鮮
        '鮭魚'    => [['label' => '1 片',     'grams' => 150]],
        '鯛魚'    => [['label' => '1 片',     'grams' => 150]],
        '蝦'      => [['label' => '5 隻',     'grams' => 80],  ['label' => '10 隻', 'grams' => 160]],

        // 豆製品
        '板豆腐'  => [['label' => '1 塊',     'grams' => 100], ['label' => '半盒', 'grams' => 150]],
        '嫩豆腐'  => [['label' => '半盒',     'grams' => 150]],
        '豆乾'    => [['label' => '1 片',     'grams' => 35],  ['label' => '5 片', 'grams' => 175]],
        '豆漿'    => [['label' => '1 杯',     'grams' => 240]],

        // 蔬果
        '蘋果'    => [['label' => '1 顆',     'grams' => 200]],
        '香蕉'    => [['label' => '1 根',     'grams' => 120]],
        '橘子'    => [['label' => '1 顆',     'grams' => 150]],
        '芭樂'    => [['label' => '1 顆',     'grams' => 200]],
        '葡萄'    => [['label' => '10 顆',    'grams' => 80]],
        '高麗菜'  => [['label' => '1 碗',     'grams' => 100]],
        '青菜'    => [['label' => '1 碗',     'grams' => 100]],

        // 飲料
        '牛奶'    => [['label' => '1 杯',     'grams' => 240], ['label' => '1 瓶', 'grams' => 350]],
        '咖啡'    => [['label' => '中杯',     'grams' => 350], ['label' => '大杯', 'grams' => 470]],
        '拿鐵'    => [['label' => '中杯',     'grams' => 350]],
        '茶'      => [['label' => '中杯',     'grams' => 500]],
        '奶茶'    => [['label' => '中杯',     'grams' => 500], ['label' => '大杯', 'grams' => 700]],
        '珍珠奶茶' => [['label' => '中杯',    'grams' => 500], ['label' => '大杯', 'grams' => 700]],
        '可樂'    => [['label' => '1 罐',     'grams' => 330], ['label' => '1 瓶', 'grams' => 600]],
        '果汁'    => [['label' => '1 杯',     'grams' => 300]],

        // 點心
        '冰淇淋'  => [['label' => '1 球',     'grams' => 60]],
        '蛋糕'    => [['label' => '1 片',     'grams' => 100]],
        '餅乾'    => [['label' => '1 片',     'grams' => 10],  ['label' => '5 片', 'grams' => 50]],
        '巧克力'  => [['label' => '1 片',     'grams' => 10],  ['label' => '1 條', 'grams' => 50]],
        '洋芋片'  => [['label' => '1 包',     'grams' => 50]],

        // 便當/小吃
        '滷肉飯'  => [['label' => '1 碗',     'grams' => 300]],
        '雞腿便當' => [['label' => '1 份',    'grams' => 500]],
        '排骨便當' => [['label' => '1 份',    'grams' => 500]],
        '牛肉麵'  => [['label' => '1 碗',     'grams' => 500]],
        '炒飯'    => [['label' => '1 盤',     'grams' => 300]],
        '炒麵'    => [['label' => '1 盤',     'grams' => 300]],
    ];

    /**
     * 給定食物名稱，回傳推薦的常見份量按鈕清單。
     * 永遠會包含「100g」當基準。
     *
     * @return array<int, array{label: string, grams: int}>
     */
    public static function guess(string $foodName): array
    {
        $name = trim($foodName);
        $found = [];

        // 用名稱關鍵字比對（取最長 match 優先）
        foreach (self::PRESETS as $kw => $presets) {
            if (str_contains($name, $kw)) {
                $found[] = ['kw' => $kw, 'presets' => $presets];
            }
        }
        // 排序：較長的 keyword 優先（更精確）
        usort($found, fn ($a, $b) => mb_strlen($b['kw']) <=> mb_strlen($a['kw']));

        $result = $found[0]['presets'] ?? [];

        // 補上 100g 基準（如果還沒有）
        $hasHundred = false;
        foreach ($result as $r) {
            if ($r['grams'] === 100) {
                $hasHundred = true;
                break;
            }
        }
        if (! $hasHundred) {
            $result[] = ['label' => '100g', 'grams' => 100];
        }

        return $result;
    }
}
