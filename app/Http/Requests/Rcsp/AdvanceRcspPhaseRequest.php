<?php

namespace App\Http\Requests\Rcsp;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceRcspPhaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $barangay = $this->route('rcspBarangay');

        return $barangay && ($this->user()?->can('update', $barangay) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
