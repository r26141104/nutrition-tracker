<?php

namespace App\Services\Analysis;

use App\Models\User;
use App\Services\Meal\MealService;
use App\Services\Nutrition\NutritionCalculatorService;

/**
 * 今日營養缺口分析。
 *
 * 計算 target / consumed / gap，找出最缺什麼、最需控制什麼，
 * 產出 2~3 條保守建議文字。
 */
class NutritionGapService
{
    private const PROTEIN_LOW_RATIO    = 0.7;
    private const FAT_NEAR_RATIO       = 0.9;
    private const CALORIES_LOW_THRESHOLD = 300;

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
        private readonly MealService $mealService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateNutritionGapAnalysis(User $user): array
    {
        $profile = $user->profile;
        if ($profile === null || ! $profile->isComplete()) {
            return [
                'has_enough_data' => false,
                'message'         => '請先完成個人資料設定，才能分析營養缺口。',
            ];
        }

        $rawTarget = $this->calculator->generateNutritionTarget($profile);
        $target = [
            'calories'  => (int) $rawTarget['daily_calories'],
            'protein_g' => (int) $rawTarget['protein_g'],
            'fat_g'     => (int) $rawTarget['fat_g'],
            'carbs_g'   => (int) $rawTarget['carbs_g'],
        ];

        $meals = $this->mealService->listOfDate($user->id);
        $consumed = $this->sumConsumed($meals);

        $gap = [
            'calories'  => $target['calories']  - $consumed['calories'],
            'protein_g' => round($target['protein_g'] - $consumed['protein_g'], 1),
            'fat_g'     => round($target['fat_g']     - $consumed['fat_g'],     1),
            'carbs_g'   => round($target['carbs_g']   - $consumed['carbs_g'],   1),
        ];

        $mainDeficit = $this->detectMainDeficit($target, $consumed);
        $mainExcess  = $this->detectMainExcess($target, $consumed);
        $messages    = $this->generateGapMessages($target, $consumed, $gap, $mainDeficit, $mainExcess);

        return [
            'has_enough_data' => true,
            'target'          => $target,
            'consumed'        => $consumed,
            'gap'             => $gap,
            'main_deficit'    => $mainDeficit,
            'main_excess'     => $mainExcess,
            'messages'        => $messages,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function sumConsumed($meals): array
    {
        $cal = 0; $p = 0.0; $f = 0.0; $c = 0.0;
        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                $cal += $item->total_calories;
                $p   += (float) $item->total_protein_g;
                $f   += (float) $item->total_fat_g;
                $c   += (float) $item->total_carbs_g;
            }
        }
        return [
            'calories'  => $cal,
            'protein_g' => round($p, 1),
            'fat_g'     => round($f, 1),
            'carbs_g'   => round($c, 1),
        ];
    }

    /**
     * 判斷今日最缺的營養。
     */
    private function detectMainDeficit(array $target, array $consumed): ?string
    {
        if ($target['protein_g'] > 0 && ($consumed['protein_g'] / $target['protein_g']) < self::PROTEIN_LOW_RATIO) {
            return 'protein';
        }
        return null;
    }

    /**
     * 判斷今日最需要控制的營養（脂肪 > 碳水 > 熱量）。
     */
    private function detectMainExcess(array $target, array $consumed): ?string
    {
        if ($target['fat_g'] > 0 && $consumed['fat_g'] > $target['fat_g']) {
            return 'fat';
        }
        if ($target['carbs_g'] > 0 && $consumed['carbs_g'] > $target['carbs_g']) {
            return 'carbs';
        }
        if ($target['calories'] > 0 && $consumed['calories'] > $target['calories']) {
            return 'calories';
        }
        return null;
    }

    /**
     * 產生 2~3 條保守措辭的訊息。
     *
     * @return array<int, string>
     */
    private function generateGapMessages(array $target, array $consumed, array $gap, ?string $mainDeficit, ?string $mainExcess): array
    {
        $messages = [];

        // 蛋白質缺口
        if ($mainDeficit === 'protein') {
            $messages[] = "今日蛋白質距離目標仍有約 {$gap['protein_g']} g 差距，可考慮補充蛋白質來源。";
        }

        // 脂肪超標
        if ($mainExcess === 'fat') {
            $over = round($consumed['fat_g'] - $target['fat_g'], 1);
            $messages[] = "今日脂肪攝取已略高於目標約 {$over} g，下一餐可考慮減少油炸或高油脂食物。";
        } elseif ($target['fat_g'] > 0 && ($consumed['fat_g'] / $target['fat_g']) >= self::FAT_NEAR_RATIO) {
            $messages[] = '今日脂肪已接近目標上限，下一餐可優先選擇低脂高蛋白食物。';
        }

        // 碳水超標
        if ($mainExcess === 'carbs') {
            $messages[] = '今日碳水攝取目前略高於目標，下一餐可考慮減少精緻澱粉或含糖飲料。';
        }

        // 熱量超標（沒被脂肪/碳水捕捉到才提）
        if ($mainExcess === 'calories') {
            $messages[] = '今日熱量已略高於目標，明天可考慮選擇較清淡的餐點；單日波動屬正常。';
        }

        // 剩餘熱量不多
        if ($gap['calories'] > 0 && $gap['calories'] < self::CALORIES_LOW_THRESHOLD) {
            $messages[] = "今日剩餘熱量約 {$gap['calories']} kcal，可選擇熱量較低的食物。";
        }

        // 沒缺也沒超
        if (empty($messages)) {
            $messages[] = '今日營養目前接近目標，繼續維持。';
        }

        return $messages;
    }
}
