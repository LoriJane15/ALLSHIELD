<?php

namespace App\Http\Requests\Mblrc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormerRebelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('mblrc') ?? false;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', Rule::in(['Jr.', 'Sr.', 'II', 'III'])],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'civil_status' => ['nullable', Rule::in(['Single', 'Married', 'Widowed', 'Separated'])],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'contact_num' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'municipality_id' => ['required', 'exists:municipalities,id'],
            'barangay_id' => ['required', 'exists:barangays,id'],
            'province' => ['nullable', 'string', 'max:50'],
            'zipcode' => ['nullable', 'string', 'max:10'],
            'residential_address' => ['nullable', 'string', 'max:255'],
            'surrender_date' => ['nullable', 'date'],
            'surrender_reason' => ['nullable', 'string'],
            'batch_year' => ['nullable', 'string', 'max:50'],
            'batch_section' => ['nullable', Rule::in(['1', '2'])],
            'status' => ['nullable', Rule::in([
                'Active', 'On hold', 'Reintegrated', 'Inactive', 'Under Review',
                'Disengaged', 'Pending', 'Suspended', 'Completed', 'Deceased', 'Relocated',
            ])],
        ];
    }
}
