<?php

namespace App\Services\Health;

use App\Models\BodyRecord;
use App\Models\UserProfile;
use App\Services\Nutrition\NutritionCalculatorService;

/**
 * 健康提醒 service。
 *
 * 設計原則（嚴格遵守）：
 *   - 提醒只能是「一般健康提醒」，不做醫療診斷
 *   - 不保證減重結果
 *   - 不鼓勵極端節食
 *   - 資料不足時，顯示「資料不足」而不是硬算
 *   - 措辭保守、避免讓使用者焦慮
 *
 * 回傳格式：每筆 warning 都是
 *   ['type' => 'info|warning|danger', 'category' => '...', 'message' => '...']
 */
class HealthWarningService
{
    /** Categories（給前端用） */
    public const CATEGORY_GENERAL    = 'general';
    public const CATEGORY_TARGET_BMI = 'target_bmi';
    public const CATEGORY_CALORIES   = 'calories';
    public const CATEGORY_PROTEIN    = 'protein';
    public const CATEGORY_FAT        = 'fat';
    public const CATEGORY_CARBS      = 'carbs';
    public const CATEGORY_WEIGHT     = 'weight';

    /** Types */
    public const TYPE_INFO    = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_DANGER  = 'danger';

    /** 蛋白質低於目標的這個比例就提醒 */
    private const PROTEIN_LOW_RATIO = 0.70;

    /** 碳水低於目標的這個比例就提醒（必須有吃東西才會觸發） */
    private const CARBS_LOW_RATIO = 0.40;

    /** 熱量大幅低於目標的判定（搭配「接近一天結束」才觸發） */
    private const CALORIES_LOW_RATIO = 0.50;

    /** 視為「接近一天結束」的小時門檻（24h 制） */
    private const END_OF_DAY_HOUR = 20;

    /** 距離目標體重 1 kg 內視為「已接近」 */
    private const NEAR_TARGET_WEIGHT_KG = 1.0;

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * 主入口：把所有 check 串起來，回傳結構化 warnings 陣列。
     *
     * @param  array<string, mixed>|null  $nutritionTarget   {target_weight_kg, bmr, tdee, calories, protein_g, fat_g, carbs_g}
     * @param  array<string, mixed>       $consumed          {calories, protein_g, fat_g, carbs_g}
     * @param  array<string, bool>        $isOver            {calories, protein_g, fat_g, carbs_g}
     * @return array<int, array<string, string>>
     */
    public function generateWarnings(
        ?UserProfile $profile,
        ?array $nutritionTarget,
        array $consumed,
        array $isOver,
        ?BodyRecord $latestBodyRecord = null,
    ): array {
        $warnings = [];

        // 1. 通用聲明（一律顯示，type=info）
        $warnings[] = [
            'type'     => self::TYPE_INFO,
            'category' => self::CATEGORY_GENERAL,
            'message'  => '每日營養目標為公式估算值，實際需求會因身體狀況、活動量、睡眠、壓力與紀錄誤差不同。建議連續記錄 14～21 天後再依體重趨勢微調，並非醫療診斷。',
        ];

        // 2. 目標 BMI（需要 profile）
        if ($profile !== null && ($w = $this->checkTargetBmi($profile)) !== null) {
            $warnings[] = $w;
        }

        // 3. 每日建議熱量（需要 target）
        if ($nutritionTarget !== null && ($w = $this->checkDailyCalories($nutritionTarget, $profile)) !== null) {
            $warnings[] = $w;
        }

        // 4. 熱量進度（需要 target）
        if ($nutritionTarget !== null && ($w = $this->checkCalorieProgress($nutritionTarget, $consumed, $isOver)) !== null) {
            $warnings[] = $w;
        }

        // 5/6/7. PFC：必須有 target，且至少有吃過東西（避免早上空腹就跳警告）
        $hasFood = ($consumed['calories'] ?? 0) > 0;
        if ($nutritionTarget !== null && $hasFood) {
            if (($w = $this->checkProteinProgress($nutritionTarget, $consumed)) !== null) {
                $warnings[] = $w;
            }
            if (($w = $this->checkFatProgress($nutritionTarget, $consumed, $isOver)) !== null) {
                $warnings[] = $w;
            }
            if (($w = $this->checkCarbsProgress($nutritionTarget, $consumed, $isOver)) !== null) {
                $warnings[] = $w;
            }
        }

        // 8. 體重狀態（需要 profile，沒有 body record 時也會出「資料不足」訊息）
        if ($profile !== null && ($w = $this->checkWeightStatus($profile, $latestBodyRecord)) !== null) {
            $warnings[] = $w;
        }

        return $warnings;
    }

