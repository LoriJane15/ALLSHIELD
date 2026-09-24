<?php

namespace App\Http\Requests\Rcsp;

use App\Models\RcspBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRcspBarangayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RcspBarangay::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'barangay_id' => [
                'required', 'integer',
                Rule::exists('barangays', 'id')->where('municipality_id', $this->user()->municipality_id),
                Rule::unique('rcsp_barangays', 'barangay_id'),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['_token', 'barangay_id'])) {
                $validator->errors()->add('request', 'Unknown RCSP registration fields are not accepted.');
            }
        }];
    }
}
