<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 為 foods 加 source_type / confidence_level 欄位（修正四：標示資料來源與可信度）。
 *
 * 既有資料 backfill：
 *   - is_system = true  → source_type = system_estimate, confidence_level = medium
 *   - is_system = false → source_type = user_custom,    confidence_level = low
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            // system_estimate / user_custom / imported / official
            $table->string('source_type', 30)
                ->default('user_custom')
                ->after('is_system');

            // high / medium / low
            $table->string('confidence_level', 20)
                ->default('low')
                ->after('source_type');

            $table->index('source_type');
            $table->index('confidence_level');
        });

        // backfill 既有資料
        DB::table('foods')->where('is_system', true)->update([
            'source_type'      => 'system_estimate',
            'confidence_level' => 'medium',
        ]);
        DB::table('foods')->where('is_system', false)->update([
            'source_type'      => 'user_custom',
            'confidence_level' => 'low',
        ]);
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropIndex(['source_type']);
            $table->dropIndex(['confidence_level']);
            $table->dropColumn(['source_type', 'confidence_level']);
        });
    }
};
