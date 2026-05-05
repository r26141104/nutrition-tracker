<?php

namespace App\Http\Controllers;

use App\Http\Requests\Food\StoreOrUpdateFoodRequest;
use App\Http\Resources\FoodResource;
use App\Models\Food;
use App\Services\Food\FoodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FoodController extends Controller
{
    public function __construct(
        private readonly FoodService $foodService,
    ) {}

    /**
     * GET /api/foods?search=&category=&per_page=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $foods = $this->foodService->search(
            search:   $request->query('search'),
            category: $request->query('category'),
            userId:   $request->user()?->id,
            perPage:  (int) $request->query('per_page', 20),
        );

        return FoodResource::collection($foods);
    }

    /**
     * POST /api/foods
     */
    public function store(StoreOrUpdateFoodRequest $request): JsonResponse
    {
        $food = $this->foodService->create($request->validated(), $request->user());

        return response()->json([
            'food' => new FoodResource($food),
        ], 201);
    }

    /**
     * GET /api/foods/{food}
     * 注意：不靠 route model binding，用 Service 處理可見性檢查。
     */
    public function show(Request $request, int $food): JsonResponse
    {
        $foodModel = $this->foodService->findVisibleOrFail($food, $request->user()?->id);

        return response()->json([
            'food' => new FoodResource($foodModel),
        ]);
    }

    /**
     * PUT/PATCH /api/foods/{food}
     */
    public function update(StoreOrUpdateFoodRequest $request, int $food): JsonResponse
    {
        $foodModel = $this->foodService->findVisibleOrFail($food, $request->user()->id);
        $foodModel = $this->foodService->update($foodModel, $request->validated(), $request->user());

        return response()->json([
            'food' => new FoodResource($foodModel),
        ]);
    }

    /**
     * DELETE /api/foods/{food}
     */
    public function destroy(Request $request, int $food): JsonResponse
    {
        $foodModel = $this->foodService->findVisibleOrFail($food, $request->user()->id);
        try {
            $this->foodService->delete($foodModel, $request->user());
        } catch (\RuntimeException $e) {
            // 例如：被飲食紀錄引用，回 409 Conflict
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => '已刪除']);
    }
}
