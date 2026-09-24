<?php

namespace App\Http\Requests\Japic;

use Illuminate\Foundation\Http\FormRequest;

class UploadFinalCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadFinal', $this->route('japicCertificationProcessing')) ?? false;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:20480',
            ],
            'revision' => ['required', 'integer', 'min:0'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'all_signatories_confirmed' => ['required', 'accepted'],
            'correct_final_confirmed' => ['required', 'accepted'],
            'processing_id' => ['missing'],
            'status' => ['missing'],
            'version_number' => ['missing'],
            'replaces_version_id' => ['missing'],
            'storage_path' => ['missing'],
            'sha256' => ['missing'],
            'uploaded_by' => ['missing'],
            'completed_at' => ['missing'],
            'completed_by' => ['missing'],
            'current_final_version_id' => ['missing'],
        ];
    }
}
