<?php

namespace App\Services\Analysis;

use App\Models\User;
use App\Services\Nutrition\NutritionCalculatorService;
use App\Services\Report\WeeklyReportService;

/**
 * 每週修正建議。
 *
 * 整合每週報告 + 既有分析，產出最多 3 條 actionable 建議。
 */
class WeeklyCorrectionSuggestionService
{
    /** 飲食紀錄少於這個天數視為資料不足 */
    private const MIN_LOGGED_DAYS = 3;

    /** 蛋白質達成率低於這個值視為偏低 */
    private const PROTEIN_LOW_RATIO = 0.7;

    /** 脂肪超標天數視為偏高 */
    private const FAT_OVER_DAYS_THRESHOLD = 3;

    /** 最多產 N 個 action items */
    private const MAX_ACTION_ITEMS = 3;

    private const DISCLAIMER = '這些建議根據近期紀錄產生，僅供一般參考，並非醫療診斷。';

    public function __construct(
        private readonly WeeklyReportService $weeklyReportService,
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateWeeklyCorrectionSuggestion(User $user): array
    {
        $report = $this->weeklyReportService->generateWeeklyReport($user);
        $loggedDays = (int) ($report['logged_meal_days'] ?? 0);

        // === 資料不足 ===
        if ($loggedDays < self::MIN_LOGGED_DAYS) {
            return [
                'has_enough_data' => false,
                'strengths'       => [],
                'issues'          => ['本週飲食紀錄不足 3 天，無法產生完整修正建議。'],
                'action_items'    => ['下週優先穩定飲食紀錄，至少連續記錄 5～7 天。'],
                'disclaimer'      => self::DISCLAIMER,
            ];
        }

        $strengths    = [];
        $issues       = [];
        $actionItems  = [];

        // === 1. 紀錄完整度 ===
        if ($loggedDays >= 5) {
            $strengths[] = "本週有 {$loggedDays} 天完成飲食紀錄。";
        }

        // === 2. 平均熱量 ===
        $profile = $user->profile;
        if ($profile !== null && $profile->isComplete()) {
            $target = $this->calculator->generateNutritionTarget($profile);
            $targetCal = (int) $target['daily_calories'];
            $avgCal = (int) ($report['average_calories'] ?? 0);

            if ($targetCal > 0) {
                $calRatio = $avgCal / $targetCal;

                if ($calRatio >= 0.9 && $calRatio <= 1.1) {
                    $strengths[] = '本週平均熱量接近目標。';
                } elseif ($calRatio > 1.15) {
                    $issues[]      = '本週平均熱量略高於目標。';
                    $actionItems[] = '下週可考慮減少高熱量點心或含糖飲料。';
                } elseif ($calRatio < 0.85) {
                    $issues[]      = '本週平均熱量略低於目標，注意不要長期攝取不足。';
                    $actionItems[] = '下週確保三餐都有足夠份量。';
                }
            }

            // === 3. 蛋白質達成率 ===
            $targetP = (int) $target['protein_g'];
            $avgP = (float) ($report['average_protein_g'] ?? 0);
            if ($targetP > 0) {
                $pRatio = $avgP / $targetP;
                if ($pRatio < self::PROTEIN_LOW_RATIO) {
                    $issues[]      = '本週蛋白質平均達成率偏低。';
                    $actionItems[] = '下週優先讓早餐增加蛋白質（雞蛋、豆漿、希臘優格等）。';
                } elseif ($pRatio >= 0.9) {
                    $strengths[] = '本週蛋白質攝取接近目標。';
                }
            }
        }

        // === 4. 熱量超標天數 ===
        $overDays = (int) ($report['over_target_days'] ?? 0);
        if ($overDays >= self::FAT_OVER_DAYS_THRESHOLD) {
            $issues[]      = "本週有 {$overDays} 天熱量超標。";
            $actionItems[] = '下週注意每餐份量，避免單日大量超標；單日波動屬正常。';
        }

        // === 5. 體重資料 ===
        $weightChange = $report['weight_change_kg'] ?? null;
        if ($weightChange === null) {
            $actionItems[] = '至少記錄 5 天體重，以便觀察趨勢。';
        }

        // 限制 action_items 不超過 3 條（避免使用者無法執行）
        $actionItems = array_slice(array_values(array_unique($actionItems)), 0, self::MAX_ACTION_ITEMS);

        // 沒有 issues 但也沒 strengths 時的 fallback
        if (empty($strengths) && empty($issues)) {
            $strengths[] = '本週紀錄已建立基礎，可繼續累積資料。';
        }

        // 沒有 action items 時給個鼓勵性建議
        if (empty($actionItems)) {
            $actionItems[] = '保持目前的飲食與紀錄習慣，下週繼續觀察體重趨勢。';
        }

        return [
            'has_enough_data' => true,
            'strengths'       => $strengths,
            'issues'          => $issues,
            'action_items'    => $actionItems,
            'disclaimer'      => self::DISCLAIMER,
        ];
    }
}
