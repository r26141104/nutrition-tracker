<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BodyRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseRecommendationController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\FoodImportController;
use App\Http\Controllers\FoodRecommendationController;
use App\Http\Controllers\FoodVisionController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\GoalProgressController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\NearbyStoreController;
use App\Http\Controllers\NutritionEstimateController;
use App\Http\Controllers\NutritionTargetController;
use App\Http\Controllers\ExerciseLogController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StreakController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WaterIntakeController;
use App\Http\Controllers\WeeklyReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| 所有路徑會被自動加上 /api 前綴。
*/

// 公開
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// === TEMP DEBUG: 一鍵匯入衛福部官方資料（之後可刪） ===
// 用法：瀏覽器打開 /api/_debug/tfnd-run-import 就會跑匯入
Route::get('/_debug/tfnd-status', function () {
    $official = \App\Models\Food::where('source_type', 'official')->count();
    $total = \App\Models\Food::count();
    return response()->json([
        'official_count'   => $official,
        'total_count'      => $total,
        'tfnd_json_exists' => file_exists(database_path('data/tfnd_official.json')),
        'tfnd_json_size'   => file_exists(database_path('data/tfnd_official.json'))
            ? filesize(database_path('data/tfnd_official.json'))
            : 0,
    ]);
});

Route::get('/_debug/tfnd-run-import', function () {
    // 直接 inline 跑 import，繞過 Artisan command 註冊問題
    set_time_limit(180);
    try {
        $jsonPath = database_path('data/tfnd_official.json');
        if (! file_exists($jsonPath)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'JSON 檔不存在：' . $jsonPath,
            ], 500);
        }

        $records = json_decode(file_get_contents($jsonPath), true);
        if (! is_array($records)) {
            return response()->json(['status' => 'error', 'message' => 'JSON 格式錯誤'], 500);
        }

        // 先清掉舊的 official 食物，避免 timeout 殘留
        $deletedCount = \App\Models\Food::where('source_type', 'official')->delete();

        $catMap = [
            '穀物類' => 'rice_box', '澱粉類' => 'rice_box',
            '堅果及種子類' => 'snack', '水果類' => 'snack',
            '糖類' => 'snack', '糕餅點心類' => 'snack',
            '乳品類' => 'drink', '飲料類' => 'drink', '嗜好性飲料類' => 'drink',
            '調理加工食品類' => 'fast_food', '加工調理食品類' => 'fast_food',
            '加工調理食品及其他類' => 'fast_food',
        ];

        $now = now();
        $toInsert = [];
        foreach ($records as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) continue;
            $cat = (string) ($r['category'] ?? '');
            $mappedCat = $catMap[$cat] ?? 'other';
            // 把俗名加進 brand 讓搜尋能 match（'aliases' 是 JSON 內的俗名欄位）
            $aliases = trim((string) ($r['aliases'] ?? ''));
            $brand = '衛福部';
            if ($aliases !== '') {
                $brand = '衛福部 · ' . mb_substr($aliases, 0, 40);
            }
            $toInsert[] = [
                'name' => $name,
                'brand' => $brand,
                'category' => $mappedCat,
                'serving_unit' => 'g',
                'serving_size' => 100,
                'calories' => (int) max(0, $r['calories'] ?? 0),
                'protein_g' => round((float) max(0, $r['protein_g'] ?? 0), 1),
                'fat_g' => round((float) max(0, $r['fat_g'] ?? 0), 1),
                'carbs_g' => round((float) max(0, $r['carbs_g'] ?? 0), 1),
                'is_system' => true,
                'created_by_user_id' => null,
                'source_type' => 'official',
                'confidence_level' => 'high',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = 0;
        foreach (array_chunk($toInsert, 500) as $chunk) {
            \App\Models\Food::insert($chunk);
            $inserted += count($chunk);
        }

        return response()->json([
            'status' => 'success',
            'deleted_old' => $deletedCount,
            'inserted' => $inserted,
            'total_official' => \App\Models\Food::where('source_type', 'official')->count(),
            'message' => '完成！打開食物資料庫應該就能看到「✓ 衛福部」綠色標籤',
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
            'file'    => $e->getFile() . ':' . $e->getLine(),
        ], 500);
    }
});

