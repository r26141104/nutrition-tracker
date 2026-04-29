<?php

namespace App\Http\Requests\Meal;

use App\Models\Meal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateMealRequest extends FormRequest
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
            'eaten_at'           => ['required', 'date'],
            'meal_type'          => ['required', 'string', Rule::in(Meal::MEAL_TYPES)],
            'note'               => ['nullable', 'string', 'max:500'],

            // items 在 store 是 optional（允許先建空餐再補食物）
            // 在 update 時如果傳了，就視為「整批替換」
            'items'              => ['sometimes', 'array'],
            'items.*.food_id'    => ['required', 'integer', Rule::exists('foods', 'id')],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'eaten_at.required'       => '請輸入用餐時間',
            'eaten_at.date'           => '用餐時間格式不正確',
            'meal_type.required'      => '請選擇餐別',
            'meal_type.in'            => '餐別必須是 breakfast / lunch / dinner / snack 之一',
            'note.max'                => '備註最多 500 字',
            'items.array'             => 'items 必須是陣列',
            'items.*.food_id.required' => '每個項目都要指定食物',
            'items.*.food_id.integer'  => 'food_id 必須是整數',
            'items.*.food_id.exists'   => '指定的食物不存在',
            'items.*.quantity.required' => '請輸入份量',
            'items.*.quantity.numeric'  => '份量必須是數字',
            'items.*.quantity.min'      => '份量必須大於 0',
            'items.*.quantity.max'      => '份量不能超過 9999.99',
        ];
    }
}
