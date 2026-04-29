<?php

namespace App\Services\Goal;

use App\Models\BodyRecord;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Nutrition\NutritionCalculatorService;

/**
 * 目標進度與達標時間估算 service。
 *
 * 設計原則（嚴格遵守）：
 *   - 估算只能作為一般參考，不做醫療診斷
 *   - 不保證使用者一定能達成目標
 *   - 不鼓勵極端減重（每週減重上限 0.5 kg、增重上限 0.25 kg）
 *   - 結果一律附上 disclaimer
 *   - 資料不足時，回對應 status，不要硬算
 */
class GoalProgressService
{
    /** Status 6 種 */
    public const STATUS_NO_PROFILE       = 'no_profile';
    public const STATUS_NO_WEIGHT_RECORD = 'no_weight_record';
    public const STATUS_IN_PROGRESS      = 'in_progress';
    public const STATUS_NEAR_GOAL        = 'near_goal';
    public const STATUS_REACHED_GOAL     = 'reached_goal';
    public const STATUS_MAINTAIN         = 'maintain';

    /** 距離目標 ≤ 此值視為「已接近」 */
    private const NEAR_GOAL_KG = 1.0;

    /** 距離目標 ≤ 此值視為「已達標」 */
    private const REACHED_GOAL_KG = 0.1;

    /** 每週體重變化估算（保守） */
    private const WEEKLY_CHANGE = [
        'lose_fat'    => -0.5,
        'gain_muscle' => 0.25,
        'maintain'    => 0.0,
    ];

