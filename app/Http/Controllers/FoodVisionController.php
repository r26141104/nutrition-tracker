<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vision\AnalyzeFoodPhotoRequest;
use App\Services\Vision\FoodVisionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class FoodVisionController extends Controller
{
    public function __construct(
        private readonly FoodVisionService $visionService,
    ) {}

    /**
     * POST /api/foods/vision/analyze
     */
    public function analyze(AnalyzeFoodPhotoRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $content = (string) $file->get();

        try {
            $result = $this->visionService->analyze($request->user(), $content);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => '照片辨識失敗，請稍後再試',
            ], 500);
        }

        return response()->json(['data' => $result]);
    }
}
