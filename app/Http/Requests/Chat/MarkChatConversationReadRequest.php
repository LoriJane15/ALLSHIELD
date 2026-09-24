<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MarkChatConversationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('chatConversation'));

        return true;
    }

    public function rules(): array
    {
        return [
            'through_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
