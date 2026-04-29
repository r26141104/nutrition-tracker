<?php

namespace App\Services\Analysis;

use App\Models\BodyRecord;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\NutritionCalculatorService;
use Carbon\CarbonImmutable;

/**
 * 體重波動解釋。
 *
 * 不直接說「變胖」，而是用近期飲食紀錄與趨勢做保守解釋。
 */
class WeightFluctuationInsightService
{
    /** 最少需要這麼多筆體重紀錄才下結論 */
    private const MIN_RECORDS = 3;

    /** 檢查近期幾天的飲食 */
    private const RECENT_DAYS = 5;

    /** 熱量超標多少天才視為「明顯超標」 */
    private const SURPLUS_DAYS_THRESHOLD = 2;

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateInsight(User $user): array
    {
        $records = BodyRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->get();

        if ($records->count() < self::MIN_RECORDS) {
            return [
                'has_enough_data'      => false,
                'latest_weight_kg'     => null,
                'previous_weight_kg'   => null,
                'seven_day_average_kg' => null,
                'message'              => '體重紀錄少於 3 筆，無法判斷波動。建議累積更多紀錄後再觀察。',
                'possible_reasons'     => [],
            ];
        }

        $latest   = $records->first();
        $previous = $records->skip(1)->first();
        $latestKg = (float) $latest->weight_kg;
        $prevKg   = (float) $previous->weight_kg;

        // 7 日平均
        $sevenDaysAgo = CarbonImmutable::now()->subDays(6)->startOfDay()->toDateString();
        $recentSeven = $records->filter(
            fn (BodyRecord $r) => $r->record_date?->toDateString() >= $sevenDaysAgo,
        );
        $sevenDayAvg = $recentSeven->isEmpty() ? null
            : round($recentSeven->avg(fn ($r) => (float) $r->weight_kg), 1);

        // 近期飲食檢查
        $surplusDays  = $this->countSurplusDays($user);
        $carbsHigh    = $this->checkCarbsHigh($user);

        // 產出訊息與可能原因
        $message = $this->buildMessage($latestKg, $prevKg, $sevenDayAvg);
        $reasons = $this->buildReasons($latestKg, $prevKg, $sevenDayAvg, $surplusDays, $carbsHigh);

        return [
            'has_enough_data'      => true,
            'latest_weight_kg'     => $latestKg,
            'previous_weight_kg'   => $prevKg,
            'seven_day_average_kg' => $sevenDayAvg,
            'message'              => $message,
            'possible_reasons'     => $reasons,
        ];
    }

    /**
     * 檢查近 N 天有幾天熱量超過目標。
     */
    private function countSurplusDays(User $user): int
    {
        $profile = $user->profile;
        if ($profile === null || ! $profile->isComplete()) {
            return 0;
        }

        $target = $this->calculator->generateNutritionTarget($profile);
        $targetCal = (int) $target['daily_calories'];
        if ($targetCal <= 0) return 0;

        $startDate = CarbonImmutable::now()->subDays(self::RECENT_DAYS - 1)->startOfDay();

        $meals = Meal::query()
            ->where('user_id', $user->id)
            ->where('eaten_at', '>=', $startDate)
            ->with('items')
            ->get();

        $byDate = [];
        foreach ($meals as $meal) {
            $date = $meal->eaten_at?->toDateString();
            if ($date === null) continue;
            if (! isset($byDate[$date])) $byDate[$date] = 0;
            foreach ($meal->items as $item) {
                $byDate[$date] += $item->total_calories;
            }
        }

        $surplus = 0;
        foreach ($byDate as $cal) {
            if ($cal > $targetCal) $surplus++;
        }
        return $surplus;
    }

    /**
     * 檢查近 N 天平均碳水是否高於目標。
     */
    private function checkCarbsHigh(User $user): bool
    {
        $profile = $user->profile;
        if ($profile === null || ! $profile->isComplete()) {
            return false;
        }

        $target = $this->calculator->generateNutritionTarget($profile);
        $targetCarbs = (float) $target['carbs_g'];
        if ($targetCarbs <= 0) return false;

        $startDate = CarbonImmutable::now()->subDays(self::RECENT_DAYS - 1)->startOfDay();

        $meals = Meal::query()
            ->where('user_id', $user->id)
            ->where('eaten_at', '>=', $startDate)
            ->with('items')
            ->get();

        if ($meals->isEmpty()) return false;

        $totalCarbs = 0.0;
        $loggedDates = [];
        foreach ($meals as $meal) {
            $date = $meal->eaten_at?->toDateString();
            if ($date === null) continue;
            $loggedDates[$date] = true;
            foreach ($meal->items as $item) {
                $totalCarbs += (float) $item->total_carbs_g;
            }
        }

        $loggedDays = count($loggedDates);
        if ($loggedDays === 0) return false;

        $avgCarbs = $totalCarbs / $loggedDays;
        return $avgCarbs > $targetCarbs;
    }

    private function buildMessage(float $latest, float $previous, ?float $sevenDayAvg): string
    {
        $diff = $latest - $previous;

        if ($sevenDayAvg === null) {
            // 沒 7 日平均
            if (abs($diff) < 0.3) {
                return '本次體重與上次紀錄差異不大，建議持續觀察。';
            }
            return '本次體重與上次紀錄有變化，但目前資料尚不足以判斷整體趨勢。';
        }

        $diffFromAvg = $latest - $sevenDayAvg;

        if ($diff > 0.3 && abs($diffFromAvg) < 0.5) {
            // 比上次高，但近 7 日平均接近
            return '今日體重比上次紀錄高，但 7 日平均變化不大，可能只是短期水分波動。建議持續觀察趨勢，不要只看單日體重。';
        }
        if ($diff > 0.3 && $diffFromAvg > 0.5) {
            return '今日體重比上次紀錄高，且高於近 7 日平均。可能與近期飲食、水分或活動量有關，建議再觀察幾天確認趨勢。';
        }
        if ($diff < -0.3 && abs($diffFromAvg) < 0.5) {
            return '今日體重比上次紀錄低，但 7 日平均變化不大，可能只是短期波動。';
        }
        if ($diff < -0.3 && $diffFromAvg < -0.5) {
            return '今日體重比上次紀錄低，且低於近 7 日平均，整體趨勢看起來有下降。';
        }
        return '本次體重變化在合理範圍內，建議持續觀察。';
    }

    /**
     * @return array<int, string>
     */
    private function buildReasons(float $latest, float $previous, ?float $sevenDayAvg, int $surplusDays, bool $carbsHigh): array
    {
        $reasons = [];

        $diff = $latest - $previous;
        $isShortTermFluctuation = $sevenDayAvg !== null
            && $diff > 0.3
            && abs($latest - $sevenDayAvg) < 0.5;

        if ($isShortTermFluctuation) {
            $reasons[] = '短期水分波動';
        }

        if ($surplusDays >= self::SURPLUS_DAYS_THRESHOLD) {
            $reasons[] = '近期熱量盈餘較多';
        }

        if ($carbsHigh) {
            $reasons[] = '近期碳水攝取較高（碳水會吸附水分）';
        }

        if (empty($reasons)) {
            $reasons[] = '一般日常波動';
        }

        return $reasons;
    }
}
