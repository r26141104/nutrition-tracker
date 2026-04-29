<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stores\NearbyStoresRequest;
use App\Services\Stores\NearbyStoresService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * 附近店家 API（呼叫 OpenStreetMap Overpass）。
 *
 * GET /api/nearby-stores?lat=X&lon=Y&radius=Z
 */
class NearbyStoreController extends Controller
{
    public function __construct(
        private readonly NearbyStoresService $service,
    ) {}

    public function index(NearbyStoresRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $results = $this->service->findNearby(
                lat:    (float) $validated['lat'],
                lon:    (float) $validated['lon'],
                radius: isset($validated['radius']) ? (int) $validated['radius'] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'data' => $results,
            'meta' => [
                'count'  => count($results),
                'radius' => $validated['radius'] ?? 1000,
                'note'   => '資料來源：OpenStreetMap（社群維護，可能不完整）。連鎖店有完整菜單，其他店家可用 AI 估算。',
            ],
        ]);
    }
}
