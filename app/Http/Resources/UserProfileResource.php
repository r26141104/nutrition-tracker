<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'birthdate'      => $this->birthdate?->toDateString(),  // 'YYYY-MM-DD'
            'sex'            => $this->sex,
            'age'            => $this->age,                          // 計算屬性
            'height_cm'      => (float) $this->height_cm,
            'weight_kg'      => (float) $this->weight_kg,
            'target_bmi'     => (float) $this->target_bmi,
            'activity_level' => $this->activity_level,
            'goal_type'      => $this->goal_type,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
