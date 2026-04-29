<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 階段 I：連鎖店資料表
 *
 * stores 表代表「有營養標示資料的連鎖品牌」（麥當勞、肯德基、星巴克...）
 * 與 foods 表是 1:N 關係（store hasMany foods 當作菜單）
 *
 * 為什麼不直接用 foods.brand？
 *  - brand 是字串，沒法保證大小寫/別名一致（McDonald's vs 麥當勞 vs 麥當當）
 *  - stores 表有 osm_match_keywords 可以儲存匹配 OSM 結果用的關鍵字陣列
 *  - 之後可以加店家圖片、官網連結、官方營養標示連結
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);                  // 中文顯示名（麥當勞）
            $table->string('slug', 50)->unique();        // 英文 slug（mcdonalds），給 URL 用
            // fast_food | drink | convenience | rice_box | noodle | snack | other
            $table->string('category', 20);
            $table->string('logo_emoji', 10)->nullable(); // 🍔 🍟 ☕ ...

            // OSM matching：JSON array 存「可能在 OSM 看到的名稱」
            // 例：["麥當勞", "McDonald's", "麥當勞餐廳", "麥當當", "MCDONALDS"]
            // 後端用 LIKE 匹配 OSM 回的 amenity name
            $table->json('osm_match_keywords')->nullable();

            // 該店家菜單資料的可信度（連鎖店有官方標示 → high）
            $table->string('confidence_level', 20)->default('high');

            // 一句話介紹（給 UI 用）
            $table->string('description', 200)->nullable();

            $table->timestamps();

            $table->index('category');
        });

        // 在 foods 表加 store_id（nullable FK）
        Schema::table('foods', function (Blueprint $table) {
            $table->foreignId('store_id')
                ->nullable()
                ->after('brand')
                ->constrained('stores')
                ->nullOnDelete();

            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex(['store_id']);
            $table->dropColumn('store_id');
        });
        Schema::dropIfExists('stores');
    }
};
