<?php

namespace App\Services\Analysis;

use App\Models\BodyRecord;
use App\Models\Meal;
use App\Models\User;
use App\Services\Nutrition\NutritionCalculatorService;

/**
 * 熱量目標自動修正建議。
 *
 * 用最近 14 天的飲食 + 體重資料，比對「依公式預期變化」與「實際變化」，
 * 給出 6 種建議之一：insufficient_data / keep / decrease_calories /
 * increase_calories / increase_activity / observe_more
 *
 * 嚴格不直接修改使用者的營養目標，只提供建議文字。
 */
class CalorieAdjustmentService
{
    public const STATUS_INSUFFICIENT     = 'insufficient_data';
    public const STATUS_KEEP             = 'keep';
    public const STATUS_DECREASE         = 'decrease_calories';
    public const STATUS_INCREASE         = 'increase_calories';
    public const STATUS_INCREASE_ACTIVITY = 'increase_activity';
    public const STATUS_OBSERVE          = 'observe_more';

    private const PERIOD_DAYS = 14;
    private const MIN_MEAL_DAYS = 7;
    private const MIN_BODY_RECORDS = 3;
    private const DISCLAIMER = '此分析根據近期紀錄估算，實際變化會受到水分、睡眠、壓力、運動與紀錄誤差影響，僅供一般參考，並非醫療診斷。';

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * 主入口。
     *
     * @return array<string, mixed>
     */
    public function generateAnalysis(User $user): array
    {
        $profile = $user->profile;
        if ($profile === null || ! $profile->isComplete()) {
            return $this->insufficient('請先完成個人資料設定，才能分析熱量調整。');
        }

        $startDate = now()->subDays(self::PERIOD_DAYS - 1)->startOfDay();
        $endDate   = now()->endOfDay();

        // 飲食紀錄
        $meals = Meal::query()
            ->where('user_id', $user->id)
            ->whereBetween('eaten_at', [$startDate, $endDate])
            ->with('items')
            ->get();

        $loggedDays = $meals
            ->map(fn (Meal $m) => $m->eaten_at?->toDateString())
            ->filter()
            ->unique()
            ->count();

        if ($loggedDays < self::MIN_MEAL_DAYS) {
            return $this->insufficient("目前飲食紀錄只有 {$loggedDays} 天，建議至少連續記錄 7～14 天後再判斷。");
        }

        // 體重紀錄
        $records = BodyRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('record_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('record_date')
            ->orderBy('id')
            ->get();

        if ($records->count() < self::MIN_BODY_RECORDS) {
            return $this->insufficient("目前體重紀錄只有 {$records->count()} 筆，建議累積至少 3 筆紀錄後再判斷。");
        }

        // 平均每日熱量
        $totalCal = 0;
        foreach ($meals as $meal) {
            foreach ($meal->items as $item) {
                $totalCal += $item->total_calories;
            }
        }
        $avgCalories = (int) round($totalCal / $loggedDays);

        // 體重變化（用最早與最晚紀錄）
        $startWeight = (float) $records->first()->weight_kg;
        $endWeight   = (float) $records->last()->weight_kg;
        $actualChange = round($endWeight - $startWeight, 1);

        // 預期體重變化（按目標每週速率）
        $weeks = $loggedDays / 7.0;
        $weeklyExpected = $this->expectedWeeklyChange((string) $profile->goal_type);
        $expectedChange = round($weeklyExpected * $weeks, 1);

        // 比對 + 產建議
        [$type, $adjustment, $message] = $this->buildSuggestion(
            (string) $profile->goal_type,
            $actualChange,
            $expectedChange,
            $avgCalories,
        );

        return [
            'period_days'                  => self::PERIOD_DAYS,
            'has_enough_data'              => true,
            'average_daily_calories'       => $avgCalories,
            'start_weight_kg'              => $startWeight,
            'end_weight_kg'                => $endWeight,
            'actual_weight_change_kg'      => $actualChange,
            'expected_weight_change_kg'    => $expectedChange,
            'suggestion_type'              => $type,
            'suggested_calorie_adjustment' => $adjustment,
            'message'                      => $message,
            'disclaimer'                   => self::DISCLAIMER,
        ];
    }

    /**
     * 公式預期每週變化 kg。
     */
    private function expectedWeeklyChange(string $goalType): float
    {
        return match ($goalType) {
            'lose_fat'    => -0.5,   // 保守減重每週 0.5 kg
            'gain_muscle' => 0.25,   // 保守增肌每週 0.25 kg
            'maintain'    => 0.0,
            default       => 0.0,
        };
    }

    /**
     * 比對實際/預期，產出建議。
     *
     * @return array{0: string, 1: int, 2: string}  [suggestion_type, kcal_adjustment, message]
     */
    private function buildSuggestion(string $goalType, float $actual, float $expected, int $avgCalories): array
    {
        if ($goalType === 'maintain') {
            if (abs($actual) <= 1.0) {
                return [self::STATUS_KEEP, 0, '目前體重維持穩定，建議延續目前策略。'];
            }
            return [self::STATUS_OBSERVE, 0, '近期體重變化較大，建議觀察飲食、活動量與睡眠是否有變動。'];
        }

        if ($goalType === 'lose_fat') {
            // expected 是負數
            if ($actual >= -0.05) {
                // 幾乎沒下降
                return [
                    self::STATUS_DECREASE,
                    -100,
                    '近期體重幾乎沒下降，可考慮小幅降低每日熱量約 100 kcal，或增加日常步行；不建議過度節食。',
                ];
            }
            if ($actual < -0.05 && $actual > $expected * 0.5) {
                // 有下降但慢於預期
                return [
                    self::STATUS_INCREASE_ACTIVITY,
                    0,
                    '體重有下降但慢於預期，可考慮增加日常活動量（例如多走 1500 步），先觀察一週。',
                ];
            }
            if ($actual <= $expected * 1.5) {
                // 下降太快
                return [
                    self::STATUS_OBSERVE,
                    0,
                    '近期體重下降速度偏快，建議觀察是否有過度節食或脫水情形，必要時可微調回安全範圍。',
                ];
            }
            // 接近預期
            return [self::STATUS_KEEP, 0, '目前體重變化接近預期，建議先維持目前熱量目標。'];
        }

        if ($goalType === 'gain_muscle') {
            // expected 是正數
            if ($actual <= 0.05) {
                // 沒上升
                return [
                    self::STATUS_INCREASE,
                    100,
                    '近期體重沒有上升，可考慮小幅提高每日熱量約 100 kcal，並確認蛋白質與訓練穩定。',
                ];
            }
            if ($actual > $expected * 2.0) {
                // 上升太快
                return [
                    self::STATUS_OBSERVE,
                    0,
                    '近期體重上升速度偏快，可能熱量盈餘較高，建議觀察體脂率與訓練表現一起判斷。',
                ];
            }
            return [self::STATUS_KEEP, 0, '目前體重變化接近預期，建議先維持目前策略。'];
        }

        return [self::STATUS_OBSERVE, 0, '無法判斷，建議繼續觀察體重趨勢與飲食紀錄。'];
    }

    /**
     * @return array<string, mixed>
     */
    private function insufficient(string $message): array
    {
        return [
            'period_days'                  => self::PERIOD_DAYS,
            'has_enough_data'              => false,
            'average_daily_calories'       => null,
            'start_weight_kg'              => null,
            'end_weight_kg'                => null,
            'actual_weight_change_kg'      => null,
            'expected_weight_change_kg'    => null,
            'suggestion_type'              => self::STATUS_INSUFFICIENT,
            'suggested_calorie_adjustment' => 0,
            'message'                      => $message,
            'disclaimer'                   => self::DISCLAIMER,
        ];
    }
}
