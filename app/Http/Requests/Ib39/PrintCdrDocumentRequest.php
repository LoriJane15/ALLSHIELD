<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class PrintCdrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cdr = $this->route('cdr');
        $document = $cdr?->finalDocument;

        return $document?->cdr_processing_id === $cdr?->id
            && ($this->user()?->can('print', $document) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
