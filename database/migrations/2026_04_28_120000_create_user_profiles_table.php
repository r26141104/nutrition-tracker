<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            // unique 確保一個 user 最多只有一筆 profile；cascadeOnDelete 在使用者被刪時連帶刪除
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('height_cm', 5, 2);    // 50.00 ~ 300.00
            $table->decimal('weight_kg', 5, 2);    // 20.00 ~ 500.00
            $table->decimal('target_bmi', 4, 2);   // 10.00 ~ 50.00
            $table->string('activity_level', 20);  // sedentary | light | moderate | active
            $table->string('goal_type', 20);       // lose_fat | gain_muscle | maintain
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
