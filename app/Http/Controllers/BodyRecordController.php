<?php

namespace App\Http\Controllers;

use App\Http\Requests\BodyRecord\StoreOrUpdateBodyRecordRequest;
use App\Http\Resources\BodyRecordResource;
use App\Services\BodyRecord\BodyRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BodyRecordController extends Controller
{
    public function __construct(
        private readonly BodyRecordService $bodyRecordService,
    ) {}

    /**
     * GET /api/body-records
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $records = $this->bodyRecordService->getUserBodyRecords($request->user());
        return BodyRecordResource::collection($records);
    }

    /**
     * GET /api/body-records/trend?days={7|30|90}
     * 注意：路由必須在 apiResource 之前註冊，否則會被當成 /{bodyRecord} 處理
     */
    public function trend(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $data = $this->bodyRecordService->getTrend($request->user(), $days);

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/body-records
     * 同一天已有紀錄 → 更新那筆。
     */
    public function store(StoreOrUpdateBodyRecordRequest $request): JsonResponse
    {
        $record = $this->bodyRecordService->createOrUpdateRecord(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'body_record' => new BodyRecordResource($record),
        ], 201);
    }

    /**
     * GET /api/body-records/{bodyRecord}
     */
    public function show(Request $request, int $bodyRecord): JsonResponse
    {
        $record = $this->bodyRecordService->findOwnedOrFail($bodyRecord, $request->user());

        return response()->json([
            'body_record' => new BodyRecordResource($record),
        ]);
    }

    /**
     * PUT /api/body-records/{bodyRecord}
     */
    public function update(StoreOrUpdateBodyRecordRequest $request, int $bodyRecord): JsonResponse
    {
        $record  = $this->bodyRecordService->findOwnedOrFail($bodyRecord, $request->user());
        $updated = $this->bodyRecordService->updateRecord(
            $record,
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'body_record' => new BodyRecordResource($updated),
        ]);
    }

    /**
     * DELETE /api/body-records/{bodyRecord}
     */
    public function destroy(Request $request, int $bodyRecord): JsonResponse
    {
        $record = $this->bodyRecordService->findOwnedOrFail($bodyRecord, $request->user());
        $this->bodyRecordService->deleteRecord($record, $request->user());

        return response()->json(['message' => '已刪除']);
    }
}
