<?php

namespace App\Http\Requests\Ib39;

use App\Services\Ib39FeaDraftSchema;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SaveFeaDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('editDraft', [$this->route('document'), $this->route('fea')]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $draft = $this->input('draft');
        if (! is_array($draft)) {
            return;
        }

        foreach (app(Ib39FeaDraftSchema::class)->fields($this->route('document')->document_type) as $field) {
            if ($field['type'] === 'table' && ! array_key_exists($field['key'], $draft)) {
                $draft[$field['key']] = [];
            }
        }

        array_walk_recursive($draft, function (&$value): void {
            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }
        });
        $this->merge(['draft' => $draft]);
    }

    public function rules(Ib39FeaDraftSchema $schema): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            ...$schema->rules($this->route('document')->document_type),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['_token', '_method', 'revision', 'draft']) !== []) {
                $validator->errors()->add('request', 'The request contains unsupported or server-owned fields.');
            }
        }];
    }

    protected function failedValidation(Validator $validator): void
    {
        $response = redirect($this->getRedirectUrl())->withErrors($validator, $this->errorBag);
        throw new ValidationException($validator, $response, $this->errorBag);
    }

    public function draftData(): array
    {
        return $this->validated('draft');
    }
}
