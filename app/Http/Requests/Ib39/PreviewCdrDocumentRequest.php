<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class PreviewCdrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cdr = $this->route('cdr');
        $version = $this->route('version');

        return $version?->cdr_processing_id === $cdr?->id
            && ($this->user()?->can('preview', $version) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
