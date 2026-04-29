<?php

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class NearbyStoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lat'    => ['required', 'numeric', 'between:-90,90'],
            'lon'    => ['required', 'numeric', 'between:-180,180'],
            // 可調整搜尋半徑，最大 5 公里（避免砸 OSM）
            'radius' => ['nullable', 'integer', 'min:100', 'max:5000'],
        ];
    }
}
