<?php

namespace App\Services\BodyRecord;

use App\Models\BodyRecord;
use App\Models\User;
use App\Services\Nutrition\NutritionCalculatorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 體重紀錄 service。
 *
 * 設計原則：
 *   - BMI 一律由後端依使用者個人資料 height_cm 計算，前端不可決定
 *   - 同一個使用者同一天只能有一筆紀錄（DB 層 unique 索引保護 + Service updateOrCreate）
 *   - 權限檢查集中在 canView / canUpdate / canDelete
 *   - 缺 height_cm 時丟可被 controller 翻成 422 的 ValidationException
 */
class BodyRecordService
{
    /** 趨勢圖支援的天數區間 */
    public const TREND_ALLOWED_DAYS = [7, 30, 90];

    /** 修正六：體重紀錄少於這個筆數時不下趨勢結論 */
    private const INSUFFICIENT_RECORDS = 3;

    /** 修正六：體重紀錄少於這個天數時不下趨勢結論 */
    private const INSUFFICIENT_DAYS = 7;

    public function __construct(
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * BMI = 體重(kg) / 身高(m)²
     * 結果保留 2 位小數。
     */
    public function calculateBmi(float $weightKg, float $heightCm): float
    {
        if ($heightCm <= 0) {
            throw new \InvalidArgumentException('身高必須大於 0');
        }
        $heightM = $heightCm / 100;
        return round($weightKg / ($heightM * $heightM), 2);
    }

    /**
     * 體重趨勢資料：最近 N 天紀錄（升冪，方便畫圖）+ 摘要欄位。
     * days 只接受 7 / 30 / 90，其他值一律當作 30。
     *
     * @return array<string, mixed>
     */
    public function getTrend(User $user, int $days = 30): array
    {
        if (! in_array($days, self::TREND_ALLOWED_DAYS, true)) {
            $days = 30;
        }

        $startDate = now()->subDays($days - 1)->startOfDay()->toDateString();

        // 區間內的紀錄（升冪：舊 → 新，給折線圖用）
        $records = BodyRecord::query()
            ->where('user_id', $user->id)
            ->where('record_date', '>=', $startDate)
            ->orderBy('record_date')
            ->orderBy('id')
            ->get();

        // 最新紀錄（不限區間，可能在區間外）
        $latest = BodyRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->first();

        // 目標體重（依個人資料 height_cm + target_bmi 算）
        $targetWeight = null;
        $profile = $user->profile;
        if ($profile !== null && $profile->height_cm !== null && $profile->target_bmi !== null) {
            $targetWeight = $this->calculator->calculateTargetWeight(
                (float) $profile->height_cm,
                (float) $profile->target_bmi,
            );
        }

        return [
            'days'             => $days,
            'target_weight_kg' => $targetWeight,
            'latest_weight_kg' => $latest ? (float) $latest->weight_kg : null,
            'latest_bmi'       => $latest ? (float) $latest->bmi : null,
            'records'          => $records->map(static fn (BodyRecord $r) => [
                'record_date' => $r->record_date?->toDateString(),
                'weight_kg'   => (float) $r->weight_kg,
                'bmi'         => (float) $r->bmi,
            ])->values()->all(),
            // 修正六：附加 insights（7 日平均 / 30 日變化 / 比較訊息 / 資料是否足夠）
            'insights'         => $this->buildInsights($user),
        ];
    }

    /**
     * 修正六：產出體重趨勢洞察（給前端顯示用）。
     *
     * @return array<string, mixed>
     */
    private function buildInsights(User $user): array
    {
        $allRecords = BodyRecord::query()
            ->where('user_id', $user->id)
            ->orderBy('record_date')
            ->orderBy('id')
            ->get();

        $totalCount = $allRecords->count();

        // 資料完全沒有
        if ($totalCount === 0) {
            return [
                'has_sufficient_data'    => false,
                'seven_day_average_kg'   => null,
                'thirty_day_change_kg'   => null,
                'today_vs_seven_day'     => null,
                'message'                => '尚未建立體重紀錄，建議至少記錄 7 天後再觀察體重趨勢。',
            ];
        }

        // 紀錄筆數不足
        if ($totalCount < self::INSUFFICIENT_RECORDS) {
            return [
                'has_sufficient_data'    => false,
                'seven_day_average_kg'   => null,
                'thirty_day_change_kg'   => null,
                'today_vs_seven_day'     => null,
                'message'                => "目前體重紀錄較少（{$totalCount} 筆），建議累積更多資料後再觀察趨勢。",
            ];
        }

        // 7 日平均（包含今天往前推 7 天的紀錄）
        $sevenDaysAgo = now()->subDays(6)->startOfDay()->toDateString();
        $recentSeven  = $allRecords->filter(fn (BodyRecord $r) => $r->record_date?->toDateString() >= $sevenDaysAgo);

        // 紀錄天數跨度不足（從第一筆到最新的距離）
        $first = $allRecords->first();
        $last  = $allRecords->last();
        $daysSpan = $first->record_date->diffInDays($last->record_date) + 1;

        if ($daysSpan < self::INSUFFICIENT_DAYS) {
            $sevenDayAvg = $recentSeven->isEmpty() ? null
                : round($recentSeven->avg(fn ($r) => (float) $r->weight_kg), 1);
            return [
                'has_sufficient_data'    => false,
                'seven_day_average_kg'   => $sevenDayAvg,
                'thirty_day_change_kg'   => null,
                'today_vs_seven_day'     => null,
                'message'                => "目前體重紀錄跨度只有 {$daysSpan} 天，體重容易受水分、鈉含量、碳水攝取與排便狀況影響，建議觀察 7 日平均。",
            ];
        }

        // === 資料足夠，可以做完整分析 ===
        $sevenDayAvg = $recentSeven->isEmpty() ? null
            : round($recentSeven->avg(fn ($r) => (float) $r->weight_kg), 1);

        // 30 日變化：30 天前最接近的紀錄 vs 最新
        $thirtyDaysAgo = now()->subDays(30)->startOfDay()->toDateString();
        $thirtyDayBaseline = $allRecords
            ->filter(fn ($r) => $r->record_date?->toDateString() <= $thirtyDaysAgo)
            ->last(); // 30 天前或更早最接近的一筆
        if ($thirtyDayBaseline === null) {
            // 30 天前沒紀錄 → 用最早一筆當基準
            $thirtyDayBaseline = $first;
        }
        $thirtyDayChange = round((float) $last->weight_kg - (float) $thirtyDayBaseline->weight_kg, 1);

        // 今日 vs 7 日平均的對照（修正六核心：避免只看單日）
        $todayDate = now()->toDateString();
        $todayRecord = $allRecords->filter(fn ($r) => $r->record_date?->toDateString() === $todayDate)->first();

        $todayVsAverage = null;
        $message = '資料足夠，可以觀察體重趨勢。';

        if ($todayRecord !== null && $sevenDayAvg !== null) {
            $todayWeight = (float) $todayRecord->weight_kg;
            $diff = round($todayWeight - $sevenDayAvg, 1);

            $todayVsAverage = [
                'today_weight_kg'   => $todayWeight,
                'seven_day_avg_kg'  => $sevenDayAvg,
                'difference_kg'     => $diff,
            ];

            // 今日比 7 日平均高 + 7 日平均比 30 日前低 → 短期波動但長期下降
            if ($diff > 0.3 && $thirtyDayChange < 0) {
                $message = '今日體重比近 7 日平均高，但近 30 日整體仍在下降，可能只是短期水分波動。';
            } elseif ($diff < -0.3 && $thirtyDayChange > 0) {
                $message = '今日體重比近 7 日平均低，但近 30 日整體仍在上升，建議觀察長期趨勢。';
            } elseif (abs($diff) <= 0.3) {
                $message = '今日體重與近 7 日平均接近，目前看起來穩定。';
            } elseif ($diff > 0) {
                $message = '今日體重比近 7 日平均略高，單日波動屬正常，建議觀察整週平均。';
            } else {
                $message = '今日體重比近 7 日平均略低，單日波動屬正常，建議觀察整週平均。';
            }
        }

        return [
            'has_sufficient_data'    => true,
            'seven_day_average_kg'   => $sevenDayAvg,
            'thirty_day_change_kg'   => $thirtyDayChange,
            'today_vs_seven_day'     => $todayVsAverage,
            'message'                => $message,
        ];
    }

    /**
     * 取得某個使用者的所有體重紀錄，依 record_date 由新到舊。
     *
     * @return Collection<int, BodyRecord>
     */
    public function getUserBodyRecords(User $user): Collection
    {
        return BodyRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * 找到並驗證可見性；只有 owner 看得到。
     */
    public function findOwnedOrFail(int $id, User $user): BodyRecord
    {
        $record = BodyRecord::query()->where('user_id', $user->id)->find($id);
        if (! $record) {
            throw new NotFoundHttpException('找不到此體重紀錄');
        }
        return $record;
    }

    /**
     * 新增體重紀錄。
     * 同一天已有紀錄 → 更新那一筆（updateOrCreate）。
     *
     * @param  array<string, mixed>  $data  含 record_date / weight_kg / note
     */
    public function createOrUpdateRecord(User $user, array $data): BodyRecord
    {
        $heightCm = $this->getHeightCmOrFail($user);
        $weightKg = (float) $data['weight_kg'];
        $bmi      = $this->calculateBmi($weightKg, $heightCm);

        // 不接受前端傳 bmi 或 user_id
        return BodyRecord::updateOrCreate(
            [
                'user_id'     => $user->id,
                'record_date' => $data['record_date'],
            ],
            [
                'weight_kg'        => $weightKg,
                'bmi'              => $bmi,
                'note'             => $data['note'] ?? null,
                // 階段 G：身體量測（缺則寫 null）
                'waist_cm'         => $data['waist_cm']         ?? null,
                'hip_cm'           => $data['hip_cm']           ?? null,
                'chest_cm'         => $data['chest_cm']         ?? null,
                'arm_cm'           => $data['arm_cm']           ?? null,
                'thigh_cm'         => $data['thigh_cm']         ?? null,
                'body_fat_percent' => $data['body_fat_percent'] ?? null,
                'muscle_mass_kg'   => $data['muscle_mass_kg']   ?? null,
            ],
        );
    }

    /**
     * 更新已存在的體重紀錄。BMI 會重算（如果 weight_kg 改變或身高有變）。
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRecord(BodyRecord $record, User $user, array $data): BodyRecord
    {
        $this->ensureCanUpdate($record, $user);

        $heightCm = $this->getHeightCmOrFail($user);

        // 如果 record_date 變更，要確保不會撞到別筆已存在的紀錄
        $newDate = $data['record_date'] ?? $record->record_date->toDateString();
        $oldDate = $record->record_date->toDateString();
        if ($newDate !== $oldDate) {
            $exists = BodyRecord::query()
                ->where('user_id', $user->id)
                ->where('record_date', $newDate)
                ->where('id', '!=', $record->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'record_date' => ['該日期已有另一筆體重紀錄，請改填其他日期或直接編輯那筆。'],
                ]);
            }
        }

        $weightKg = (float) ($data['weight_kg'] ?? $record->weight_kg);
        $bmi      = $this->calculateBmi($weightKg, $heightCm);

        $record->update([
            'record_date'      => $newDate,
            'weight_kg'        => $weightKg,
            'bmi'              => $bmi,
            'note'             => array_key_exists('note',             $data) ? $data['note']             : $record->note,
            // 階段 G：身體量測（沒給就保留原值）
            'waist_cm'         => array_key_exists('waist_cm',         $data) ? $data['waist_cm']         : $record->waist_cm,
            'hip_cm'           => array_key_exists('hip_cm',           $data) ? $data['hip_cm']           : $record->hip_cm,
            'chest_cm'         => array_key_exists('chest_cm',         $data) ? $data['chest_cm']         : $record->chest_cm,
            'arm_cm'           => array_key_exists('arm_cm',           $data) ? $data['arm_cm']           : $record->arm_cm,
            'thigh_cm'         => array_key_exists('thigh_cm',         $data) ? $data['thigh_cm']         : $record->thigh_cm,
            'body_fat_percent' => array_key_exists('body_fat_percent', $data) ? $data['body_fat_percent'] : $record->body_fat_percent,
            'muscle_mass_kg'   => array_key_exists('muscle_mass_kg',   $data) ? $data['muscle_mass_kg']   : $record->muscle_mass_kg,
        ]);

        return $record->fresh();
    }

    /**
     * 刪除體重紀錄。
     */
    public function deleteRecord(BodyRecord $record, User $user): void
    {
        $this->ensureCanDelete($record, $user);
        $record->delete();
    }

    // ============================================================
    // 權限檢查
    // ============================================================

    public function canView(BodyRecord $record, User $user): bool
    {
        return $record->user_id === $user->id;
    }

    public function canUpdate(BodyRecord $record, User $user): bool
    {
        return $record->user_id === $user->id;
    }

    public function canDelete(BodyRecord $record, User $user): bool
    {
        return $record->user_id === $user->id;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureCanUpdate(BodyRecord $record, User $user): void
    {
        if (! $this->canUpdate($record, $user)) {
            throw new AuthorizationException('您沒有權限修改此體重紀錄');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureCanDelete(BodyRecord $record, User $user): void
    {
        if (! $this->canDelete($record, $user)) {
            throw new AuthorizationException('您沒有權限刪除此體重紀錄');
        }
    }

    // ============================================================
    // 內部工具
    // ============================================================

    /**
     * 從使用者個人資料抓 height_cm。
     * 沒有 profile 或 height_cm 為 null → 丟 422，附清楚錯誤訊息。
     *
     * @throws ValidationException
     */
    private function getHeightCmOrFail(User $user): float
    {
        $profile = $user->profile;
        if ($profile === null || $profile->height_cm === null) {
            throw ValidationException::withMessages([
                'profile' => ['請先完成個人資料設定，才能計算 BMI。'],
            ]);
        }
        return (float) $profile->height_cm;
    }
}
