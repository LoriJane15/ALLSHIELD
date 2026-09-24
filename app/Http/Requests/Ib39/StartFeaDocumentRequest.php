<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartFeaDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('start', [$this->route('document'), $this->route('fea')]) ?? false;
    }

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->all()), ['_token']);

            if ($unknown !== []) {
                $validator->errors()->add('request', 'The request contains unsupported fields.');
            }
        }];
    }
}
