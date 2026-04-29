<?php

namespace App\Http\Requests\Vision;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeFoodPhotoRequest extends FormRequest
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
            // max 是 KB 數，4096 KB = 4 MB
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => '請選擇要辨識的照片',
            'image.image'    => '上傳的不是圖片檔',
            'image.mimes'    => '照片格式必須是 JPG / JPEG / PNG / WEBP',
            'image.max'      => '照片大小不能超過 4 MB',
        ];
    }
}
