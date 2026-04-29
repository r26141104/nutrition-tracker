<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('brand', 50)->nullable();
            // rice_box | noodle | convenience | fast_food | drink | snack | other
            $table->string('category', 20);
            $table->string('serving_unit', 20);          // 份 / 杯 / 顆 / g / ml ...
            $table->decimal('serving_size', 8, 2);       // 1 份、500 ml ...
            $table->unsignedInteger('calories');         // kcal per serving
            $table->decimal('protein_g', 6, 2);
            $table->decimal('fat_g', 6, 2);
            $table->decimal('carbs_g', 6, 2);
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index('category');
            $table->index('is_system');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
