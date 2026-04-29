<?php

namespace App\Http\Requests\BodyRecord;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrUpdateBodyRecordRequest extends FormRequest
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
            // 注意：故意「不」收 bmi 跟 user_id，BMI 由 Service 算、user_id 從 auth 拿
            'record_date'      => ['required', 'date'],
            'weight_kg'        => ['required', 'numeric', 'min:20', 'max:500'],
            'note'             => ['nullable', 'string', 'max:500'],
            // 階段 G：身體量測（全部 nullable）
            'waist_cm'         => ['nullable', 'numeric', 'min:30', 'max:200'],
            'hip_cm'           => ['nullable', 'numeric', 'min:30', 'max:200'],
            'chest_cm'         => ['nullable', 'numeric', 'min:30', 'max:200'],
            'arm_cm'           => ['nullable', 'numeric', 'min:10', 'max:80'],
            'thigh_cm'         => ['nullable', 'numeric', 'min:20', 'max:120'],
            'body_fat_percent' => ['nullable', 'numeric', 'min:3',  'max:60'],
            'muscle_mass_kg'   => ['nullable', 'numeric', 'min:10', 'max:200'],
        ];
    }

    /**
     * 為了避免前端偷偷加欄位，validated() 之後只回我們允許的欄位。
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $allowed = parent::validated();
        unset($allowed['bmi'], $allowed['user_id']);
        return $key === null ? $allowed : ($allowed[$key] ?? $default);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'record_date.required' => '請選擇日期',
            'record_date.date'     => '日期格式不正確',
            'weight_kg.required'   => '請輸入體重',
            'weight_kg.numeric'    => '體重必須是數字',
            'weight_kg.min'        => '體重數值過低（最低 20 kg）',
            'weight_kg.max'        => '體重數值過高（最高 500 kg）',
            'note.max'             => '備註最多 500 字',
        ];
    }
}
