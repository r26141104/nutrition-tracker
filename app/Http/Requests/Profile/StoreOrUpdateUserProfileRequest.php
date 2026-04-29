<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateUserProfileRequest extends FormRequest
{
    public const ACTIVITY_LEVELS = ['sedentary', 'light', 'moderate', 'active'];
    public const GOAL_TYPES      = ['lose_fat', 'gain_muscle', 'maintain'];
    public const SEXES           = ['male', 'female'];

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
            'birthdate'      => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'sex'            => ['required', 'string', Rule::in(self::SEXES)],
            'height_cm'      => ['required', 'numeric', 'min:50', 'max:300'],
            'weight_kg'      => ['required', 'numeric', 'min:20', 'max:500'],
            'target_bmi'     => ['required', 'numeric', 'min:10', 'max:50'],
            'activity_level' => ['required', 'string', Rule::in(self::ACTIVITY_LEVELS)],
            'goal_type'      => ['required', 'string', Rule::in(self::GOAL_TYPES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthdate.required'      => '請輸入生日',
            'birthdate.date'          => '生日格式不正確',
            'birthdate.before'        => '生日必須早於今天',
            'birthdate.after'         => '生日必須在 1900 年之後',
            'sex.required'            => '請選擇生理性別',
            'sex.in'                  => '生理性別必須是 male / female 之一',
            'height_cm.required'      => '請輸入身高',
            'height_cm.numeric'       => '身高必須是數字',
            'height_cm.min'           => '身高最低 50 公分',
            'height_cm.max'           => '身高最高 300 公分',
            'weight_kg.required'      => '請輸入體重',
            'weight_kg.numeric'       => '體重必須是數字',
            'weight_kg.min'           => '體重最低 20 公斤',
            'weight_kg.max'           => '體重最高 500 公斤',
            'target_bmi.required'     => '請輸入目標 BMI',
            'target_bmi.numeric'      => '目標 BMI 必須是數字',
            'target_bmi.min'          => '目標 BMI 最低 10',
            'target_bmi.max'          => '目標 BMI 最高 50',
            'activity_level.required' => '請選擇活動量',
            'activity_level.in'       => '活動量選項不正確',
            'goal_type.required'      => '請選擇目標類型',
            'goal_type.in'            => '目標類型選項不正確',
        ];
    }
}
