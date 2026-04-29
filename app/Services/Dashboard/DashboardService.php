<?php

namespace App\Services\Dashboard;

use App\Models\Meal;
use App\Models\User;
use App\Services\Health\HealthWarningService;
use App\Services\Meal\MealService;
use App\Services\Nutrition\NutritionCalculatorService;
use Illuminate\Support\Collection;

/**
 * Dashboard 「今日攝取總覽」聚合 service。
 *
 * 邏輯職責：
 *   - 取使用者個人資料（沒有 → profile_completed = false）
 *   - 委派 NutritionCalculatorService 算每日營養目標（不在這重寫公式）
 *   - 委派 MealService 拿今日 meals + items
 *   - 算 consumed / remaining / progress_percent / is_over
 *   - 串接 calculator 的 warnings + 自己的超標 warnings
 *   - 把 meals 整理成前端要用的扁平格式
 */
class DashboardService
{
    /** 餐別中文標籤（用來填 meal_type_label） */
    private const MEAL_TYPE_LABELS = [
        'breakfast' => '早餐',
        'lunch'     => '午餐',
        'dinner'    => '晚餐',
        'snack'     => '點心',
    ];

    public function __construct(
        private readonly NutritionCalculatorService $nutritionCalc,
        private readonly MealService $mealService,
        private readonly HealthWarningService $healthWarning,
    ) {}

    /**
     * 主入口：產出 today dashboard payload。
     *
     * @return array<string, mixed>
     */
    public function getTodayDashboard(User $user, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();

        $profile = $user->profile;
        $isComplete = $profile !== null && $profile->isComplete();

        // === Case 1：個人資料未完成 → 不算目標、不出 warnings ===
        if (! $isComplete) {
            return [
                'date'              => $date,
                'profile_completed' => false,
                'nutrition_target'  => null,
                'consumed'          => $this->zeroNutrients(),
                'remaining'         => $this->zeroNutrients(),
                'progress_percent'  => $this->zeroPercent(),
                'is_over'           => $this->allFalse(),
                'warnings'          => [],
                'today_meals'       => [],
            ];
        }

        // === Case 2：個人資料完整 → 全套計算 ===

        // 1) 每日營養目標（公式都在 NutritionCalculatorService 裡）
        $targetRaw = $this->nutritionCalc->generateNutritionTarget($profile);
        $target = [
            'target_weight_kg' => $targetRaw['target_weight_kg'],
            'bmr'              => $targetRaw['bmr'],
            'tdee'             => $targetRaw['tdee'],
            'calories'         => $targetRaw['daily_calories'],
            'protein_g'        => $targetRaw['protein_g'],
            'fat_g'            => $targetRaw['fat_g'],
            'carbs_g'          => $targetRaw['carbs_g'],
        ];

        // 2) 今日 meals + 攝取量
        $meals = $this->mealService->listOfDate($user->id, $date);
        $consumed = $this->sumConsumed($meals);

        // 3) remaining（允許負數，表示已超過）
        $remaining = [
            'calories'  => $target['calories']  - $consumed['calories'],
            'protein_g' => $this->round1($target['protein_g'] - $consumed['protein_g']),
            'fat_g'     => $this->round1($target['fat_g']     - $consumed['fat_g']),
            'carbs_g'   => $this->round1($target['carbs_g']   - $consumed['carbs_g']),
        ];

        // 4) progress_percent（避免除以 0）
        $progress = [
            'calories'  => $this->safePercent($consumed['calories'],  $target['calories']),
            'protein_g' => $this->safePercent($consumed['protein_g'], $target['protein_g']),
            'fat_g'     => $this->safePercent($consumed['fat_g'],     $target['fat_g']),
            'carbs_g'   => $this->safePercent($consumed['carbs_g'],   $target['carbs_g']),
        ];

        // 5) is_over
        $isOver = [
            'calories'  => $consumed['calories']  > $target['calories'],
            'protein_g' => $consumed['protein_g'] > $target['protein_g'],
            'fat_g'     => $consumed['fat_g']     > $target['fat_g'],
            'carbs_g'   => $consumed['carbs_g']   > $target['carbs_g'],
        ];

        // 6) warnings：交給 HealthWarningService 統一產生（結構化 {type, category, message}）
        $latestBodyRecord = $user->bodyRecords()
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->first();
        $warnings = $this->healthWarning->generateWarnings(
            $profile,
            $target,
            $consumed,
            $isOver,
            $latestBodyRecord,
        );

        // 7) today_meals 扁平化
        $todayMeals = $this->formatMeals($meals);

        return [
            'date'              => $date,
            'profile_completed' => true,
            'nutrition_target'  => $target,
            'consumed'          => $consumed,
            'remaining'         => $remaining,
            'progress_percent'  => $progress,
            'is_over'           => $isOver,
            'warnings'          => $warnings,
            'today_meals'       => $todayMeals,
        ];
    }

