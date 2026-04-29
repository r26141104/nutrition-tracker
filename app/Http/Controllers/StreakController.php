<?php

namespace App\Http\Controllers;

use App\Services\Streak\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    public function __construct(
        private readonly StreakService $streakService,
    ) {}

    /** GET /api/streak */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->streakService->generateStreakInfo($request->user()),
        ]);
    }
}
