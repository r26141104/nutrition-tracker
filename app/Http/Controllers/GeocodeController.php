<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stores\GeocodeRequest;
use App\Services\Stores\GeocodingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * 地址 → 經緯度（OSM Nominatim）
 *
 * GET /api/geocode?q=台北車站
 */
class GeocodeController extends Controller
{
    public function __construct(
        private readonly GeocodingService $service,
    ) {}

    public function index(GeocodeRequest $request): JsonResponse
    {
        $q = (string) $request->validated()['q'];

        try {
            $result = $this->service->geocode($q);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $result,
            'meta' => [
                'note' => '資料來源：OpenStreetMap Nominatim',
            ],
        ]);
    }
}