    // ============================================================
    // 個別 check
    // ============================================================

    /**
     * 1) 目標 BMI 過低或過高。
     * - < 18.5 → warning
     * - > 24   → warning
     */
    public function checkTargetBmi(UserProfile $profile): ?array
    {
        if ($profile->target_bmi === null) {
            return null;
        }
        $bmi = (float) $profile->target_bmi;

        if ($bmi < 18.5) {
            return [
                'type'     => self::TYPE_WARNING,
                'category' => self::CATEGORY_TARGET_BMI,
                'message'  => '你設定的目標 BMI 低於一般健康成人參考範圍，可考慮重新評估。BMI 僅作參考、無法區分肌肉與脂肪，建議同時觀察體重趨勢、體脂率與飲食紀錄。',
            ];
        }
        if ($bmi > 24.0) {
            return [
                'type'     => self::TYPE_WARNING,
                'category' => self::CATEGORY_TARGET_BMI,
                'message'  => '你設定的目標 BMI 高於一般健康成人參考範圍。如果你的目標是增肌，BMI 上升不一定代表狀況變差，仍需搭配體脂率、肌肉量與訓練表現一起判斷；若有健康疑慮，建議諮詢專業人員。',
            ];
        }
        return null;
    }

    /**
     * 2) 每日建議熱量過低。
     * - 女性 < 1200 / 男性 < 1500
     * - 沒有性別資料時，採較保守的女性門檻
     */
    public function checkDailyCalories(array $target, ?UserProfile $profile): ?array
    {
        $cal = (int) ($target['calories'] ?? 0);
        if ($cal <= 0) return null;

        $sex = $profile?->sex;
        // 沒有性別資料 → 保守用 1200（較低門檻就提醒）
        $threshold = $sex === 'male' ? 1500 : 1200;

        if ($cal < $threshold) {
            return [
                'type'     => self::TYPE_WARNING,
                'category' => self::CATEGORY_CALORIES,
                'message'  => '每日建議熱量偏低，一般情況下不建議長期過度節食。若有特殊需求，建議諮詢專業營養師。',
            ];
        }
        return null;
    }

    /**
     * 3) 熱量進度。
     * - 已超標 → warning
     * - 接近一天結束 (>= 20:00) 且 < 50% 目標 → info（保守）
     */
    public function checkCalorieProgress(array $target, array $consumed, array $isOver): ?array
    {
        if (($isOver['calories'] ?? false) === true) {
            return [
                'type'     => self::TYPE_WARNING,
                'category' => self::CATEGORY_CALORIES,
                'message'  => '今日熱量略高於目標，明天可考慮選擇較清淡的餐點。單日波動屬正常，建議觀察 7 日平均。',
            ];
        }

        $targetCal = (float) ($target['calories'] ?? 0);
        $consumedCal = (float) ($consumed['calories'] ?? 0);
        if ($targetCal > 0 && (int) date('H') >= self::END_OF_DAY_HOUR) {
            $ratio = $consumedCal / $targetCal;
            if ($ratio < self::CALORIES_LOW_RATIO && $consumedCal > 0) {
                return [
                    'type'     => self::TYPE_INFO,
                    'category' => self::CATEGORY_CALORIES,
                    'message'  => '今日熱量攝取偏低，請注意不要長期攝取不足。',
                ];
            }
        }
        return null;
    }

