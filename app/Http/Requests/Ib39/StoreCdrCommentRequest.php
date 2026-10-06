<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class StoreCdrCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('text'))) {
            $this->merge(['text' => trim($this->input('text'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('comment', $this->route('cdr')) ?? false;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:2000'],
        ];
    }
}
