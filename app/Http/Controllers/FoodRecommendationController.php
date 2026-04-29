<?php

namespace App\Http\Controllers;

use App\Services\Recommendation\FoodRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodRecommendationController extends Controller
{
    public function __construct(
        private readonly FoodRecommendationService $foodRecommendationService,
    ) {}

    /**
     * GET /api/food-recommendations
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->foodRecommendationService->generateFoodRecommendations($request->user());

        return response()->json(['data' => $data]);
    }
}
