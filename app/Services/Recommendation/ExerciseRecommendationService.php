<?php

namespace App\Services\Recommendation;

use App\Models\User;

/**
 * 簡單運動建議 service。
 *
 * 設計原則（嚴格遵守）：
 *   - 建議只能作為一般健康與生活建議，不做醫療診斷
 *   - 不提供高風險、過度激烈或違反一般健身常識的建議
 *   - 不依疾病狀況開處方
 *   - 提醒使用者「依自身狀況調整」、「有疼痛或不適請諮詢專業人員」
 *
 * 邏輯純規則（不查 DB），主軸由 goal_type 決定，
 * 有氧的時長/強度依 activity_level 微調。
 */
class ExerciseRecommendationService
{
    /** 通用注意事項（不論 goal_type 都附上） */
    private const NOTES = [
        '運動建議僅供一般參考，請依照自身狀況調整。',
        '若有疼痛、暈眩或特殊健康狀況，建議先詢問專業人員。',
        '建議從低強度逐步增加，避免一開始就過度訓練。',
        '充足睡眠與水分對運動恢復同樣重要。',
        // 修正五：避免使用者把運動消耗當「可額外進食的額度」
        '運動消耗熱量僅為估算，實際消耗會因強度、體重、體能與身體狀況不同而有相當大的變動。',
        '不建議把運動消耗完全當作可以額外進食的熱量額度。',
        '減脂或增肌仍應以長期飲食紀錄、活動量與體重趨勢一起觀察，運動只是其中一環。',
    ];

    /**
     * 主入口：產出完整運動建議。
     *
     * @return array<string, mixed>
     */
    public function generateExerciseRecommendations(User $user): array
    {
        $profile = $user->profile;

        // 沒個人資料 → fallback
        if ($profile === null || ! $profile->isComplete()) {
            return [
                'goal_type'           => null,
                'main_focus'          => '請先完成個人資料設定，才能依照目標提供運動建議。',
                'cardio'              => [],
                'resistance_training' => [],
                'weekly_plan'         => [],
                'notes'               => ['請先完成個人資料設定。'],
            ];
        }

        $goalType      = (string) $profile->goal_type;
        $activityLevel = (string) $profile->activity_level;

        return [
            'goal_type'           => $goalType,
            'main_focus'          => $this->recommendByGoalType($goalType),
            'cardio'              => $this->recommendCardio($goalType, $activityLevel),
            'resistance_training' => $this->recommendResistanceTraining($goalType),
            'weekly_plan'         => $this->recommendWeeklyPlan($goalType),
            'notes'               => self::NOTES,
        ];
    }

    /**
     * 依 goal_type 給主要訓練方向（一句話）。
     */
    public function recommendByGoalType(string $goalType): string
    {
        return match ($goalType) {
            'lose_fat'    => '減脂建議以有氧搭配肌力訓練為主，搭配日常步行與飲食控制效果更佳。',
            'gain_muscle' => '增肌建議以阻力訓練為主軸，搭配充足蛋白質與休息以利肌肉恢復。',
            'maintain'    => '維持模式建議有氧與肌力均衡安排，以穩定生活作息為主。',
            default       => '建議依照個人興趣與體能，安排規律的有氧與肌力訓練。',
        };
    }

    /**
     * 有氧建議（時長/強度依 activity_level 微調）。
     *
     * @return array<int, string>
     */
    public function recommendCardio(string $goalType, string $activityLevel): array
    {
        $intensity = match ($activityLevel) {
            'sedentary' => '每次 20～30 分鐘起步，依體能逐步增加。',
            'light'     => '每次約 25～35 分鐘。',
            'moderate'  => '每次約 30～40 分鐘。',
            'active'    => '每次約 40 分鐘以上，可分段進行。',
            default     => '每次約 25～35 分鐘。',
        };

        return match ($goalType) {
            'lose_fat' => [
                '每週 3～5 次中等強度有氧（快走、慢跑、腳踏車或游泳）。',
                $intensity,
                '可額外增加日常步行，例如以樓梯取代電梯、提前一站下車。',
            ],
            'gain_muscle' => [
                '有氧維持輕中度即可，每週 1～2 次。',
                '每次 20～30 分鐘，避免過度有氧影響肌肉恢復。',
            ],
            'maintain' => [
                '每週 2～3 次有氧運動。',
                $intensity,
                '重點在於維持規律，不必追求高強度。',
            ],
            default => [
                '每週 2～3 次規律有氧運動。',
                $intensity,
            ],
        };
    }

