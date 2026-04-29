<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'meal_id',
    'food_id',
    'quantity',
    'calories',
    'protein_g',
    'fat_g',
    'carbs_g',
])]
class MealItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity'  => 'decimal:2',
            'calories'  => 'integer',
            'protein_g' => 'decimal:2',
            'fat_g'     => 'decimal:2',
            'carbs_g'   => 'decimal:2',
        ];
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    /**
     * Food 可能被刪 → food_id 為 null，這時要顯示「（已刪除的食物）」之類。
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    /**
     * === 計算屬性：snapshot × quantity ===
     * 給 Resource / Service 算當餐總攝取用，不要存 DB。
     */
    protected function totalCalories(): Attribute
    {
        return Attribute::get(
            fn () => (int) round($this->calories * (float) $this->quantity)
        );
    }

    protected function totalProteinG(): Attribute
    {
        return Attribute::get(
            fn () => round((float) $this->protein_g * (float) $this->quantity, 2)
        );
    }

    protected function totalFatG(): Attribute
    {
        return Attribute::get(
            fn () => round((float) $this->fat_g * (float) $this->quantity, 2)
        );
    }

    protected function totalCarbsG(): Attribute
    {
        return Attribute::get(
            fn () => round((float) $this->carbs_g * (float) $this->quantity, 2)
        );
    }
}
