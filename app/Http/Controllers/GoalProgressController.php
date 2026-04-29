<?php

namespace App\Http\Controllers;

use App\Services\Goal\GoalProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalProgressController extends Controller
{
    public function __construct(
        private readonly GoalProgressService $goalProgressService,
    ) {}

    /**
     * GET /api/goal-progress
     */
    public function show(Request $request): JsonResponse
    {
        $data = $this->goalProgressService->generateGoalProgress($request->user());

        return response()->json(['data' => $data]);
    }
}
