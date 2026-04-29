<?php

namespace App\Http\Controllers;

use App\Http\Requests\Food\FoodImportRequest;
use App\Services\Food\FoodImportService;
use Illuminate\Http\JsonResponse;

class FoodImportController extends Controller
{
    public function __construct(
        private readonly FoodImportService $importService,
    ) {}

    /**
     * POST /api/foods/import/preview
     */
    public function preview(FoodImportRequest $request): JsonResponse
    {
        $rows = $this->parseFile($request);
        $result = $this->importService->previewImport($request->user(), $rows);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /api/foods/import
     */
    public function store(FoodImportRequest $request): JsonResponse
    {
        $rows = $this->parseFile($request);
        $result = $this->importService->importRows($request->user(), $rows);

        $message = "匯入完成，成功 {$result['imported_count']} 筆，失敗 {$result['failed_count']} 筆。";

        return response()->json([
            'data'    => $result,
            'message' => $message,
        ]);
    }

    /**
     * 共用：依副檔名分流 CSV / JSON 解析。
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseFile(FoodImportRequest $request): array
    {
        $file      = $request->file('file');
        $content   = (string) $file->get();
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return $extension === 'json'
            ? $this->importService->parseJson($content)
            : $this->importService->parseCsv($content);
    }
}
