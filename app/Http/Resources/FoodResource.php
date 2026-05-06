<?php

namespace App\Http\Resources;

use App\Services\Food\CommonServingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'brand'              => $this->brand,
            'category'           => $this->category,
            'serving_unit'       => $this->serving_unit,
            'serving_size'       => (float) $this->serving_size,
            'calories'           => (int) $this->calories,
            'protein_g'          => (float) $this->protein_g,
            'fat_g'              => (float) $this->fat_g,
            'carbs_g'            => (float) $this->carbs_g,
            'is_system'          => (bool) $this->is_system,
            'created_by_user_id' => $this->created_by_user_id,
            'source_type'        => $this->source_type,
            'confidence_level'   => $this->confidence_level,
            'is_owned'           => $this->isOwnedBy($userId),
            // 常見份量推薦（自動依食物名稱猜，例如「蔥抓餅 → 1 張 90g」）
            'serving_presets'    => CommonServingService::guess((string) $this->name),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
