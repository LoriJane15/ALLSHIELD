<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['super_admin', 'admin', '39th_ib', 'gov_agency', 'lgu', 'mblrc', 'afp'])],
            'municipality_id' => ['nullable', 'required_if:role,lgu', 'exists:municipalities,id'],
            'gov_agency_id' => ['nullable', 'required_if:role,gov_agency', 'exists:gov_agencies,id'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
