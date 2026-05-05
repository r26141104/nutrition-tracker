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
    // 先 truncate 避免 timeout 殘留問題，再重新跑 bulk insert
    set_time_limit(120);
    try {
        \Illuminate\Support\Facades\Artisan::call('import:tfnd', ['--truncate' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $official = \App\Models\Food::where('source_type', 'official')->count();
        return response()->json([
            'status'         => 'success',
            'official_count' => $official,
            'output'         => $output,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
            'trace'   => collect($e->getTrace())->take(5)->toArray(),
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
