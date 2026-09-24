<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class UploadFeaDraftFileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->replacement_reason)) {
            $this->merge(['replacement_reason' => trim($this->replacement_reason)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('uploadDraft', [$this->route('document'), $this->route('fea')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'expected_current_version_id' => ['nullable', 'integer', 'min:1'],
            'replacement_reason' => ['nullable', 'string', 'max:2000'],
            'status' => ['missing'],
            'completed_at' => ['missing'],
            'completed_by' => ['missing'],
            'final_copy' => ['missing'],
            'version_number' => ['missing'],
            'replaces_version_id' => ['missing'],
            'uploaded_by' => ['missing'],
            'storage_path' => ['missing'],
            'sha256' => ['missing'],
            'mime_type' => ['missing'],
        ];
    }
}
