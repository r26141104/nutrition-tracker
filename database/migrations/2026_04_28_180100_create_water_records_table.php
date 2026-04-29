<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 水分攝取紀錄。
 * 一個 user 一天一筆累計值，「+250 ml / +500 ml」按鈕都更新同一筆。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->date('record_date');
            $table->unsignedInteger('amount_ml')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'record_date']);
            $table->index(['user_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_records');
    }
};
