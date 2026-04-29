<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'category',
    'logo_emoji',
    'osm_match_keywords',
    'confidence_level',
    'description',
])]
class Store extends Model
{
    protected $table = 'stores';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'osm_match_keywords' => 'array',
        ];
    }

    /**
     * 該店家的菜單品項（foods.store_id 串過來）
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(Food::class, 'store_id');
    }
}
