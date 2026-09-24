<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceFinalCdrRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->replacement_reason)) {
            $this->merge(['replacement_reason' => trim($this->replacement_reason)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('replaceFinal', $this->route('cdr')) ?? false;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480'],
            'replacement_reason' => ['required', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
            'status' => ['missing'],
            'version_number' => ['missing'],
            'completed_at' => ['missing'],
            'completed_by' => ['missing'],
            'current_final_version_id' => ['missing'],
            'replaces_version_id' => ['missing'],
            'storage_path' => ['missing'],
            'sha256' => ['missing'],
        ];
    }
}
