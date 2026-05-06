<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('exercise_name', 50);          // 例：跑步、騎自行車
            $table->decimal('duration_min', 6, 1);        // 持續分鐘數
            $table->unsignedInteger('calories_burned');   // 消耗的卡路里
            $table->date('logged_at');                    // 記錄日期（給 dashboard 「今日」用）
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_logs');
    }
};
