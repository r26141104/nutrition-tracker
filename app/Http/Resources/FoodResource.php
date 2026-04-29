<?php

namespace App\Http\Resources;

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
            // 修正四：資料來源與可信度
            'source_type'        => $this->source_type,
            'confidence_level'   => $this->confidence_level,
            // 給前端方便判斷要不要顯示「編輯／刪除」按鈕
            'is_owned'           => $this->isOwnedBy($userId),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
