<?php

namespace App\Services\Water;

use App\Models\User;
use App\Models\WaterRecord;
use Illuminate\Support\Carbon;

/**
 * 水分攝取追蹤 service。
 *
 * 設計：一個 user 一天一筆累計值。+250 / +500 按鈕都疊加到同一筆上。
 * 目標 = 體重 (kg) × 30 ml（一般保健建議；無體重資料則預設 2000）。
 */
class WaterIntakeService
{
    /** 沒體重資料時的預設目標（ml） */
    private const DEFAULT_TARGET_ML = 2000;

    /** 體重每 kg 的水分目標（ml） */
    private const ML_PER_KG = 30.0;

    /**
     * 取得今日水分攝取狀態。
     *
     * @return array<string, mixed>
     */
    public function getTodayStatus(User $user): array
    {
        $today = now()->toDateString();
        $record = $this->findTodayRecord($user, $today);

        $totalMl = (int) ($record?->amount_ml ?? 0);
        $targetMl = $this->calculateTarget($user);

        return [
            'date'             => $today,
            'total_ml'         => $totalMl,
            'target_ml'        => $targetMl,
            'progress_percent' => $targetMl > 0 ? round($totalMl / $targetMl * 100, 1) : 0.0,
            'reached_target'   => $targetMl > 0 && $totalMl >= $targetMl,
        ];
    }

    /**
     * 增加水分（+ amount_ml 到今日紀錄）。
     */
    public function addIntake(User $user, int $amountMl): WaterRecord
    {
        $today = now()->toDateString();
        $existing = $this->findTodayRecord($user, $today);

        if ($existing !== null) {
            $existing->amount_ml = (int) $existing->amount_ml + $amountMl;
            $existing->save();
            return $existing;
        }

        return WaterRecord::create([
            'user_id'     => $user->id,
            'record_date' => $today,
            'amount_ml'   => $amountMl,
        ]);
    }

    /**
     * 直接設定今日總量（給「修正」用）。
     */
    public function setIntake(User $user, int $amountMl): WaterRecord
    {
        $today = now()->toDateString();
        $amountMl = max(0, $amountMl);
        $existing = $this->findTodayRecord($user, $today);

        if ($existing !== null) {
            $existing->amount_ml = $amountMl;
            $existing->save();
            return $existing;
        }

        return WaterRecord::create([
            'user_id'     => $user->id,
            'record_date' => $today,
            'amount_ml'   => $amountMl,
        ]);
    }

    /**
     * 重設今日水量為 0（給「我點錯了」用）。
     */
    public function resetToday(User $user): void
    {
        WaterRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('record_date', now()->toDateString())
            ->delete();
    }

    /**
     * 安全的「找今日紀錄」—— 用 whereDate 不管儲存格式都能比對到。
     * （SQLite 的 date cast 可能把 '2026-04-29' 存成 '2026-04-29 00:00:00'，
     *  普通 where 字串比對會錯過，whereDate 不會）
     */
    private function findTodayRecord(User $user, string $todayString): ?WaterRecord
    {
        return WaterRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('record_date', $todayString)
            ->first();
    }

    /**
     * 取最近 N 天歷史（升冪，給趨勢圖用）。
     *
     * @return array<int, array{record_date: string, amount_ml: int, target_ml: int}>
     */
    public function getHistory(User $user, int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->toDateString();

        $records = WaterRecord::query()
            ->where('user_id', $user->id)
            ->where('record_date', '>=', $startDate)
            ->orderBy('record_date')
            ->get();

        $target = $this->calculateTarget($user);

        return $records->map(fn (WaterRecord $r) => [
            'record_date' => $r->record_date?->toDateString(),
            'amount_ml'   => (int) $r->amount_ml,
            'target_ml'   => $target,
        ])->all();
    }

    /**
     * 依使用者體重算每日目標（沒資料用預設）。
     */
    public function calculateTarget(User $user): int
    {
        $profile = $user->profile;
        if ($profile === null || $profile->weight_kg === null) {
            return self::DEFAULT_TARGET_ML;
        }
        return (int) round((float) $profile->weight_kg * self::ML_PER_KG);
    }
}
