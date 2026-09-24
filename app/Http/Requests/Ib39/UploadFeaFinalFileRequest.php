<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadFeaFinalFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadFinal', [$this->route('document'), $this->route('fea')]) ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:20480']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['_token', 'file']) !== []) {
                $validator->errors()->add('request', 'The request contains unsupported or server-owned fields.');
            }
        }];
    }
}
