<?php

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMenuRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:80'],
            // 使用者額外提示：例如「賣健康餐」、「便當店」、「咖啡輕食」
            // 沒填的話 AI 只能憑店名瞎猜，準度極低；有填的話 AI 會優先依據此線索
            'hint' => ['nullable', 'string', 'max:200'],
        ];
    }
}