    /**
     * 4) 蛋白質低於目標的 70%。
     * 注意：呼叫端負責確認 hasFood，這裡不再 gate。
     */
    public function checkProteinProgress(array $target, array $consumed): ?array
    {
        $targetP = (float) ($target['protein_g'] ?? 0);
        if ($targetP <= 0) return null;

        $consumedP = (float) ($consumed['protein_g'] ?? 0);
        if (($consumedP / $targetP) < self::PROTEIN_LOW_RATIO) {
            return [
                'type'     => self::TYPE_INFO,
                'category' => self::CATEGORY_PROTEIN,
                'message'  => '今日蛋白質攝取目前較少，可考慮增加雞蛋、豆製品、魚肉或雞胸肉等蛋白質來源。',
            ];
        }
        return null;
    }

    /**
     * 5) 脂肪超標。
     */
    public function checkFatProgress(array $target, array $consumed, array $isOver): ?array
    {
        if (($isOver['fat_g'] ?? false) === true) {
            return [
                'type'     => self::TYPE_WARNING,
                'category' => self::CATEGORY_FAT,
                'message'  => '今日脂肪攝取目前略高於目標，下一餐可考慮減少油炸與高油脂食物。單日波動屬正常，建議觀察整週平均。',
            ];
        }
        return null;
    }

    /**
     * 6) 碳水：超標 or 明顯過低。
     */
    public function checkCarbsProgress(array $target, array $consumed, array $isOver): ?array
    {
        if (($isOver['carbs_g'] ?? false) === true) {
            return [
                'type'     => self::TYPE_WARNING,
                'category' => self::CATEGORY_CARBS,
                'message'  => '今日碳水攝取目前略高於目標，下一餐可考慮減少精緻澱粉或含糖飲料。單日波動屬正常。',
            ];
        }

        $targetC = (float) ($target['carbs_g'] ?? 0);
        $consumedC = (float) ($consumed['carbs_g'] ?? 0);
        if ($targetC > 0 && ($consumedC / $targetC) < self::CARBS_LOW_RATIO) {
            return [
                'type'     => self::TYPE_INFO,
                'category' => self::CATEGORY_CARBS,
                'message'  => '今日碳水攝取目前較少，若有運動或感到疲勞，可留意主食攝取是否足夠。',
            ];
        }
        return null;
    }

    /**
     * 7) 體重狀態。
     * - 沒有 body record → 「資料不足」
     * - 有 body record，距離目標 < 1 kg → 「已接近目標」
     * - 其他 → 「距離目標體重約 X kg」
     */
    public function checkWeightStatus(UserProfile $profile, ?BodyRecord $latest): ?array
    {
        if ($latest === null) {
            return [
                'type'     => self::TYPE_INFO,
                'category' => self::CATEGORY_WEIGHT,
                'message'  => '尚未建立體重紀錄，建議至少記錄 7 天後再觀察體重趨勢。',
            ];
        }

        // 需要身高 + 目標 BMI 才能算目標體重
        if ($profile->height_cm === null || $profile->target_bmi === null) {
            return [
                'type'     => self::TYPE_INFO,
                'category' => self::CATEGORY_WEIGHT,
                'message'  => '個人資料不完整，無法判斷體重進度。',
            ];
        }

        $targetWeight = $this->calculator->calculateTargetWeight(
            (float) $profile->height_cm,
            (float) $profile->target_bmi,
        );

        $current = (float) $latest->weight_kg;
        $diff    = abs($current - $targetWeight);

        if ($diff < self::NEAR_TARGET_WEIGHT_KG) {
            return [
                'type'     => self::TYPE_INFO,
                'category' => self::CATEGORY_WEIGHT,
                'message'  => '目前已接近目標體重，可考慮以穩定維持為主，避免極端飲食。',
            ];
        }

        $diffStr = number_format($diff, 1);
        return [
            'type'     => self::TYPE_INFO,
            'category' => self::CATEGORY_WEIGHT,
            'message'  => "目前距離目標體重約 {$diffStr} kg。體重容易受水分、鈉含量、碳水攝取與排便狀況影響，建議觀察 7 日平均較準確。",
        ];
    }
}
