<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 階段 J（後續）：stores 加 default_opening_hours
 *
 * 為什麼要加這個欄位？
 *   OSM（OpenStreetMap）的 opening_hours tag 在台灣資料覆蓋率不高，
 *   很多連鎖店在 OSM 上根本沒填營業時間。
 *
 *   解法：在我們的 stores 表給連鎖店一個「預設營業時間」，
 *   當 OSM 沒資料時，前端顯示這個預設值（依然標示為一般時間，僅供參考）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // 預設營業時間，例：「每日 10:00 - 22:00」
            $table->string('default_opening_hours', 100)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('default_opening_hours');
        });
    }
};
