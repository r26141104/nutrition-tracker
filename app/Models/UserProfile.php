<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'birthdate',
    'sex',
    'height_cm',
    'weight_kg',
    'target_bmi',
    'activity_level',
    'goal_type',
])]
class UserProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate'  => 'date',
            'height_cm'  => 'decimal:2',
            'weight_kg'  => 'decimal:2',
            'target_bmi' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 根據 birthdate 算目前年齡；尚未填則回 null。
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birthdate?->diffInYears(now());
    }

    /**
     * 是否已填齊計算 BMR/TDEE 所需的最少欄位。
     */
    public function isComplete(): bool
    {
        return $this->birthdate !== null
            && $this->sex !== null
            && $this->height_cm !== null
            && $this->weight_kg !== null
            && $this->activity_level !== null
            && $this->goal_type !== null;
    }
}
