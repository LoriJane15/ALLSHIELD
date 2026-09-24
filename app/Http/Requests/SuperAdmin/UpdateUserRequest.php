<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],   // blank = keep current
            'role' => ['required', Rule::in(['super_admin', 'admin', '39th_ib', 'gov_agency', 'lgu', 'mblrc', 'afp'])],
            'municipality_id' => ['nullable', 'required_if:role,lgu', 'exists:municipalities,id'],
            'gov_agency_id' => ['nullable', 'required_if:role,gov_agency', 'exists:gov_agencies,id'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
