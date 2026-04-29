<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BodyRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'record_date'       => $this->record_date?->toDateString(),
            'weight_kg'         => (float) $this->weight_kg,
            'bmi'               => (float) $this->bmi,
            'note'              => $this->note,
            // 階段 G：身體量測補完（nullable）
            'waist_cm'          => $this->waist_cm         !== null ? (float) $this->waist_cm         : null,
            'hip_cm'            => $this->hip_cm           !== null ? (float) $this->hip_cm           : null,
            'chest_cm'          => $this->chest_cm         !== null ? (float) $this->chest_cm         : null,
            'arm_cm'            => $this->arm_cm           !== null ? (float) $this->arm_cm           : null,
            'thigh_cm'          => $this->thigh_cm         !== null ? (float) $this->thigh_cm         : null,
            'body_fat_percent'  => $this->body_fat_percent !== null ? (float) $this->body_fat_percent : null,
            'muscle_mass_kg'    => $this->muscle_mass_kg   !== null ? (float) $this->muscle_mass_kg   : null,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
