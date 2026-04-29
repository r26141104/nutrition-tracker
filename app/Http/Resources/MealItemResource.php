<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // food 可能被刪 → relation 為 null。前端拿 food_summary === null 就顯示「(已刪除的食物)」
        $foodSummary = $this->food
            ? [
                'id'           => $this->food->id,
                'name'         => $this->food->name,
                'brand'        => $this->food->brand,
                'category'     => $this->food->category,
                'serving_unit' => $this->food->serving_unit,
                'serving_size' => (float) $this->food->serving_size,
            ]
            : null;

        return [
            'id'           => $this->id,
            'meal_id'      => $this->meal_id,
            'food_id'      => $this->food_id,
            'food_summary' => $foodSummary,
            'quantity'     => (float) $this->quantity,

            // === Snapshot：每 1 單位的數值（與 Food 表語意相同） ===
            'snapshot'     => [
                'calories'  => (int) $this->calories,
                'protein_g' => (float) $this->protein_g,
                'fat_g'     => (float) $this->fat_g,
                'carbs_g'   => (float) $this->carbs_g,
            ],

            // === Total：snapshot × quantity（直接用 Model accessor） ===
            'total'        => [
                'calories'  => $this->total_calories,
                'protein_g' => $this->total_protein_g,
                'fat_g'     => $this->total_fat_g,
                'carbs_g'   => $this->total_carbs_g,
            ],

            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