    /**
     * 肌力訓練建議。
     *
     * @return array<int, string>
     */
    public function recommendResistanceTraining(string $goalType): array
    {
        return match ($goalType) {
            'lose_fat' => [
                '每週 2～3 次全身肌力訓練。',
                '可從深蹲、伏地挺身、划船、棒式等基礎動作開始。',
                '每次選 4～6 個動作，每個動作 2～3 組、每組 10～15 下。',
            ],
            'gain_muscle' => [
                '每週 3～4 次阻力訓練，可分為「上肢日」與「下肢日」。',
                '聚焦複合動作：深蹲、硬舉、臥推、划船、肩推。',
                '每組 6～12 下、3～4 組，重量逐步漸進。',
                '蛋白質建議攝取約 1.6～2.0 g/kg 體重，並注意睡眠與休息日。',
            ],
            'maintain' => [
                '每週 2 次肌力訓練。',
                '可選擇全身循環或分上下肢交替進行。',
                '重量適中，重點是維持運動習慣。',
            ],
            default => [
                '每週 2 次基礎肌力訓練。',
                '從自身體重動作開始建立穩定動作模式。',
            ],
        };
    }

    /**
     * 簡單一週訓練安排。
     *
     * @return array<int, array{day: string, suggestion: string}>
     */
    public function recommendWeeklyPlan(string $goalType): array
    {
        return match ($goalType) {
            'lose_fat' => [
                ['day' => '週一', 'suggestion' => '快走或慢跑 30 分鐘'],
                ['day' => '週二', 'suggestion' => '全身肌力訓練 30 分鐘'],
                ['day' => '週三', 'suggestion' => '腳踏車或游泳 30 分鐘'],
                ['day' => '週四', 'suggestion' => '休息或輕度伸展'],
                ['day' => '週五', 'suggestion' => '全身肌力訓練 30 分鐘'],
                ['day' => '週六', 'suggestion' => '長距離快走或郊外活動 40 分鐘'],
                ['day' => '週日', 'suggestion' => '休息'],
            ],
            'gain_muscle' => [
                ['day' => '週一', 'suggestion' => '上肢肌力（推 + 拉）45 分鐘'],
                ['day' => '週二', 'suggestion' => '輕中度有氧 20 分鐘'],
                ['day' => '週三', 'suggestion' => '下肢肌力（深蹲 / 硬舉）45 分鐘'],
                ['day' => '週四', 'suggestion' => '休息'],
                ['day' => '週五', 'suggestion' => '全身或弱項補強 45 分鐘'],
                ['day' => '週六', 'suggestion' => '輕度活動或休息'],
                ['day' => '週日', 'suggestion' => '休息'],
            ],
            'maintain' => [
                ['day' => '週一', 'suggestion' => '中等強度有氧 30 分鐘'],
                ['day' => '週二', 'suggestion' => '全身肌力訓練 30 分鐘'],
                ['day' => '週三', 'suggestion' => '休息或伸展'],
                ['day' => '週四', 'suggestion' => '中等強度有氧 30 分鐘'],
                ['day' => '週五', 'suggestion' => '全身肌力訓練 30 分鐘'],
                ['day' => '週六', 'suggestion' => '休閒運動（散步、騎車、爬山）'],
                ['day' => '週日', 'suggestion' => '休息'],
            ],
            default => [
                ['day' => '週一', 'suggestion' => '快走或慢跑 30 分鐘'],
                ['day' => '週三', 'suggestion' => '全身肌力訓練 30 分鐘'],
                ['day' => '週五', 'suggestion' => '快走或騎車 30 分鐘'],
            ],
        };
    }
}
