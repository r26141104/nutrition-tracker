<?php

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateFoodRequest extends FormRequest
{
    public const CATEGORIES = [
        'rice_box',
        'noodle',
        'convenience',
        'fast_food',
        'drink',
        'snack',
        'other',
    ];

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
            'name'         => ['required', 'string', 'max:100'],
            'brand'        => ['nullable', 'string', 'max:50'],
            'category'     => ['required', 'string', Rule::in(self::CATEGORIES)],
            'serving_unit' => ['required', 'string', 'max:20'],
            'serving_size' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'calories'     => ['required', 'integer', 'min:0', 'max:99999'],
            'protein_g'    => ['required', 'numeric', 'min:0', 'max:9999'],
            'fat_g'        => ['required', 'numeric', 'min:0', 'max:9999'],
            'carbs_g'      => ['required', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    /**
     * 過濾掉 server-controlled 欄位（前端不能決定）：
     *   - is_system / created_by_user_id（既有）
     *   - source_type / confidence_level（修正四新加）
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $allowed = parent::validated();
        unset(
            $allowed['is_system'],
            $allowed['created_by_user_id'],
            $allowed['source_type'],
            $allowed['confidence_level'],
        );
        return $key === null ? $allowed : ($allowed[$key] ?? $default);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'         => '請輸入食物名稱',
            'name.max'              => '食物名稱最多 100 字',
            'brand.max'             => '品牌名稱最多 50 字',
            'category.required'     => '請選擇類別',
            'category.in'           => '類別必須是 rice_box / noodle / convenience / fast_food / drink / snack / other 之一',
            'serving_unit.required' => '請輸入單位（如：份、杯、顆、g）',
            'serving_unit.max'      => '單位最多 20 字',
            'serving_size.required' => '請輸入份量數值',
            'serving_size.numeric'  => '份量必須是數字',
            'serving_size.min'      => '份量必須大於 0',
            'calories.required'     => '請輸入熱量',
            'calories.integer'      => '熱量必須是整數',
            'calories.min'          => '熱量不能為負',
            'protein_g.required'    => '請輸入蛋白質（克）',
            'fat_g.required'        => '請輸入脂肪（克）',
            'carbs_g.required'      => '請輸入碳水化合物（克）',
        ];
    }
}
