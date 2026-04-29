<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 擴充 body_records，補上 BMI 看不出的身體量測（修正一的精神延伸）。
 *
 * 全部欄位 nullable，既有資料不需 backfill；新增紀錄時可選填。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('body_records', function (Blueprint $table) {
            // 體圍（公分）
            $table->decimal('waist_cm', 5, 1)->nullable()->after('bmi');
            $table->decimal('hip_cm',   5, 1)->nullable()->after('waist_cm');
            $table->decimal('chest_cm', 5, 1)->nullable()->after('hip_cm');
            $table->decimal('arm_cm',   5, 1)->nullable()->after('chest_cm');
            $table->decimal('thigh_cm', 5, 1)->nullable()->after('arm_cm');

            // 體脂率（%）/ 肌肉量（kg）
            $table->decimal('body_fat_percent', 4, 1)->nullable()->after('thigh_cm');
            $table->decimal('muscle_mass_kg',   5, 2)->nullable()->after('body_fat_percent');
        });
    }

    public function down(): void
    {
        Schema::table('body_records', function (Blueprint $table) {
            $table->dropColumn([
                'waist_cm', 'hip_cm', 'chest_cm', 'arm_cm', 'thigh_cm',
                'body_fat_percent', 'muscle_mass_kg',
            ]);
        });
    }
};
