<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class IndexChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('chatConversation'));

        return true;
    }

    public function rules(): array
    {
        return [
            'after_id' => ['nullable', 'integer', 'min:0'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->query('after_id') === null && $this->query('before_id') === null) {
                $validator->errors()->add('after_id', 'A message cursor is required.');
            }
            if ($this->query('after_id') !== null && $this->query('before_id') !== null) {
                $validator->errors()->add('before_id', 'Only one message cursor may be used.');
            }

            foreach (array_diff(array_keys($this->query()), ['after_id', 'before_id']) as $key) {
                $validator->errors()->add($key, 'This query parameter is not allowed.');
            }
        }];
    }
}
