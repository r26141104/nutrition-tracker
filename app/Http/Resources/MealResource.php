<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 注意：呼叫端應該用 ->load('items.food') 預載，避免 N+1
        $items = $this->items ?? collect();

        // 整餐合計（所有 items 的 total_* 加總）
        $totals = [
            'calories'  => (int) $items->sum->total_calories,
            'protein_g' => round((float) $items->sum->total_protein_g, 2),
            'fat_g'     => round((float) $items->sum->total_fat_g, 2),
            'carbs_g'   => round((float) $items->sum->total_carbs_g, 2),
        ];

        return [
            'id'         => $this->id,
            'user_id'    => $this->user_id,
            'eaten_at'   => $this->eaten_at?->toIso8601String(),
            'meal_type'  => $this->meal_type,
            'note'       => $this->note,
            'items'      => MealItemResource::collection($items),
            'totals'     => $totals,
            'item_count' => $items->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
