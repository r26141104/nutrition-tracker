<?php

namespace App\Services\Analysis;

use App\Models\User;
use App\Services\Meal\MealService;
use App\Services\Nutrition\NutritionCalculatorService;
use Illuminate\Support\Collection;

/**
 * 飲食品質分數（0~100）。
 *
 * 6 個面向加權配分：
 *   蛋白質 25 / 熱量 25 / 脂肪 15 / 碳水 10 / 紀錄完整 15 / 點心飲料比例 10
 *
 * 分數只是輔助參考，不是健康診斷。
 */
class DietQualityScoreService
{
    public const LEVEL_INSUFFICIENT     = 'insufficient_data';
    public const LEVEL_NEEDS_ATTENTION  = 'needs_attention';
    public const LEVEL_FAIR             = 'fair';
    public const LEVEL_GOOD             = 'good';
    public const LEVEL_EXCELLENT        = 'excellent';

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
        private readonly MealService $mealService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateDietQualityScore(User $user): array
    {
        $profile = $user->profile;
        if ($profile === null || ! $profile->isComplete()) {
            return [
                'has_enough_data' => false,
                'score'           => 0,
                'level'           => self::LEVEL_INSUFFICIENT,
                'breakdown'       => $this->emptyBreakdown(),
                'feedback'        => ['請先完成個人資料設定，才能評估飲食品質分數。'],
            ];
        }

        $meals = $this->mealService->listOfDate($user->id);
        if ($meals->isEmpty()) {
            return [
                'has_enough_data' => false,
                'score'           => 0,
                'level'           => self::LEVEL_INSUFFICIENT,
                'breakdown'       => $this->emptyBreakdown(),
                'feedback'        => ['今日尚無飲食紀錄，無法評估品質分數。'],
            ];
        }

        $rawTarget = $this->calculator->generateNutritionTarget($profile);
        $target = [
            'calories'  => (int) $rawTarget['daily_calories'],
            'protein_g' => (int) $rawTarget['protein_g'],
            'fat_g'     => (int) $rawTarget['fat_g'],
            'carbs_g'   => (int) $rawTarget['carbs_g'],
        ];
        $consumed = $this->sumConsumed($meals);

        $breakdown = [
            'protein'           => $this->scoreProtein($consumed, $target),
            'calories'          => $this->scoreCalories($consumed, $target),
            'fat'               => $this->scoreFat($consumed, $target),
            'carbs'             => $this->scoreCarbs($consumed, $target),
            'meal_logging'      => $this->scoreMealLogging($meals),
            'snack_drink_ratio' => $this->scoreSnackAndDrinkRatio($meals),
        ];

        $score = array_sum($breakdown);
        $level = $this->levelFromScore($score);
        $feedback = $this->generateFeedback($breakdown, $consumed, $target);

        return [
            'has_enough_data' => true,
            'score'           => $score,
            'level'           => $level,
            'breakdown'       => $breakdown,
            'feedback'        => $feedback,
        ];
    }

    // ========================================================================
    // 6 個子項配分
    // ========================================================================

    /** 蛋白質：滿分 25。0.9~1.2 滿分；0.7~ 18；0.5~ 10；其它 5 */
    public function scoreProtein(array $consumed, array $target): int
    {
        if ($target['protein_g'] <= 0) return 0;
        $ratio = $consumed['protein_g'] / $target['protein_g'];
        if ($ratio >= 0.9 && $ratio <= 1.3) return 25;
        if ($ratio >= 0.7) return 18;
        if ($ratio >= 0.5) return 10;
        return 5;
    }

    /** 熱量：滿分 25。0.85~1.15 滿分；0.7~1.3 中等；其它低分 */
    public function scoreCalories(array $consumed, array $target): int
    {
        if ($target['calories'] <= 0) return 0;
        $ratio = $consumed['calories'] / $target['calories'];
        if ($ratio >= 0.85 && $ratio <= 1.15) return 25;
        if ($ratio >= 0.7  && $ratio <= 1.3)  return 15;
        if ($ratio >= 0.5  && $ratio <= 1.5)  return 8;
        return 3;
    }

    /** 脂肪：滿分 15。沒超滿分；超 1.2 倍 10；其它 3 */
    public function scoreFat(array $consumed, array $target): int
    {
        if ($target['fat_g'] <= 0) return 0;
        $ratio = $consumed['fat_g'] / $target['fat_g'];
        if ($ratio <= 1.0) return 15;
        if ($ratio <= 1.2) return 10;
        if ($ratio <= 1.5) return 5;
        return 2;
    }

