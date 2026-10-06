<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class DownloadCdrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cdr = $this->route('cdr');
        $document = $cdr?->finalDocument;

        return $document?->cdr_processing_id === $cdr?->id
            && ($this->user()?->can('download', $document) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
