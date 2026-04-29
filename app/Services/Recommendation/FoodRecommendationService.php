<?php

namespace App\Services\Recommendation;

use App\Models\Food;
use App\Models\User;
use App\Services\Meal\MealService;
use App\Services\Nutrition\NutritionCalculatorService;
use Illuminate\Support\Collection;

/**
 * 簡單餐點建議 service。
 *
 * 設計原則（嚴格遵守）：
 *   - 只能從 foods 資料表中推薦已存在的食物，絕不憑空產生
 *   - 推薦食物必須是「使用者可見」（系統食物 or 自己建立的自訂食物）
 *   - 不做醫療診斷
 *   - 不保證使用者一定會減重或增肌
 *   - 排除使用者今日已吃過的食物（避免重複推薦）
 */
class FoodRecommendationService
{
    /** 蛋白質低於目標的這個比例就觸發「蛋白質補充」group */
    private const PROTEIN_LOW_RATIO = 0.7;

    /** 剩餘熱量低於這個值就觸發「低熱量選擇」group */
    private const REMAINING_CAL_LOW = 300;

    /** 脂肪達到目標的這個比例就觸發「低脂選擇」group */
    private const FAT_NEAR_RATIO = 0.9;

    /** 每個 group 推薦幾筆 */
    private const FOODS_PER_GROUP = 5;