    /** 一律附在估算結果上 */
    private const DISCLAIMER = '此為公式估算，實際變化會受到飲食、運動、睡眠、壓力與身體狀況影響，且 BMI 無法區分肌肉與脂肪。建議連續記錄 14～21 天後依體重趨勢微調目標，本系統提示僅供一般參考，並非醫療診斷。';

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * 1) 目標體重 = target_bmi × 身高(m)²
     */
    public function calculateTargetWeight(float $heightCm, float $targetBmi): float
    {
        return $this->calculator->calculateTargetWeight($heightCm, $targetBmi);
    }

    /**
     * 2) 取得最新體重 + 來源說明。
     * 優先用 body_records 最新一筆；沒有就 fallback 到 user_profiles.weight_kg。
     *
     * @return array{weight: float, source: 'body_record'|'profile'}|null
     */
    public function getLatestWeight(User $user, ?UserProfile $profile): ?array
    {
        $latest = $user->bodyRecords()
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->first();

        if ($latest !== null) {
            return ['weight' => (float) $latest->weight_kg, 'source' => 'body_record'];
        }

        if ($profile !== null && $profile->weight_kg !== null) {
            return ['weight' => (float) $profile->weight_kg, 'source' => 'profile'];
        }

        return null;
    }

    /**
     * 3) 體重差距（有號數值，正數=需減重、負數=需增重）。
     */
    public function calculateWeightDifference(float $currentWeight, float $targetWeight): float
    {
        return round($currentWeight - $targetWeight, 1);
    }

    /**
     * 4) 每週預估體重變化（保守估算）。
     */
    public function estimateWeeklyWeightChange(string $goalType): float
    {
        return self::WEEKLY_CHANGE[$goalType] ?? 0.0;
    }

    /**
     * 5) 估算需要多少週才能接近目標。
     * weeklyChange = 0 → 回 null（如：維持）
     */
    public function calculateEstimatedWeeks(float $weightDifference, float $weeklyChange): ?int
    {
        if ($weeklyChange == 0.0) {
            return null;
        }
        return (int) ceil(abs($weightDifference) / abs($weeklyChange));
    }

    /**
     * 6) 從週數推算預估達標日期（YYYY-MM-DD）。
     */
    public function calculateEstimatedTargetDate(?int $weeks): ?string
    {
        if ($weeks === null) {
            return null;
        }
        return now()->addWeeks($weeks)->toDateString();
    }

    /**
     * 7) 主入口：產出完整目標進度資料。
     *
     * @return array<string, mixed>
     */
    public function generateGoalProgress(User $user): array
    {
        $profile = $user->profile;

        // === Case 1：個人資料未完成 ===
        if ($profile === null || ! $profile->isComplete()) {
            return [
                'goal_type'                  => null,
                'height_cm'                  => null,
                'current_weight_kg'          => null,
                'target_bmi'                 => null,
                'target_weight_kg'           => null,
                'weight_difference_kg'       => null,
                'estimated_weekly_change_kg' => null,
                'estimated_weeks'            => null,
                'estimated_target_date'      => null,
                'status'                     => self::STATUS_NO_PROFILE,
                'message'                    => '請先完成個人資料設定，才能估算目標進度。',
                'disclaimer'                 => null,
            ];
        }

        // === Case 2：個人資料完整 ===
        $heightCm     = (float) $profile->height_cm;
        $targetBmi    = (float) $profile->target_bmi;
        $targetWeight = $this->calculateTargetWeight($heightCm, $targetBmi);
        $goalType     = (string) $profile->goal_type;

        $weightInfo    = $this->getLatestWeight($user, $profile);
        $hasRecord     = $weightInfo !== null && $weightInfo['source'] === 'body_record';
        $currentWeight = $weightInfo !== null ? $weightInfo['weight'] : (float) $profile->weight_kg;

        $diff    = $this->calculateWeightDifference($currentWeight, $targetWeight);
        $absDiff = abs($diff);

        $weeklyChange = $this->estimateWeeklyWeightChange($goalType);

        // 預設值
        $weeks      = null;
        $targetDate = null;
        $status     = self::STATUS_IN_PROGRESS;
        $message    = '';

        // === 狀態判定（優先順序）===
        if ($goalType === 'maintain') {
            $status  = self::STATUS_MAINTAIN;
            $message = '目前為維持模式，可以以穩定生活作息為主，不必特別估算達標時間。';
        } elseif ($absDiff <= self::REACHED_GOAL_KG) {
            $status  = self::STATUS_REACHED_GOAL;
            $message = '目前體重已接近目標範圍，可考慮以穩定維持為主，避免極端飲食。';
        } elseif ($absDiff <= self::NEAR_GOAL_KG) {
            $status  = self::STATUS_NEAR_GOAL;
            $message = '目前已接近目標體重，可考慮以穩定維持為主。';
        } else {
            // 距離還遠，需要估算週數
            $weeks      = $this->calculateEstimatedWeeks($diff, $weeklyChange);
            $targetDate = $this->calculateEstimatedTargetDate($weeks);

            if (! $hasRecord) {
                $status  = self::STATUS_NO_WEIGHT_RECORD;
                $message = "尚未建立體重紀錄，目前以個人資料中的體重作為估算基準，預估僅供粗略參考。預估約 {$weeks} 週後可接近目標範圍，建議盡快建立體重紀錄以提高準確度。";
            } else {
                $status      = self::STATUS_IN_PROGRESS;
                $direction   = $weeklyChange < 0 ? '減重' : '增重';
                $weeklyAbs   = number_format(abs($weeklyChange), 2);
                $message     = "依保守估算每週{$direction}約 {$weeklyAbs} kg，預估約 {$weeks} 週後可接近目標範圍。實際進度會因飲食、運動、睡眠等因素變化，僅供一般參考。";
            }
        }

        return [
            'goal_type'                  => $goalType,
            'height_cm'                  => $heightCm,
            'current_weight_kg'          => round($currentWeight, 1),
            'target_bmi'                 => $targetBmi,
            'target_weight_kg'           => $targetWeight,
            'weight_difference_kg'       => round($absDiff, 1),
            'estimated_weekly_change_kg' => $weeklyChange,
            'estimated_weeks'            => $weeks,
            'estimated_target_date'      => $targetDate,
            'status'                     => $status,
            'message'                    => $message,
            'disclaimer'                 => self::DISCLAIMER,
        ];
    }
}
