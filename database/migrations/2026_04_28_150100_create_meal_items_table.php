<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();

            // meal 被刪 → items 跟著被刪
            $table->foreignId('meal_id')
                ->constrained('meals')
                ->cascadeOnDelete();

            // food 被刪 → 保留 meal_item，food_id 變 null
            // 配合 snapshot 欄位（calories/PFC），歷史紀錄不會壞
            $table->foreignId('food_id')
                ->nullable()
                ->constrained('foods')
                ->nullOnDelete();

            // 使用者吃了幾份（搭配 Food.serving_size 的單位語意）
            // 例如 Food = 雞腿便當 1 份 750kcal，user 吃 1.5 份 → quantity=1.5
            $table->decimal('quantity', 8, 2);

            // === Snapshot 欄位（皆為「每 1 單位」的數值，沿用 foods 表語意） ===
            // 設計原因：未來 Food 被改 / 被刪不影響歷史紀錄
            // 計算總攝取時：snapshot * quantity
            $table->unsignedInteger('calories');
            $table->decimal('protein_g', 6, 2);
            $table->decimal('fat_g', 6, 2);
            $table->decimal('carbs_g', 6, 2);

            $table->timestamps();

            $table->index('meal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_items');
    }
};
