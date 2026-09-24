<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class StartCdrProcessingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('start', $this->route('cdr')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
