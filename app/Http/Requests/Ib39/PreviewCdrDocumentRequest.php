<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class PreviewCdrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cdr = $this->route('cdr');

        return $cdr !== null && ($this->user()?->can('view', $cdr) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
