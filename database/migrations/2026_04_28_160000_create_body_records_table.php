<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_records', function (Blueprint $table) {
            $table->id();

            // user 被刪 → 體重紀錄一起 cascade
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('record_date');

            // 體重（公斤）
            $table->decimal('weight_kg', 5, 2);

            // BMI 由後端 BodyRecordService::calculateBmi 寫入，前端不可決定
            $table->decimal('bmi', 5, 2);

            $table->text('note')->nullable();

            $table->timestamps();

            // 同一個使用者同一天只能有一筆紀錄（updateOrCreate 仰賴此索引）
            $table->unique(['user_id', 'record_date']);

            // 列表通常依 user + 日期反序查詢
            $table->index(['user_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_records');
    }
};
