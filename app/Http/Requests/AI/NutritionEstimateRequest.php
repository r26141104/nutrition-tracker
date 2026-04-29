<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class NutritionEstimateRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '請輸入食物名稱',
            'name.max'      => '食物名稱最多 100 字',
        ];
    }
}
