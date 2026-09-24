<?php

namespace App\Http\Requests\Pswdo;

use Illuminate\Foundation\Http\FormRequest;

class UploadFinalEnrollmentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadDocument', $this->route('pswdoEnrollment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'correct_document_type_confirmed' => ['required', 'accepted'],
            'belongs_to_fr_confirmed' => ['required', 'accepted'],
            'final_signed_confirmed' => ['required', 'accepted'],
            'document_type' => ['missing'],
            'pswdo_enrollment_id' => ['missing'],
            'storage_path' => ['missing'],
            'sha256' => ['missing'],
            'uploaded_by' => ['missing'],
            'uploaded_at' => ['missing'],
        ];
    }
}
