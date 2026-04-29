<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'eaten_at',
    'meal_type',
    'note',
])]
class Meal extends Model
{
    /**
     * meal_type 合法值（給 FormRequest / Service 共用）。
     */
    public const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eaten_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }

    /**
     * 篩選某個使用者的 meals（給 Service 統一上權限用）。
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 篩選某一天（YYYY-MM-DD）的 meals。
     * 比對 eaten_at 的「日期部分」，不管時間。
     */
    public function scopeOfDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('eaten_at', $date);
    }

    /**
     * 是否為某個使用者擁有的 meal（給 controller / resource 用）。
     */
    public function isOwnedBy(?int $userId): bool
    {
        return $userId !== null && $this->user_id === $userId;
    }
}
