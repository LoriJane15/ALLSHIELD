<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(['super_admin', 'admin', '39th_ib', 'gov_agency', 'lgu', 'mblrc', 'afp', 'pswdo', 'japic'])],
            'is_active' => ['required', 'boolean'],
            'municipality_id' => ['nullable', 'required_if:role,lgu', 'exists:municipalities,id'],
            'gov_agency_id' => ['nullable', 'required_if:role,gov_agency', 'exists:gov_agencies,id'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $user = $this->route('user');

        if ($user instanceof User) {
            $this->session()->flash('super_admin_edit_user_id', $user->getKey());
        }

        parent::failedValidation($validator);
    }
}
