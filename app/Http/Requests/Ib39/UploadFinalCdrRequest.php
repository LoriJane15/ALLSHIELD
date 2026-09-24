<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class UploadFinalCdrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadFinal', $this->route('cdr')) ?? false;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480'],
            'confirmed' => ['accepted'],
            'replacement_reason' => ['missing'],
            'status' => ['missing'],
            'version_number' => ['missing'],
            'completed_at' => ['missing'],
            'completed_by' => ['missing'],
            'current_final_version_id' => ['missing'],
            'storage_path' => ['missing'],
            'sha256' => ['missing'],
        ];
    }
}
