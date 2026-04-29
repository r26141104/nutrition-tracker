<?php

namespace App\Http\Controllers;

use App\Services\Nutrition\NutritionCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NutritionTargetController extends Controller
{
    public function __construct(
        private readonly NutritionCalculatorService $calculator,
    ) {}

    /**
     * GET /api/nutrition-target
     *
     * Controller 只負責：
     *   1) 拿登入者
     *   2) 檢查 profile 完整度
     *   3) 把計算結果包成 JSON
     * 所有公式/營養邏輯都在 NutritionCalculatorService。
     */
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if (! $profile) {
            return response()->json([
                'ready'    => false,
                'reason'   => 'profile_not_set',
                'message'  => '尚未設定個人資料',
                'warnings' => ['請先到「個人資料」填寫身高、體重、生日等基本資訊。'],
            ]);
        }

        if (! $profile->isComplete()) {
            return response()->json([
                'ready'    => false,
                'reason'   => 'profile_incomplete',
                'message'  => '個人資料尚未填齊',
                'warnings' => ['個人資料缺少生日或生理性別等欄位，請回到「個人資料」補齊後再試。'],
            ]);
        }

        return response()->json(array_merge(
            ['ready' => true],
            $this->calculator->generateNutritionTarget($profile),
        ));
    }
}
