<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // 餐點實際發生時間（含日期+時間）
            $table->dateTime('eaten_at');
            // breakfast | lunch | dinner | snack
            $table->string('meal_type', 20);
            $table->text('note')->nullable();
            $table->timestamps();

            // daily-summary / 列表查詢都會用 user_id + eaten_at，建複合索引
            $table->index(['user_id', 'eaten_at']);
            $table->index('meal_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
