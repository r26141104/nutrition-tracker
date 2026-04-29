<?php

namespace App\Services\Analysis;

use App\Models\User;
use App\Services\Meal\MealService;
use App\Services\Nutrition\NutritionCalculatorService;

/**
 * 蛋白質分配分析。
 *
 * 統計今日早午晚點各別蛋白質、找出偏低餐別 / 集中過度的餐別，產出保守建議。
 */
class ProteinDistributionService
{
    /** 早餐蛋白質佔全天目標比例低於這個值就提醒 */
    private const BREAKFAST_LOW_RATIO = 0.15;

    /** 某一餐蛋白質佔全天攝取超過這個比例就提醒「集中過度」 */
    private const CONCENTRATION_RATIO = 0.6;

    private const MEAL_TYPE_LABELS = [
        'breakfast' => '早餐',
        'lunch'     => '午餐',
        'dinner'    => '晚餐',
        'snack'     => '點心',
    ];

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
        private readonly MealService $mealService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateProteinDistributionAnalysis(User $user): array
    {
        $profile = $user->profile;

        // 沒個人資料時，蛋白質目標未知，但仍可分析分配
        $proteinTarget = 0;
        if ($profile !== null && $profile->isComplete()) {
            $target = $this->calculator->generateNutritionTarget($profile);
            $proteinTarget = (int) $target['protein_g'];
        }

        $meals = $this->mealService->listOfDate($user->id);

        if ($meals->isEmpty()) {
            return [
                'has_enough_data' => false,
                'total_protein_g' => 0,
                'by_meal_type'    => $this->emptyByMealType(),
                'messages'        => ['今日尚無飲食紀錄，無法分析蛋白質分配。'],
            ];
        }

        $byMealType = $this->calculateProteinByMealType($meals);
        $total = round(array_sum($byMealType), 1);

        $messages = $this->generateMessages($byMealType, $total, $proteinTarget);

        return [
            'has_enough_data' => true,
            'total_protein_g' => $total,
            'by_meal_type'    => array_map(fn ($v) => round($v, 1), $byMealType),
            'messages'        => $messages,
        ];
    }

    /**
     * 統計各餐別蛋白質。
     *
     * @return array<string, float>
     */
    private function calculateProteinByMealType($meals): array
    {
        $by = $this->emptyByMealType();
        foreach ($meals as $meal) {
            $type = (string) $meal->meal_type;
            if (! isset($by[$type])) continue;
            foreach ($meal->items as $item) {
                $by[$type] += (float) $item->total_protein_g;
            }
        }
        return $by;
    }

    /**
     * @return array<string, float>
     */
    private function emptyByMealType(): array
    {
        return [
            'breakfast' => 0.0,
            'lunch'     => 0.0,
            'dinner'    => 0.0,
            'snack'     => 0.0,
        ];
    }

    /**
     * 產出保守措辭的訊息。
     *
     * @param  array<string, float>  $byMealType
     * @return array<int, string>
     */
    private function generateMessages(array $byMealType, float $total, int $proteinTarget): array
    {
        $messages = [];

        if ($total <= 0) {
            $messages[] = '今日尚未攝取蛋白質，建議至少於主餐加入蛋白質來源。';
            return $messages;
        }

        // 找出蛋白質最高的一餐
        $maxMeal = array_keys($byMealType, max($byMealType))[0] ?? null;
        if ($maxMeal !== null) {
            $maxRatio = $byMealType[$maxMeal] / $total;
            if ($maxRatio > self::CONCENTRATION_RATIO) {
                $label = self::MEAL_TYPE_LABELS[$maxMeal] ?? $maxMeal;
                $pct = (int) round($maxRatio * 100);
                $messages[] = "今日蛋白質主要集中在{$label}（佔 {$pct}%），分布較不平均。";
            }
        }

        // 早餐偏低（用全天目標來判斷，沒個人資料就跳過）
        if ($proteinTarget > 0) {
            $breakfastP = $byMealType['breakfast'];
            $breakfastRatio = $breakfastP / $proteinTarget;
            if ($breakfastRatio < self::BREAKFAST_LOW_RATIO) {
                $messages[] = '早餐蛋白質目前偏低，可考慮增加雞蛋、豆製品、乳製品或其他蛋白質來源。';
            }
        } else {
            // 沒目標時，用相對比例判斷
            if ($total > 0 && ($byMealType['breakfast'] / $total) < 0.10) {
                $messages[] = '早餐蛋白質目前較少，可考慮加入雞蛋或豆製品平均整日蛋白質。';
            }
        }

        if (empty($messages)) {
            $messages[] = '今日蛋白質分布看起來相對平均。';
        }

        return $messages;
    }
}
