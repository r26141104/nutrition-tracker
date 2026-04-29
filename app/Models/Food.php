<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'brand',
    'store_id',
    'category',
    'serving_unit',
    'serving_size',
    'calories',
    'protein_g',
    'fat_g',
    'carbs_g',
    'is_system',
    'created_by_user_id',
])]
class Food extends Model
{
    /**
     * 顯式指定 table 名。
     * Laravel 預設把 model 名複數化推導 table，但 'food' 在英文是不可數名詞，
     * 預設 pluralizer 不會加 s，會推成 'food'，與我們 migration 的 'foods' 對不上。
     */
    protected $table = 'foods';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'serving_size' => 'decimal:2',
            'calories'     => 'integer',
            'protein_g'    => 'decimal:2',
            'fat_g'        => 'decimal:2',
            'carbs_g'      => 'decimal:2',
            'is_system'    => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * 視野（visibility）：系統食物所有人看得到，自訂食物只有 owner 看得到。
     * 未登入時 ($userId === null) 只看得到系統食物。
     */
    public function scopeVisibleTo(Builder $query, ?int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('is_system', true);
            if ($userId !== null) {
                $q->orWhere('created_by_user_id', $userId);
            }
        });
    }

    /**
     * 是否為登入者擁有的自訂食物（給 controller / resource 用）
     */
    public function isOwnedBy(?int $userId): bool
    {
        return ! $this->is_system
            && $userId !== null
            && $this->created_by_user_id === $userId;
    }
}
