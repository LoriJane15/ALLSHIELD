<?php

namespace App\Http\Requests\Japic;

use Illuminate\Foundation\Http\FormRequest;

class UploadCertificationPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadPhoto', $this->route('processing')) ?? false;
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'mimetypes:image/jpeg,image/png',
                'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000',
            ],
            'processing_id' => ['missing'],
            'storage_path' => ['missing'],
            'photo_version_id' => ['missing'],
            'uploaded_by' => ['missing'],
        ];
    }
}
