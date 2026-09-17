<?php

namespace App\Http\Requests\Rcsp;

use Illuminate\Foundation\Http\FormRequest;

class StoreRcspCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('text'))) {
            $this->merge(['text' => trim($this->input('text'))]);
        }
    }

    public function authorize(): bool
    {
        $form = $this->route('form');

        return $form && ($this->user()?->can('comment', $form) ?? false);
    }

    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:5000']];
    }
}