    // ============================================================
    // 內部工具
    // ============================================================

    /**
     * 把所有 meal_items 的 total_* 累加。
     *
     * @param  Collection<int, Meal>  $meals
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
            'protein_g' => $this->round1($p),
            'fat_g'     => $this->round1($f),
            'carbs_g'   => $this->round1($c),
        ];
    }

    /**
     * 把 meals 轉成前端要的扁平格式（含 meal_type_label / 整餐 totals / items 簡化）。
     *
     * @param  Collection<int, Meal>  $meals
     * @return array<int, array<string, mixed>>
     */
    private function formatMeals(Collection $meals): array
    {
        return $meals->map(function (Meal $meal): array {
            return [
                'id'              => $meal->id,
                'meal_date'       => $meal->eaten_at?->toDateString(),
                'eaten_at'        => $meal->eaten_at?->toIso8601String(),
                'meal_type'       => $meal->meal_type,
                'meal_type_label' => self::MEAL_TYPE_LABELS[$meal->meal_type] ?? $meal->meal_type,
                'total_calories'  => (int) $meal->items->sum->total_calories,
                'total_protein_g' => $this->round1((float) $meal->items->sum->total_protein_g),
                'total_fat_g'     => $this->round1((float) $meal->items->sum->total_fat_g),
                'total_carbs_g'   => $this->round1((float) $meal->items->sum->total_carbs_g),
                'items'           => $meal->items->map(function ($item): array {
                    return [
                        'id'        => $item->id,
                        // 食物被刪 → fallback 文字
                        'food_name' => $item->food?->name ?? '（已刪除的食物）',
                        'quantity'  => (float) $item->quantity,
                        // ↓ 這裡是「該 item 累計後的值」(snapshot × quantity)
                        'calories'  => $item->total_calories,
                        'protein_g' => $this->round1((float) $item->total_protein_g),
                        'fat_g'     => $this->round1((float) $item->total_fat_g),
                        'carbs_g'   => $this->round1((float) $item->total_carbs_g),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * 安全百分比：分母 0 / null 直接回 0，不丟例外。
     */
    private function safePercent(float|int $consumed, float|int|null $target): float
    {
        if ($target === null || $target <= 0) {
            return 0.0;
        }
        return round($consumed / $target * 100, 1);
    }

    private function round1(float $v): float
    {
        return round($v, 1);
    }

    /**
     * @return array<string, int|float>
     */
    private function zeroNutrients(): array
    {
        return [
            'calories'  => 0,
            'protein_g' => 0,
            'fat_g'     => 0,
            'carbs_g'   => 0,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function zeroPercent(): array
    {
        return [
            'calories'  => 0.0,
            'protein_g' => 0.0,
            'fat_g'     => 0.0,
            'carbs_g'   => 0.0,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function allFalse(): array
    {
        return [
            'calories'  => false,
            'protein_g' => false,
            'fat_g'     => false,
            'carbs_g'   => false,
        ];
    }
}
