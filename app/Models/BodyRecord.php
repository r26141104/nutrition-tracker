<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'record_date',
    'weight_kg',
    'bmi',
    'note',
    // 階段 G：身體量測補完
    'waist_cm',
    'hip_cm',
    'chest_cm',
    'arm_cm',
    'thigh_cm',
    'body_fat_percent',
    'muscle_mass_kg',
])]
class BodyRecord extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record_date'      => 'date',
            'weight_kg'        => 'decimal:2',
            'bmi'              => 'decimal:2',
            // 階段 G
            'waist_cm'         => 'decimal:1',
            'hip_cm'           => 'decimal:1',
            'chest_cm'         => 'decimal:1',
            'arm_cm'           => 'decimal:1',
            'thigh_cm'         => 'decimal:1',
            'body_fat_percent' => 'decimal:1',
            'muscle_mass_kg'   => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
