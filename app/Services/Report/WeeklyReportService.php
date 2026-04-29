<?php

namespace App\Services\Report;

use App\Models\BodyRecord;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\NutritionCalculatorService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * 每週飲食 + 體重報告 service。
 *
 * 設計原則（嚴格遵守）：
 *   - 統計只能作為一般參考，不做醫療診斷
 *   - 不保證使用者一定會減重或增肌
 *   - 資料不足時，回明確的「資料不足」提示而不是硬算
 *   - 第一版即時計算，不建表（如需快取再規劃）
 */
class WeeklyReportService
{
    /** Disclaimer 一律附在報告底下 */
    private const WARNINGS = [
        '每週報告為估算結果，實際變化會受到飲食、運動、睡眠與身體狀況影響。',
        '體重容易受水分、鈉含量、碳水攝取與排便狀況影響，建議觀察 7 日平均較準確。',
        '本報告僅供一般參考，並非醫療診斷。',
    ];

    /** 飲食紀錄少於這個天數時，summary 切到「資料不足」 */
    private const INSUFFICIENT_MEAL_DAYS = 3;

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * 1) 取本週週一 00:00 ~ 週日 23:59:59（不傳 reference 時用「現在」）。
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function getWeekRange(?CarbonImmutable $reference = null): array
    {
        $ref = $reference ?? CarbonImmutable::now();
        return [
            'start' => $ref->startOfWeek(Carbon::MONDAY),
            'end'   => $ref->endOfWeek(Carbon::SUNDAY),
        ];
    }

    /**
     * 2) 本週每日平均熱量（以「有紀錄的天數」為分母，避免 0/0）。
     */
    public function calculateAverageCalories(Collection $meals, int $loggedDays): int
    {
        if ($loggedDays <= 0) return 0;
        $total = 0;
        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                $total += $item->total_calories;
            }
        }
        return (int) round($total / $loggedDays);
    }

    /** 3) 本週每日平均蛋白質（g） */
    public function calculateAverageProtein(Collection $meals, int $loggedDays): float
    {
        return $this->averageMacro($meals, $loggedDays, 'total_protein_g');
    }

    /** 4) 本週每日平均脂肪（g） */
    public function calculateAverageFat(Collection $meals, int $loggedDays): float
    {
        return $this->averageMacro($meals, $loggedDays, 'total_fat_g');
    }

    /** 5) 本週每日平均碳水（g） */
    public function calculateAverageCarbs(Collection $meals, int $loggedDays): float
    {
        return $this->averageMacro($meals, $loggedDays, 'total_carbs_g');
    }

    private function averageMacro(Collection $meals, int $loggedDays, string $accessor): float
    {
        if ($loggedDays <= 0) return 0.0;
        $total = 0.0;
        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                $total += (float) $item->{$accessor};
            }
        }
        return round($total / $loggedDays, 1);
    }

    /**
     * 6) 本週有飲食紀錄的天數（distinct date）。
     */
    public function calculateLoggedMealDays(Collection $meals): int
    {
        return $meals
            ->map(fn (Meal $m) => $m->eaten_at?->toDateString())
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * 7) 本週體重變化：取最早與最晚紀錄相減。
     * - 0 筆 → null
     * - 1 筆 → 0.0（沒變化但有資料）
     * - 多筆 → last - first
     */
    public function calculateWeightChange(User $user, CarbonImmutable $start, CarbonImmutable $end): ?float
    {
        $records = BodyRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('record_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('record_date')
            ->orderBy('id')
            ->get();

        if ($records->count() === 0) return null;
        if ($records->count() === 1) return 0.0;

        $first = (float) $records->first()->weight_kg;
        $last  = (float) $records->last()->weight_kg;
        return round($last - $first, 1);
    }

    /**
     * 8) 本週最常吃的食物（按 food_id GROUP，跳過已被刪除的食物）。
     *
     * @return array<int, array{food_id:int, food_name:string, count:int}>
     */
    public function findMostFrequentFoods(Collection $meals, int $topN = 5): array
    {
        $counts = [];
        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                if ($item->food_id === null || $item->food === null) {
                    continue; // 跳過已刪除的食物（沒法顯示名稱）
                }
                $id = (int) $item->food_id;
                if (! isset($counts[$id])) {
                    $counts[$id] = [
                        'food_id'   => $id,
                        'food_name' => $item->food->name,
                        'count'     => 0,
                    ];
                }
                $counts[$id]['count']++;
            }
        }

        // 依 count 降冪、同 count 按 food_id 升冪
        usort($counts, function ($a, $b) {
            return $b['count'] === $a['count']
                ? $a['food_id'] - $b['food_id']
                : $b['count'] - $a['count'];
        });

        return array_slice(array_values($counts), 0, $topN);
    }

    /**
     * 9) 本週熱量超標天數（以每日 daily_calories 為門檻）。
     * 沒個人資料 → 0（無法判斷）
     */
    public function calculateOverTargetDays(User $user, Collection $meals): int
    {
        $profile = $user->profile;
        if ($profile === null || ! $profile->isComplete()) {
            return 0;
        }

        $target = $this->calculator->generateNutritionTarget($profile);
        $targetCalories = (int) ($target['daily_calories'] ?? 0);
        if ($targetCalories <= 0) {
            return 0;
        }

        // 按日期累加當天總熱量
        $byDate = [];
        foreach ($meals as $meal) {
            $date = $meal->eaten_at?->toDateString();
            if ($date === null) continue;
            if (! isset($byDate[$date])) {
                $byDate[$date] = 0;
            }
            foreach ($meal->items as $item) {
                $byDate[$date] += $item->total_calories;
            }
        }

        $overDays = 0;
        foreach ($byDate as $cal) {
            if ($cal > $targetCalories) {
                $overDays++;
            }
        }
        return $overDays;
    }

    /**
     * 10) 簡單文字總結（依資料動態組句子，措辭保守）。
     *
     * @param  array<string, mixed>  $stats
     * @return array<int, string>
     */
    public function generateSummary(array $stats): array
    {
        $summary = [];
        $loggedDays = (int) ($stats['logged_meal_days'] ?? 0);

        // === 飲食紀錄概況 ===
        if ($loggedDays === 0) {
            $summary[] = '本週尚無飲食紀錄，無法產生完整分析。建議從每日飲食紀錄開始追蹤。';
        } elseif ($loggedDays < self::INSUFFICIENT_MEAL_DAYS) {
            // 資料不足（< 3 天）：不給結論性建議
            $summary[] = "本週目前只有 {$loggedDays} 天有飲食紀錄，目前資料不足以分析飲食習慣。";
            $summary[] = '建議連續記錄至少 7 天後再判斷整體飲食情況。';
        } else {
            $summary[] = "本週目前有 {$loggedDays} 天有飲食紀錄。";
            $summary[] = "本週平均熱量約 {$stats['average_calories']} kcal（僅供參考）。";

            // 蛋白質評論：保守措辭
            $avgP = (float) ($stats['average_protein_g'] ?? 0);
            if ($avgP >= 60) {
                $summary[] = '本週蛋白質攝取目前看起來尚可，可考慮持續穩定補充。';
            } elseif ($avgP > 0) {
                $summary[] = '本週蛋白質平均攝取目前較少，可考慮增加雞蛋、豆製品、魚肉等來源。';
            }
        }

        // === 體重變化 ===
        $weightChange = $stats['weight_change_kg'] ?? null;
        if ($weightChange === null) {
            $summary[] = '本週尚無足夠體重紀錄，建議至少記錄 7 天後再觀察趨勢。';
        } elseif (abs($weightChange) < 0.1) {
            $summary[] = '本週體重變化微小，目前看起來維持穩定（注意：單週變化容易受水分波動影響）。';
        } else {
            $direction = $weightChange < 0 ? '減少' : '增加';
            $absChange = number_format(abs($weightChange), 1);
            $summary[] = "本週體重{$direction}約 {$absChange} kg；單週變化容易受水分、鈉含量與排便狀況影響，建議觀察整月趨勢較準確。";
        }

        // === 超標天數提醒（只在資料夠多時提）===
        if ($loggedDays >= self::INSUFFICIENT_MEAL_DAYS && ($stats['over_target_days'] ?? 0) > 0) {
            $summary[] = "本週有 {$stats['over_target_days']} 天熱量略高於目標，下週可考慮稍作調整；單日波動屬正常。";
        }

        return $summary;
    }

    /**
     * 11) 主入口：產出完整每週報告。
     *
     * @return array<string, mixed>
     */
    public function generateWeeklyReport(User $user): array
    {
        $range = $this->getWeekRange();
        $start = $range['start'];
        $end   = $range['end'];

        // 區間內所有 meals（含 items + food）
        $meals = Meal::query()
            ->where('user_id', $user->id)
            ->whereBetween('eaten_at', [$start, $end])
            ->with(['items.food'])
            ->orderBy('eaten_at')
            ->get();

        $loggedDays = $this->calculateLoggedMealDays($meals);

        $stats = [
            'week_start'           => $start->toDateString(),
            'week_end'             => $end->toDateString(),
            'logged_meal_days'     => $loggedDays,
            'average_calories'     => $this->calculateAverageCalories($meals, $loggedDays),
            'average_protein_g'    => $this->calculateAverageProtein($meals, $loggedDays),
            'average_fat_g'        => $this->calculateAverageFat($meals, $loggedDays),
            'average_carbs_g'      => $this->calculateAverageCarbs($meals, $loggedDays),
            'weight_change_kg'     => $this->calculateWeightChange($user, $start, $end),
            'over_target_days'     => $this->calculateOverTargetDays($user, $meals),
            'most_frequent_foods'  => $this->findMostFrequentFoods($meals),
        ];

        $stats['summary']  = $this->generateSummary($stats);
        $stats['warnings'] = self::WARNINGS;

        return $stats;
    }
}
