<?php

namespace App\Http\Controllers;

use App\Services\Recommendation\ExerciseRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseRecommendationController extends Controller
{
    public function __construct(
        private readonly ExerciseRecommendationService $exerciseService,
    ) {}

    /**
     * GET /api/exercise-recommendations
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->exerciseService->generateExerciseRecommendations($request->user());

        return response()->json(['data' => $data]);
    }
}
