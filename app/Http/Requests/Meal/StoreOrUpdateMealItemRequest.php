<?php

namespace App\Http\Requests\Meal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateMealItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'food_id'  => ['required', 'integer', Rule::exists('foods', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'food_id.required'  => '請指定食物',
            'food_id.integer'   => 'food_id 必須是整數',
            'food_id.exists'    => '指定的食物不存在',
            'quantity.required' => '請輸入份量',
            'quantity.numeric'  => '份量必須是數字',
            'quantity.min'      => '份量必須大於 0',
            'quantity.max'      => '份量不能超過 9999.99',
        ];
    }
}