    /** 碳水：滿分 10。沒嚴重超滿分 */
    public function scoreCarbs(array $consumed, array $target): int
    {
        if ($target['carbs_g'] <= 0) return 0;
        $ratio = $consumed['carbs_g'] / $target['carbs_g'];
        if ($ratio <= 1.0) return 10;
        if ($ratio <= 1.3) return 6;
        if ($ratio <= 1.6) return 3;
        return 1;
    }

    /** 紀錄完整：滿分 15。≥3 餐滿分 */
    public function scoreMealLogging(Collection $meals): int
    {
        $count = $meals->count();
        if ($count >= 3) return 15;
        if ($count >= 2) return 10;
        if ($count >= 1) return 5;
        return 0;
    }

    /** 點心+飲料 calories 比例：滿分 10。< 20% 滿分 */
    public function scoreSnackAndDrinkRatio(Collection $meals): int
    {
        $totalCal = 0;
        $junkCal  = 0;

        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                $cal = $item->total_calories;
                $totalCal += $cal;

                $isSnackMeal     = $meal->meal_type === 'snack';
                $isDrinkCategory = $item->food && $item->food->category === 'drink';
                $isSnackCategory = $item->food && $item->food->category === 'snack';

                if ($isSnackMeal || $isDrinkCategory || $isSnackCategory) {
                    $junkCal += $cal;
                }
            }
        }

        if ($totalCal <= 0) return 0;
        $ratio = $junkCal / $totalCal;

        if ($ratio < 0.20) return 10;
        if ($ratio < 0.30) return 7;
        if ($ratio < 0.40) return 4;
        return 1;
    }

    // ========================================================================
    // 內部工具
    // ========================================================================

    /**
     * @return array<string, int|float>
     */
    private function sumConsumed(Collection $meals): array
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

    private function levelFromScore(int $score): string
    {
        if ($score >= 85) return self::LEVEL_EXCELLENT;
        if ($score >= 70) return self::LEVEL_GOOD;
        if ($score >= 50) return self::LEVEL_FAIR;
        return self::LEVEL_NEEDS_ATTENTION;
    }

    /**
     * @param  array<string, int>  $breakdown
     * @return array<int, string>
     */
    private function generateFeedback(array $breakdown, array $consumed, array $target): array
    {
        $feedback = [];

        // 熱量
        if ($target['calories'] > 0) {
            $ratio = $consumed['calories'] / $target['calories'];
            if ($ratio >= 0.85 && $ratio <= 1.15) {
                $feedback[] = '今日熱量大致接近目標。';
            } elseif ($ratio > 1.15) {
                $feedback[] = '今日熱量略高於目標，下一餐可考慮較清淡的選擇。';
            } elseif ($ratio < 0.7) {
                $feedback[] = '今日熱量目前偏低，注意不要長期攝取不足。';
            }
        }

        // 蛋白質
        if ($target['protein_g'] > 0) {
            $ratio = $consumed['protein_g'] / $target['protein_g'];
            if ($ratio < 0.7) {
                $feedback[] = '蛋白質仍有進步空間，可考慮加入雞蛋、豆製品或雞胸肉。';
            }
        }

        // 脂肪
        if ($target['fat_g'] > 0 && $consumed['fat_g'] > $target['fat_g']) {
            $feedback[] = '脂肪攝取略高，下一餐可減少油炸食物。';
        }

        // 點心飲料比例
        if ($breakdown['snack_drink_ratio'] <= 4) {
            $feedback[] = '點心或飲料佔比偏高，可考慮以正餐取代部分零食。';
        }

        // 紀錄
        if ($breakdown['meal_logging'] < 10) {
            $feedback[] = '今日紀錄餐次較少，建議至少記錄主要 3 餐以提高分析準確度。';
        }

        if (empty($feedback)) {
            $feedback[] = '今日整體飲食看起來不錯，繼續保持。';
        }

        return $feedback;
    }

    /**
     * @return array<string, int>
     */
    private function emptyBreakdown(): array
    {
        return [
            'protein'           => 0,
            'calories'          => 0,
            'fat'               => 0,
            'carbs'             => 0,
            'meal_logging'      => 0,
            'snack_drink_ratio' => 0,
        ];
    }
}
