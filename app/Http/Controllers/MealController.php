<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meal\StoreOrUpdateMealItemRequest;
use App\Http\Requests\Meal\StoreOrUpdateMealRequest;
use App\Http\Resources\MealItemResource;
use App\Http\Resources\MealResource;
use App\Models\MealItem;
use App\Services\Meal\MealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MealController extends Controller
{
    public function __construct(
        private readonly MealService $mealService,
    ) {}

    /**
     * GET /api/meals?date=YYYY-MM-DD&meal_type=lunch
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $meals = $this->mealService->listOfDate(
            userId:   $request->user()->id,
            date:     $request->query('date'),
            mealType: $request->query('meal_type'),
        );

        return MealResource::collection($meals);
    }

    /**
     * POST /api/meals
     */
    public function store(StoreOrUpdateMealRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $meal = $this->mealService->create($request->user(), $data, $items);

        return response()->json([
            'meal' => new MealResource($meal),
        ], 201);
    }

    /**
     * GET /api/meals/{meal}
     */
    public function show(Request $request, int $meal): JsonResponse
    {
        $mealModel = $this->mealService->findVisibleOrFail($meal, $request->user()->id);

        return response()->json([
            'meal' => new MealResource($mealModel),
        ]);
    }

    /**
     * PUT /api/meals/{meal}
     */
    public function update(StoreOrUpdateMealRequest $request, int $meal): JsonResponse
    {
        $mealModel = $this->mealService->findVisibleOrFail($meal, $request->user()->id);

        $data  = $request->validated();
        // 區分「沒傳 items」(=> null, 不動 items) 與「傳了空陣列」(=> [], 清空 items)
        $items = $request->has('items') ? ($data['items'] ?? []) : null;
        unset($data['items']);

        $mealModel = $this->mealService->update($mealModel, $request->user(), $data, $items);

        return response()->json([
            'meal' => new MealResource($mealModel),
        ]);
    }

    /**
     * DELETE /api/meals/{meal}
     */
    public function destroy(Request $request, int $meal): JsonResponse
    {
        $mealModel = $this->mealService->findVisibleOrFail($meal, $request->user()->id);
        $this->mealService->delete($mealModel, $request->user());

        return response()->json(['message' => '已刪除']);
    }

    /**
     * POST /api/meals/{meal}/items
     */
    public function addItem(StoreOrUpdateMealItemRequest $request, int $meal): JsonResponse
    {
        $mealModel = $this->mealService->findVisibleOrFail($meal, $request->user()->id);

        $item = $this->mealService->addItem(
            $mealModel,
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'item' => new MealItemResource($item),
        ], 201);
    }

    /**
     * PUT /api/meals/{meal}/items/{item}
     */
    public function updateItem(StoreOrUpdateMealItemRequest $request, int $meal, int $item): JsonResponse
    {
        $mealModel = $this->mealService->findVisibleOrFail($meal, $request->user()->id);

        $itemModel = MealItem::find($item);
        if (! $itemModel) {
            return response()->json(['message' => '找不到此項目'], 404);
        }

        $itemModel = $this->mealService->updateItem(
            $mealModel,
            $itemModel,
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'item' => new MealItemResource($itemModel),
        ]);
    }

    /**
     * DELETE /api/meals/{meal}/items/{item}
     */
    public function deleteItem(Request $request, int $meal, int $item): JsonResponse
    {
        $mealModel = $this->mealService->findVisibleOrFail($meal, $request->user()->id);

        $itemModel = MealItem::find($item);
        if (! $itemModel) {
            return response()->json(['message' => '找不到此項目'], 404);
        }

        $this->mealService->deleteItem($mealModel, $itemModel, $request->user());

        return response()->json(['message' => '已刪除']);
    }

    /**
     * GET /api/meals/daily-summary?date=YYYY-MM-DD
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $summary = $this->mealService->dailySummary(
            userId: $request->user()->id,
            date:   $request->query('date'),
        );

        return response()->json($summary);
    }
}
