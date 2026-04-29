<?php

namespace App\Services\Streak;

use App\Models\BodyRecord;
use App\Models\Meal;
use App\Models\User;
use App\Models\WaterRecord;
use Illuminate\Support\Carbon;

/**
 * 連續紀錄天數 + 達成徽章。
 *
 * 純計算 service，不寫表。
 */
class StreakService
{
    /** 飲食 streak 徽章門檻 */
    private const MEAL_BADGES = [3, 7, 14, 30, 60, 100];

    /** 體重 streak 徽章門檻（體重通常每天記，但門檻可以低一點） */
    private const BODY_BADGES = [3, 7, 14, 30];

    /**
     * 主入口。
     *
     * @return array<string, mixed>
     */
    public function generateStreakInfo(User $user): array
    {
        $mealStreak  = $this->calculateMealStreak($user);
        $bodyStreak  = $this->calculateBodyRecordStreak($user);
        $waterStreak = $this->calculateWaterStreak($user);

        return [
            'meal_streak'         => $mealStreak,
            'body_record_streak'  => $bodyStreak,
            'water_streak'        => $waterStreak,
            'longest_meal_streak' => $this->calculateLongestMealStreak($user),
            'achievements'        => $this->calculateAchievements($mealStreak, $bodyStreak, $waterStreak),
            'total_meal_days'     => $this->countTotalMealDays($user),
            'total_body_records'  => $user->bodyRecords()->count(),
        ];
    }

    /**
     * 計算當前飲食連續紀錄天數（從今天往回算）。
     */
    public function calculateMealStreak(User $user): int
    {
        $current = Carbon::now()->startOfDay();
        $streak = 0;

        // 限制最多回查 365 天，避免無限迴圈
        for ($i = 0; $i < 365; $i++) {
            $exists = Meal::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_at', $current->toDateString())
                ->exists();

            if (! $exists) {
                // 今天沒紀錄不一定要中斷（可能還沒吃），給 1 天 grace
                if ($i === 0) {
                    $current = $current->subDay();
                    continue;
                }
                break;
            }
            $streak++;
            $current = $current->subDay();
        }

        return $streak;
    }

    /**
     * 計算歷史最長飲食連續紀錄天數。
     */
    public function calculateLongestMealStreak(User $user): int
    {
        $dates = Meal::query()
            ->where('user_id', $user->id)
            ->selectRaw('DISTINCT DATE(eaten_at) as d')
            ->orderBy('d')
            ->pluck('d')
            ->all();

        return $this->longestConsecutiveDates($dates);
    }

    /**
     * 計算當前體重連續紀錄天數。
     */
    public function calculateBodyRecordStreak(User $user): int
    {
        $current = Carbon::now()->startOfDay();
        $streak = 0;

        for ($i = 0; $i < 365; $i++) {
            $exists = BodyRecord::query()
                ->where('user_id', $user->id)
                ->whereDate('record_date', $current->toDateString())
                ->exists();

            if (! $exists) {
                if ($i === 0) {
                    $current = $current->subDay();
                    continue;
                }
                break;
            }
            $streak++;
            $current = $current->subDay();
        }

        return $streak;
    }

    /**
     * 計算當前水分連續紀錄天數（達標才算）。
     */
    public function calculateWaterStreak(User $user): int
    {
        // 水分目標（簡化：取使用者一次目標即可）
        $profile = $user->profile;
        $target = ($profile && $profile->weight_kg !== null)
            ? (int) round((float) $profile->weight_kg * 30.0)
            : 2000;

        $current = Carbon::now()->startOfDay();
        $streak = 0;

        for ($i = 0; $i < 365; $i++) {
            $record = WaterRecord::query()
                ->where('user_id', $user->id)
                ->whereDate('record_date', $current->toDateString())
                ->first();

            $reached = $record !== null && (int) $record->amount_ml >= $target;

            if (! $reached) {
                if ($i === 0) {
                    $current = $current->subDay();
                    continue;
                }
                break;
            }
            $streak++;
            $current = $current->subDay();
        }

        return $streak;
    }

    /**
     * 統計總共有飲食紀錄的天數（distinct）。
     */
    public function countTotalMealDays(User $user): int
    {
        return Meal::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(DISTINCT DATE(eaten_at)) as cnt')
            ->value('cnt') ?? 0;
    }

    /**
     * 計算達成徽章。
     *
     * @return array<int, array{type: string, level: int, label: string, achieved: bool}>
     */
    public function calculateAchievements(int $mealStreak, int $bodyStreak, int $waterStreak): array
    {
        $achievements = [];

        foreach (self::MEAL_BADGES as $threshold) {
            $achievements[] = [
                'type'     => 'meal',
                'level'    => $threshold,
                'label'    => "飲食連續 {$threshold} 天",
                'achieved' => $mealStreak >= $threshold,
            ];
        }
        foreach (self::BODY_BADGES as $threshold) {
            $achievements[] = [
                'type'     => 'body',
                'level'    => $threshold,
                'label'    => "體重連續 {$threshold} 天",
                'achieved' => $bodyStreak >= $threshold,
            ];
        }
        // 水分連續 7 天達標
        $achievements[] = [
            'type'     => 'water',
            'level'    => 7,
            'label'    => '水分連續 7 天達標',
            'achieved' => $waterStreak >= 7,
        ];

        return $achievements;
    }

    /**
     * 給一組日期字串，回傳最長連續天數。
     *
     * @param  array<int, string>  $dates
     */
    private function longestConsecutiveDates(array $dates): int
    {
        if (empty($dates)) return 0;

        $longest = 1;
        $current = 1;

        for ($i = 1; $i < count($dates); $i++) {
            $prev = Carbon::parse($dates[$i - 1]);
            $curr = Carbon::parse($dates[$i]);
            if ($prev->diffInDays($curr) === 1) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }
}
