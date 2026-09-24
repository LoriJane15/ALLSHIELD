<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeCdrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finalize', $this->route('cdr')) ?? false;
    }

    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
            'draft_fingerprint' => ['required', 'string', 'size:64'],
            'status' => ['missing'],
            'version_number' => ['missing'],
            'completed_at' => ['missing'],
            'completed_by' => ['missing'],
            'content_snapshot' => ['missing'],
            'storage_path' => ['missing'],
            'missing_fields' => ['missing'],
            'current_final_version_id' => ['missing'],
        ];
    }
}
