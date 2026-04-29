<?php

namespace App\Http\Controllers;

use App\Services\Analysis\CalorieAdjustmentService;
use App\Services\Analysis\DietQualityScoreService;
use App\Services\Analysis\NutritionGapService;
use App\Services\Analysis\ProteinDistributionService;
use App\Services\Analysis\WeeklyCorrectionSuggestionService;
use App\Services\Analysis\WeightFluctuationInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 個人化分析統一 controller。
 * 6 個 endpoint 都很薄，邏輯都在各自 service。
 */
class AnalysisController extends Controller
{
    public function __construct(
        private readonly CalorieAdjustmentService $calorieAdjustmentService,
        private readonly NutritionGapService $nutritionGapService,
        private readonly ProteinDistributionService $proteinDistributionService,
        private readonly WeightFluctuationInsightService $weightFluctuationService,
        private readonly DietQualityScoreService $dietQualityService,
        private readonly WeeklyCorrectionSuggestionService $weeklyCorrectionService,
    ) {}

    /** GET /api/analysis/calorie-adjustment */
    public function calorieAdjustment(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->calorieAdjustmentService->generateAnalysis($request->user()),
        ]);
    }

    /** GET /api/analysis/nutrition-gap */
    public function nutritionGap(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->nutritionGapService->generateNutritionGapAnalysis($request->user()),
        ]);
    }

    /** GET /api/analysis/protein-distribution */
    public function proteinDistribution(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->proteinDistributionService->generateProteinDistributionAnalysis($request->user()),
        ]);
    }

    /** GET /api/analysis/weight-fluctuation */
    public function weightFluctuation(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->weightFluctuationService->generateInsight($request->user()),
        ]);
    }

    /** GET /api/analysis/diet-quality-score */
    public function dietQualityScore(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dietQualityService->generateDietQualityScore($request->user()),
        ]);
    }

    /** GET /api/analysis/weekly-correction-suggestions */
    public function weeklyCorrectionSuggestions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->weeklyCorrectionService->generateWeeklyCorrectionSuggestion($request->user()),
        ]);
    }
}