// 需登入
Route::middleware('auth:sanctum')->group(function () {
    // 會員
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user',    [AuthController::class, 'user']);

    // 個人資料
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::put('/profile', [UserProfileController::class, 'update']);

    // 每日營養目標
    Route::get('/nutrition-target', [NutritionTargetController::class, 'show']);

    // Dashboard 今日攝取總覽
    Route::get('/dashboard/today', [DashboardController::class, 'today']);

    // 食物資料庫
    // 注意：自訂路由必須在 apiResource 之前，否則會被當成 /{food}
    Route::post('foods/import/preview',           [FoodImportController::class, 'preview']);
    Route::post('foods/import',                   [FoodImportController::class, 'store']);
    Route::post('foods/vision/analyze',           [FoodVisionController::class, 'analyze']);
    // 階段 H：AI 估算營養
    Route::post('foods/ai-estimate',              [NutritionEstimateController::class, 'estimate']);
    Route::post('foods/ai-estimate-and-create',   [NutritionEstimateController::class, 'estimateAndCreate']);
    Route::apiResource('foods', FoodController::class)->whereNumber('food');

    // 體重紀錄
    // 注意：trend 必須在 apiResource 之前，否則會被當成 /{bodyRecord} 處理
    Route::get('body-records/trend', [BodyRecordController::class, 'trend']);
    Route::apiResource('body-records', BodyRecordController::class)->whereNumber('body_record');

    // 運動消耗紀錄
    Route::get('exercise-logs',           [ExerciseLogController::class, 'index']);
    Route::post('exercise-logs',          [ExerciseLogController::class, 'store']);
    Route::delete('exercise-logs/{id}',   [ExerciseLogController::class, 'destroy'])->whereNumber('id');

    // 目標進度與達標時間估算
    Route::get('goal-progress', [GoalProgressController::class, 'show']);

    // 每週飲食 + 體重報告
    Route::get('weekly-report/current', [WeeklyReportController::class, 'current']);

    // 簡單運動建議
    Route::get('exercise-recommendations', [ExerciseRecommendationController::class, 'index']);

    // 簡單餐點建議
    Route::get('food-recommendations', [FoodRecommendationController::class, 'index']);

    // 階段 I：連鎖店 + 附近店家 + 地址查詢
    // 注意：generate-menu 必須在 /{store} 之前，避免被路由當成 store id
    Route::post('stores/generate-menu', [StoreController::class, 'generateMenu']);
    Route::get('stores',                [StoreController::class, 'index']);
    Route::get('stores/{store}',        [StoreController::class, 'show'])->whereNumber('store');
    Route::get('nearby-stores',         [NearbyStoreController::class, 'index']);
    Route::get('geocode',               [GeocodeController::class, 'index']);

    // 水分攝取（階段 G）
    Route::prefix('water-intake')->group(function () {
        Route::get('today',     [WaterIntakeController::class, 'today']);
        Route::post('add',      [WaterIntakeController::class, 'add']);
        Route::delete('today',  [WaterIntakeController::class, 'reset']);
        Route::get('history',   [WaterIntakeController::class, 'history']);
    });

    // 連續紀錄 + 徽章（階段 G）
    Route::get('streak', [StreakController::class, 'index']);

    // 個人化分析（階段 F）
    Route::prefix('analysis')->group(function () {
        Route::get('calorie-adjustment',           [AnalysisController::class, 'calorieAdjustment']);
        Route::get('nutrition-gap',                [AnalysisController::class, 'nutritionGap']);
        Route::get('protein-distribution',         [AnalysisController::class, 'proteinDistribution']);
        Route::get('weight-fluctuation',           [AnalysisController::class, 'weightFluctuation']);
        Route::get('diet-quality-score',           [AnalysisController::class, 'dietQualityScore']);
        Route::get('weekly-correction-suggestions',[AnalysisController::class, 'weeklyCorrectionSuggestions']);
    });

    // 飲食紀錄
    // 注意：daily-summary 必須在 /{meal} 之前註冊，否則會被當成 meal id
    Route::get   ('/meals/daily-summary',          [MealController::class, 'dailySummary']);
    Route::get   ('/meals',                        [MealController::class, 'index']);
    Route::post  ('/meals',                        [MealController::class, 'store']);
    Route::get   ('/meals/{meal}',                 [MealController::class, 'show'])->whereNumber('meal');
    Route::put   ('/meals/{meal}',                 [MealController::class, 'update'])->whereNumber('meal');
    Route::delete('/meals/{meal}',                 [MealController::class, 'destroy'])->whereNumber('meal');
    Route::post  ('/meals/{meal}/items',           [MealController::class, 'addItem'])->whereNumber('meal');
    Route::put   ('/meals/{meal}/items/{item}',    [MealController::class, 'updateItem'])->whereNumber(['meal', 'item']);
    Route::delete('/meals/{meal}/items/{item}',    [MealController::class, 'deleteItem'])->whereNumber(['meal', 'item']);
});
