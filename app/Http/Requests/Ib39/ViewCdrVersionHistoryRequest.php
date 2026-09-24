<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class ViewCdrVersionHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('view', $this->route('cdr')) ?? false)
            && ($this->user()?->can('viewVersionHistory', $this->route('cdr')) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
