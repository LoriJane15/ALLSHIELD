<?php

namespace App\Http\Requests\Japic;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmCertificationSigningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirmSigningComplete', $this->route('japicCertificationProcessing')) ?? false;
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1'], 'lock_version' => ['required', 'integer', 'min:0'],
            'signing_complete' => ['required', 'accepted'], 'delay_reason' => ['nullable', 'string', 'max:2000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['_token', 'revision', 'lock_version', 'signing_complete', 'delay_reason']) !== []) {
                $validator->errors()->add('request', 'The request contains unsupported or server-owned fields.');
            }
        }];
    }
}
