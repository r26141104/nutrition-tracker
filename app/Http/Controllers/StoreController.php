<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stores\GenerateMenuRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\AI\StoreMenuGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 連鎖店瀏覽 API。
 *
 * GET /api/stores         → 列出所有連鎖店（含菜單品項數）
 * GET /api/stores/{store} → 顯示某連鎖店 + 完整菜單
 */
class StoreController extends Controller
{
    /**
     * 全部連鎖店（給 demo / 瀏覽用）。
     */
    public function index(Request $request): JsonResponse
    {
        $stores = Store::query()
            ->withCount('menuItems')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => StoreResource::collection($stores),
        ]);
    }

    /**
     * 單一連鎖店 + 菜單。
     */
    public function show(int $store): JsonResponse
    {
        $storeModel = Store::query()
            ->with(['menuItems' => fn ($q) => $q->orderBy('calories')])
            ->withCount('menuItems')
            ->findOrFail($store);

        return response()->json([
            'data' => new StoreResource($storeModel),
        ]);
    }

    /**
     * AI 推測菜單：給定任何店家名稱，AI 推斷類型並生成 15-20 個常見品項。
     *
     * POST /api/stores/generate-menu  body: { name: string }
     * Response: { data: { store_id, store_name, store_slug, menu_items_count } }
     */
    public function generateMenu(
        GenerateMenuRequest $request,
        StoreMenuGenerationService $service,
    ): JsonResponse {
        try {
            $validated = $request->validated();
            $store = $service->generateForStoreName(
                $validated['name'],
                $validated['hint'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'data' => [
                'store_id'         => $store->id,
                'store_slug'       => $store->slug,
                'store_name'       => $store->name,
                'menu_items_count' => $store->menuItems()->count(),
            ],
        ]);
    }
}
