<?php

namespace App\Services\Nutrition;

use App\Models\UserProfile;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * 純計算邏輯：年齡、目標體重、BMR、TDEE、每日熱量、三大營養素、warnings。
 * 不碰 DB、無副作用，方便日後加單元測試。
 *
 * 公式：
 *   - BMR：Mifflin-St Jeor
 *   - TDEE = BMR × 活動係數 (1.2 / 1.375 / 1.55 / 1.725)
 *   - 每日熱量 = TDEE × 目標係數 (lose_fat 0.85 / maintain 1.00 / gain_muscle 1.10)
 *   - 蛋白質 = 體重 × 倍率 (lose_fat 2.0 / gain_muscle 1.8 / maintain 1.6) g
 *   - 脂肪 = 每日熱量 × 25% / 9 g
 *   - 碳水 = 餘量 / 4 g
 */
class NutritionCalculatorService
{
    /** 活動係數 */
    private const ACTIVITY_FACTORS = [
        'sedentary' => 1.2,
        'light'     => 1.375,
        'moderate'  => 1.55,
        'active'    => 1.725,
    ];

    /** 目標 → 每日熱量倍率（相對於 TDEE） */
    private const GOAL_CALORIE_FACTORS = [
        'lose_fat'    => 0.85, // -15%
        'maintain'    => 1.00,
        'gain_muscle' => 1.10, // +10%
    ];

    /** 目標 → 蛋白質 g/kg */
    private const PROTEIN_PER_KG = [
        'lose_fat'    => 2.0,
        'gain_muscle' => 1.8,
        'maintain'    => 1.6,
    ];

    /** 脂肪佔每日熱量比例 */
    private const FAT_RATIO = 0.25;

    // =========================================================================
    // 1. calculateAge
    // =========================================================================

    /**
     * 由生日算現在年齡（足歲）。
     */
    public function calculateAge(DateTimeInterface $birthdate): int
    {
        return Carbon::instance(Carbon::parse($birthdate))->age;
    }

    // =========================================================================
    // 2. calculateTargetWeight
    // =========================================================================

    /**
     * 由身高與目標 BMI 反推目標體重（kg），保留 1 位小數。
     */
    public function calculateTargetWeight(float $heightCm, float $targetBmi): float
    {
        $heightM = $heightCm / 100;
        return round($targetBmi * $heightM * $heightM, 1);
    }

    // =========================================================================
    // 3. calculateBmr  (Mifflin-St Jeor)
    // =========================================================================

    /**
     * BMR = 10 × 體重(kg) + 6.25 × 身高(cm) - 5 × 年齡 + (男 +5 / 女 -161)
     */
    public function calculateBmr(float $weightKg, float $heightCm, int $age, string $sex): int
    {
        $base = (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age);
        $base += $sex === 'male' ? 5 : -161;
        return (int) round($base);
    }

    // =========================================================================
    // 4. calculateTdee
    // =========================================================================

    public function calculateTdee(int $bmr, string $activityLevel): int
    {
        $factor = self::ACTIVITY_FACTORS[$activityLevel] ?? 1.2;
        return (int) round($bmr * $factor);
    }

    // =========================================================================
    // 5. calculateDailyCalories
    // =========================================================================

    /**
     * 依目標調整後的每日建議熱量。
     */
    public function calculateDailyCalories(int $tdee, string $goalType): int
    {
        $factor = self::GOAL_CALORIE_FACTORS[$goalType] ?? 1.0;
        return (int) round($tdee * $factor);
    }

    // =========================================================================
    // 6. calculateProteinTarget
    // =========================================================================

    public function calculateProteinTarget(float $weightKg, string $goalType): int
    {
        $perKg = self::PROTEIN_PER_KG[$goalType] ?? 1.6;
        return (int) round($weightKg * $perKg);
    }

    // =========================================================================
    // 7. calculateFatTarget
    // =========================================================================

    /**
     * 脂肪 g：每日熱量 × 25%（脂肪比例）÷ 9（每克 9 kcal）
     */
    public function calculateFatTarget(int $dailyCalories): int
    {
        return (int) round(($dailyCalories * self::FAT_RATIO) / 9);
    }

    // =========================================================================
    // 8. calculateCarbsTarget
    // =========================================================================

    /**
     * 碳水 g：把蛋白質與脂肪的熱量扣掉，剩下的除以 4。
     */
    public function calculateCarbsTarget(int $dailyCalories, int $proteinG, int $fatG): int
    {
        $proteinKcal = $proteinG * 4;
        $fatKcal     = $fatG * 9;
        $carbsKcal   = max(0, $dailyCalories - $proteinKcal - $fatKcal);
        return (int) round($carbsKcal / 4);
    }

    // =========================================================================
    // 9. generateNutritionTarget — 整套
    // =========================================================================

    /**
     * 把 1~8 串起來，產出完整每日營養目標。
     *
     * @return array{
     *   age:int, target_weight_kg:float, bmr:int, tdee:int,
     *   daily_calories:int, protein_g:int, fat_g:int, carbs_g:int,
     *   warnings:array<int,string>, note:string
     * }
     */
    public function generateNutritionTarget(UserProfile $profile): array
    {
        $age            = $this->calculateAge($profile->birthdate);
        $targetWeight   = $this->calculateTargetWeight((float) $profile->height_cm, (float) $profile->target_bmi);
        $bmr            = $this->calculateBmr((float) $profile->weight_kg, (float) $profile->height_cm, $age, (string) $profile->sex);
        $tdee           = $this->calculateTdee($bmr, (string) $profile->activity_level);
        $dailyCalories  = $this->calculateDailyCalories($tdee, (string) $profile->goal_type);
        $protein        = $this->calculateProteinTarget((float) $profile->weight_kg, (string) $profile->goal_type);
        $fat            = $this->calculateFatTarget($dailyCalories);
        $carbs          = $this->calculateCarbsTarget($dailyCalories, $protein, $fat);

        return [
            'age'              => $age,
            'target_weight_kg' => $targetWeight,
            'bmr'              => $bmr,
            'tdee'             => $tdee,
            'daily_calories'   => $dailyCalories,
            'protein_g'        => $protein,
            'fat_g'            => $fat,
            'carbs_g'          => $carbs,
            'warnings'         => $this->buildWarnings($profile, $bmr, $dailyCalories),
            'note'             => '以上為依公式估算之建議值。BMR / TDEE 採用 Mifflin-St Jeor 公式，誤差可達 ±10%；實際需求會受到活動量、睡眠、壓力、年齡、紀錄誤差等因素影響。建議連續記錄至少 14～21 天後，再根據體重趨勢微調目標。BMI 僅作為一般參考，無法區分肌肉與脂肪。',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildWarnings(UserProfile $profile, int $bmr, int $dailyCalories): array
    {
        $warnings = [];

        $targetBmi = (float) $profile->target_bmi;
        if ($targetBmi < 18.5) {
            $warnings[] = '目標 BMI 低於 18.5，可能屬於體重不足，建議諮詢醫師或營養師後再執行。';
        }
        if ($targetBmi > 30.0) {
            $warnings[] = '目標 BMI 高於 30，建議重新評估目標是否合理。';
        }

        $minCalories = $profile->sex === 'female' ? 1200 : 1500;
        if ($dailyCalories < $minCalories) {
            $warnings[] = "每日建議熱量低於 {$minCalories} kcal，可能影響健康，建議諮詢營養師調整目標。";
        }
        if ($dailyCalories < $bmr) {
            $warnings[] = '每日建議熱量低於基礎代謝率（BMR），長期可能造成代謝下降。';
        }

        return $warnings;
    }
}
