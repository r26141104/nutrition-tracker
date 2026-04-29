<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * GET /api/dashboard/today?date=YYYY-MM-DD
     * date 可選；不傳就用今天。
     */
    public function today(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getTodayDashboard(
            $request->user(),
            $request->query('date'),
        );

        return response()->json(['data' => $data]);
    }
}
