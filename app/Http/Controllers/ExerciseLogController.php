<?php

namespace App\Http\Controllers;

use App\Models\ExerciseLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 運動消耗紀錄 API。
 *
 * GET    /api/exercise-logs?date=YYYY-MM-DD  → 取得某日紀錄
 * POST   /api/exercise-logs                  → 新增一筆
 * DELETE /api/exercise-logs/{id}             → 刪除
 */
class ExerciseLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $logs = ExerciseLog::where('user_id', $request->user()->id)
            ->where('logged_at', $date)
            ->orderByDesc('id')
            ->get();

        $totalBurned = (int) $logs->sum('calories_burned');

        return response()->json([
            'data'         => $logs,
            'date'         => $date,
            'total_burned' => $totalBurned,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exercise_name'   => ['required', 'string', 'max:50'],
            'duration_min'    => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'calories_burned' => ['required', 'integer', 'min:1', 'max:99999'],
            'logged_at'       => ['nullable', 'date'],
            'note'            => ['nullable', 'string', 'max:200'],
        ]);

        $log = ExerciseLog::create([
            'user_id'         => $request->user()->id,
            'exercise_name'   => $validated['exercise_name'],
            'duration_min'    => $validated['duration_min'],
            'calories_burned' => $validated['calories_burned'],
            'logged_at'       => $validated['logged_at'] ?? Carbon::today()->toDateString(),
            'note'            => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => '已儲存運動紀錄',
            'data'    => $log,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $log = ExerciseLog::where('user_id', $request->user()->id)->findOrFail($id);
        $log->delete();
        return response()->json(['message' => '已刪除']);
    }
}
