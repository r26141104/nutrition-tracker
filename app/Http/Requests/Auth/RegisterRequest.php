<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:50'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'      => '請輸入名稱',
            'name.max'           => '名稱最多 50 個字',
            'email.required'     => '請輸入 email',
            'email.email'        => 'email 格式不正確',
            'email.unique'       => '此 email 已被註冊',
            'password.required'  => '請輸入密碼',
            'password.min'       => '密碼至少 8 個字元',
            'password.confirmed' => '兩次輸入的密碼不一致',
        ];
    }
}
