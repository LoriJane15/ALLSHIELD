<?php

namespace App\Http\Requests\Ib39;

use App\Enums\Ib39CdrPhotoType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadCdrPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadPhoto', $this->route('cdr')) ?? false;
    }

    public function rules(): array
    {
        return [
            'photo_type' => ['required', Rule::enum(Ib39CdrPhotoType::class)],
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'mimetypes:image/jpeg,image/png',
                'max:5120',
                'dimensions:max_width=8000,max_height=8000',
            ],
            'status' => ['missing'],
            'storage_path' => ['missing'],
            'uploaded_by' => ['missing'],
            'version_number' => ['missing'],
        ];
    }
}