    /** 通用 disclaimer */
    private const NOTES = [
        '餐點建議僅根據目前資料庫與今日紀錄估算。',
        '實際選擇仍需依照個人飽足感、身體狀況與飲食習慣調整。',
        '若有特殊健康狀況或飲食限制，建議諮詢專業營養師。',
    ];

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
        private readonly MealService $mealService,
    ) {}

    /**
     * 主入口：產出完整餐點建議。
     *
     * @return array<string, mixed>
     */
    public function generateFoodRecommendations(User $user): array
    {
        $profile = $user->profile;

        // 沒個人資料 → 沒辦法判斷剩餘額度
        if ($profile === null || ! $profile->isComplete()) {
            return [
                'remaining'             => null,
                'recommendation_groups' => [],
                'notes'                 => [
                    '請先完成個人資料設定，才能根據今日剩餘營養額度推薦食物。',
                ],
            ];
        }

        // 1) 算今日剩餘營養
        $nutrition = $this->getRemainingNutrition($user);
        $target    = $nutrition['target'];
        $consumed  = $nutrition['consumed'];
        $remaining = $nutrition['remaining'];

        // 2) 找出今日已吃過的 food_id（要排除掉）
        $eatenFoodIds = $this->getTodayEatenFoodIds($user);

        // 3) 動態組合 groups
        $groups = [];

        // === 蛋白質補充建議 ===
        if ($target['protein_g'] > 0
            && ($consumed['protein_g'] / $target['protein_g']) < self::PROTEIN_LOW_RATIO
        ) {
            $groups[] = [
                'category' => 'high_protein',
                'title'    => '蛋白質補充建議',
                'reason'   => '今日蛋白質攝取偏低，可以優先選擇蛋白質較高的食物。',
                'foods'    => $this->recommendHighProteinFoods($user, $eatenFoodIds, self::FOODS_PER_GROUP),
            ];
        }

        // === 低熱量選擇 ===
        if ($remaining['calories'] > 0 && $remaining['calories'] < self::REMAINING_CAL_LOW) {
            $groups[] = [
                'category' => 'low_calorie',
                'title'    => '低熱量選擇',
                'reason'   => "今日剩餘熱量約 {$remaining['calories']} kcal，可以選擇熱量較低的食物。",
                'foods'    => $this->recommendLowCalorieFoods($user, $eatenFoodIds, self::FOODS_PER_GROUP, (int) $remaining['calories']),
            ];
        }

        // === 低脂選擇 ===
        if ($target['fat_g'] > 0
            && ($consumed['fat_g'] / $target['fat_g']) >= self::FAT_NEAR_RATIO
        ) {
            $groups[] = [
                'category' => 'low_fat',
                'title'    => '低脂選擇',
                'reason'   => '今日脂肪攝取已接近或超過目標，建議選擇脂肪較低的食物。',
                'foods'    => $this->recommendLowFatFoods($user, $eatenFoodIds, self::FOODS_PER_GROUP),
            ];
        }

        // === 依目標推薦（一律加） ===
        $goalType = (string) $profile->goal_type;
        $groups[] = [
            'category' => 'by_goal',
            'title'    => $this->goalGroupTitle($goalType),
            'reason'   => $this->goalGroupReason($goalType),
            'foods'    => $this->recommendByGoalType($user, $goalType, $eatenFoodIds, self::FOODS_PER_GROUP),
        ];

        return [
            'remaining'             => $remaining,
            'recommendation_groups' => $groups,
            'notes'                 => self::NOTES,
        ];
    }

    /**
     * 取得今日剩餘營養（target / consumed / remaining）。
     *
     * @return array{target: array<string,int>, consumed: array<string,int|float>, remaining: array<string,int|float>}
     */
    public function getRemainingNutrition(User $user): array
    {
        $profile = $user->profile;
        $targetRaw = $this->calculator->generateNutritionTarget($profile);
        $target = [
            'calories'  => (int) $targetRaw['daily_calories'],
            'protein_g' => (int) $targetRaw['protein_g'],
            'fat_g'     => (int) $targetRaw['fat_g'],
            'carbs_g'   => (int) $targetRaw['carbs_g'],
        ];

        $meals = $this->mealService->listOfDate($user->id);

        $consumed = [
            'calories'  => 0,
            'protein_g' => 0.0,
            'fat_g'     => 0.0,
            'carbs_g'   => 0.0,
        ];
        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                $consumed['calories']  += $item->total_calories;
                $consumed['protein_g'] += (float) $item->total_protein_g;
                $consumed['fat_g']     += (float) $item->total_fat_g;
                $consumed['carbs_g']   += (float) $item->total_carbs_g;
            }
        }

        // round 浮點欄位
        $consumed['protein_g'] = round($consumed['protein_g'], 1);
        $consumed['fat_g']     = round($consumed['fat_g'], 1);
        $consumed['carbs_g']   = round($consumed['carbs_g'], 1);

        $remaining = [
            'calories'  => $target['calories']  - $consumed['calories'],
            'protein_g' => round($target['protein_g'] - $consumed['protein_g'], 1),
            'fat_g'     => round($target['fat_g']     - $consumed['fat_g'], 1),
            'carbs_g'   => round($target['carbs_g']   - $consumed['carbs_g'], 1),
        ];

        return ['target' => $target, 'consumed' => $consumed, 'remaining' => $remaining];
    }

    /**
     * 推薦高蛋白食物：依「蛋白質 / 熱量」比例排序。
     *
     * @param  array<int, int>  $excludeFoodIds  今日已吃，要排除
     * @return array<int, array<string, mixed>>
     */
    public function recommendHighProteinFoods(User $user, array $excludeFoodIds = [], int $limit = 5): array
    {
        $foods = Food::query()
            ->visibleTo($user->id)
            ->where('calories', '>', 0)                      // 避免除以 0
            ->whereNotIn('id', $excludeFoodIds ?: [0])
            ->orderByRaw('protein_g * 1.0 / calories DESC')  // 蛋白質含量比
            ->orderByDesc('protein_g')                       // 同比例下蛋白質高的優先
            ->limit($limit)
            ->get();

        return $this->mapFoods($foods);
    }

    /**
     * 推薦低熱量食物：calories 升冪。
     * $maxCal 給「剩餘熱量上限」用，避免推薦超過剩餘額度的食物。
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendLowCalorieFoods(User $user, array $excludeFoodIds = [], int $limit = 5, ?int $maxCal = null): array
    {
        $query = Food::query()
            ->visibleTo($user->id)
            ->whereNotIn('id', $excludeFoodIds ?: [0]);

        if ($maxCal !== null) {
            $query->where('calories', '<=', $maxCal);
        }

        $foods = $query
            ->orderBy('calories')
            ->orderBy('fat_g')
            ->limit($limit)
            ->get();

        return $this->mapFoods($foods);
    }

    /**
     * 推薦低脂食物：fat_g 升冪。
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendLowFatFoods(User $user, array $excludeFoodIds = [], int $limit = 5): array
    {
        $foods = Food::query()
            ->visibleTo($user->id)
            ->whereNotIn('id', $excludeFoodIds ?: [0])
            ->orderBy('fat_g')
            ->orderBy('calories')
            ->limit($limit)
            ->get();

        return $this->mapFoods($foods);
    }

    /**
     * 依 goal_type 推薦：
     * - lose_fat：高蛋白 + 低熱量（蛋白質/熱量比優先）
     * - gain_muscle：高蛋白 + 熱量不要太低
     * - maintain：均衡（依名稱排序，常見食物優先）
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendByGoalType(User $user, string $goalType, array $excludeFoodIds = [], int $limit = 5): array
    {
        $base = Food::query()
            ->visibleTo($user->id)
            ->whereNotIn('id', $excludeFoodIds ?: [0]);

        $foods = match ($goalType) {
            'lose_fat' => $base
                ->where('calories', '>', 0)
                ->orderByRaw('protein_g * 1.0 / calories DESC')
                ->orderBy('fat_g')
                ->limit($limit)
                ->get(),

            'gain_muscle' => $base
                ->where('calories', '>=', 200)              // 太低熱量沒意義
                ->orderByDesc('protein_g')
                ->orderByDesc('calories')
                ->limit($limit)
                ->get(),

            'maintain' => $base
                ->orderByDesc('is_system')                  // 系統食物優先（較均衡）
                ->orderBy('name')
                ->limit($limit)
                ->get(),

            default => $base
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->limit($limit)
                ->get(),
        };

        return $this->mapFoods($foods);
    }

    // ============================================================
    // 內部工具
    // ============================================================

    /**
     * 找出使用者今日已吃過的 food_id（去重）。
     *
     * @return array<int, int>
     */
    private function getTodayEatenFoodIds(User $user): array
    {
        $meals = $this->mealService->listOfDate($user->id);
        $ids = [];
        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                if ($item->food_id !== null) {
                    $ids[] = (int) $item->food_id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Food collection → 扁平陣列（給前端用）。
     *
     * @param  Collection<int, Food>  $foods
     * @return array<int, array<string, mixed>>
     */
    private function mapFoods(Collection $foods): array
    {
        return $foods->map(static fn (Food $f) => [
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
            // 修正四：把資料來源傳到前端
            'source_type'      => $f->source_type,
            'confidence_level' => $f->confidence_level,
        ])->values()->all();
    }

    private function goalGroupTitle(string $goalType): string
    {
        return match ($goalType) {
            'lose_fat'    => '減脂目標推薦',
            'gain_muscle' => '增肌目標推薦',
            'maintain'    => '均衡飲食推薦',
            default       => '一般推薦',
        };
    }

    private function goalGroupReason(string $goalType): string
    {
        return match ($goalType) {
            'lose_fat'    => '依減脂目標，優先推薦高蛋白且熱量較低的食物。',
            'gain_muscle' => '依增肌目標，優先推薦高蛋白且熱量足夠的食物。',
            'maintain'    => '依維持目標，推薦均衡常見的食物選項。',
            default       => '一般日常飲食推薦。',
        };
    }
}
