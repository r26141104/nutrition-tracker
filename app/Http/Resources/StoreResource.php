<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'category'         => $this->category,
            'logo_emoji'       => $this->logo_emoji,
            'description'      => $this->description,
            'confidence_level' => $this->confidence_level,
            // 菜單品項數，列表用
            'menu_items_count' => (int) ($this->menu_items_count ?? $this->menuItems()->count()),
            // 詳情頁會帶菜單，列表頁不帶
            'menu_items' => $this->whenLoaded('menuItems', fn () =>
                FoodResource::collection($this->menuItems)
            ),
        ];
    }
}
