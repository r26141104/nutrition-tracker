<?php

namespace App\Http\Controllers;

use App\Services\Report\WeeklyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyReportController extends Controller
{
    public function __construct(
        private readonly WeeklyReportService $weeklyReportService,
    ) {}

    /**
     * GET /api/weekly-report/current
     */
    public function current(Request $request): JsonResponse
    {
        $data = $this->weeklyReportService->generateWeeklyReport($request->user());

        return response()->json(['data' => $data]);
    }
}
