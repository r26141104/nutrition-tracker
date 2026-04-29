<?php

namespace App\Http\Controllers;

use App\Services\Water\WaterIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaterIntakeController extends Controller
{
    public function __construct(
        private readonly WaterIntakeService $waterService,
    ) {}

    /** GET /api/water-intake/today */
    public function today(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->waterService->getTodayStatus($request->user()),
        ]);
    }

    /**
     * POST /api/water-intake/add
     * body: { amount_ml: int (1~3000) }
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_ml' => ['required', 'integer', 'min:1', 'max:3000'],
        ]);

        $this->waterService->addIntake($request->user(), (int) $validated['amount_ml']);

        return response()->json([
            'data'    => $this->waterService->getTodayStatus($request->user()),
            'message' => "已記錄 {$validated['amount_ml']} ml",
        ]);
    }

    /** DELETE /api/water-intake/today — 重設今日水量為 0 */
    public function reset(Request $request): JsonResponse
    {
        $this->waterService->resetToday($request->user());

        return response()->json([
            'data'    => $this->waterService->getTodayStatus($request->user()),
            'message' => '已重設今日水分紀錄',
        ]);
    }

    /** GET /api/water-intake/history?days=7 */
    public function history(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        if (! in_array($days, [7, 14, 30], true)) $days = 7;

        return response()->json([
            'data' => [
                'days'    => $days,
                'records' => $this->waterService->getHistory($request->user(), $days),
            ],
        ]);
    }
}
