<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->is_active
            && array_key_exists($user->role, config('shield.roles', []));
    }

    public function rules(): array
    {
        $supportedRoles = array_keys(config('shield.roles', []));

        return [
            'recipient_id' => [
                'bail',
                'required',
                'integer',
                Rule::notIn([$this->user()?->getKey()]),
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('role', $supportedRoles)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_id.exists' => 'The selected account is unavailable.',
            'recipient_id.not_in' => 'The selected account is unavailable.',
        ];
    }
}
