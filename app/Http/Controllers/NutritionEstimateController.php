<?php

namespace App\Http\Controllers;

use App\Http\Requests\AI\NutritionEstimateRequest;
use App\Models\Food;
use App\Services\AI\NutritionEstimateService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class NutritionEstimateController extends Controller
{
    public function __construct(
        private readonly NutritionEstimateService $estimateService,
    ) {}

    /**
     * POST /api/foods/ai-estimate
     * 只估算、不存 DB（給 FoodEdit 表單填入用）
     */
    public function estimate(NutritionEstimateRequest $request): JsonResponse
    {
        try {
            $result = $this->estimateService->estimate((string) $request->validated('name'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'AI 估算失敗，請稍後再試'], 500);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/foods/ai-estimate-and-create
     * 估算 + 直接建立食物（標記為 ai_estimate / low confidence）
     * 給 MealEdit「找不到食物 → AI 估算 + 加入」用
     */
    public function estimateAndCreate(NutritionEstimateRequest $request): JsonResponse
    {
        $user = $request->user();
        $name = (string) $request->validated('name');

        try {
            $estimated = $this->estimateService->estimate($name);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'AI 估算失敗，請稍後再試'], 500);
        }

        // 直接寫入 foods 表，標記為 ai_estimate / low
        $food = Food::create([
            'name'               => $estimated['name'],
            'brand'              => null,
            'category'           => $estimated['category'],
            'serving_unit'       => $estimated['serving_unit'],
            'serving_size'       => $estimated['serving_size'],
            'calories'           => $estimated['calories'],
            'protein_g'          => $estimated['protein_g'],
            'fat_g'              => $estimated['fat_g'],
            'carbs_g'            => $estimated['carbs_g'],
            'is_system'          => false,
            'created_by_user_id' => $user->id,
            // 修正四 + 階段 H：AI 估算來源 + 低可信度
            'source_type'        => 'ai_estimate',
            'confidence_level'   => 'low',
        ]);

        return response()->json([
            'data' => [
                'food'      => [
                    'id'               => $food->id,
                    'name'             => $food->name,
                    'brand'            => $food->brand,
                    'category'         => $food->category,
                    'serving_unit'     => $food->serving_unit,
                    'serving_size'     => (float) $food->serving_size,
                    'calories'         => (int) $food->calories,
                    'protein_g'        => (float) $food->protein_g,
                    'fat_g'            => (float) $food->fat_g,
                    'carbs_g'          => (float) $food->carbs_g,
                    'is_system'        => false,
                    'source_type'      => $food->source_type,
                    'confidence_level' => $food->confidence_level,
                    'is_owned'         => true,
                ],
                'ai_notes'  => $estimated['notes'],
            ],
            'message' => "已用 AI 估算建立「{$estimated['name']}」（可信度低，建議確認後再儲存）",
        ], 201);
    }
}
