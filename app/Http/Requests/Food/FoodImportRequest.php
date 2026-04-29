<?php

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

class FoodImportRequest extends FormRequest
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
            // max 是 KB 數，2048 KB = 2 MB
            'file' => ['required', 'file', 'mimes:csv,txt,json', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => '請選擇要匯入的檔案',
            'file.file'     => '上傳的不是合法檔案',
            'file.mimes'    => '檔案格式必須是 CSV、TXT 或 JSON',
            'file.max'      => '檔案大小不能超過 2 MB',
        ];
    }
}
