<?php

namespace App\Http\Requests\Chat;

use App\Services\ChatDocumentReferenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('send', $this->route('chatConversation'));

        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['bail', 'required', 'string', 'max:5000'],
            'document_reference' => [
                'nullable',
                'string',
                'max:100',
                'regex:/\A(?:'.implode('|', array_map(
                    fn (string $type): string => preg_quote($type, '/'),
                    ChatDocumentReferenceService::TYPES,
                )).'):[1-9][0-9]*\z/',
            ],
            'document_parent_id' => ['prohibited'],
            'document_parent_type' => ['prohibited'],
            'former_rebel_id' => ['prohibited'],
            'preview_url' => ['prohibited'],
            'storage_path' => ['prohibited'],
            'document_role' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }

        if ($this->input('document_reference') === '') {
            $this->merge(['document_reference' => null]);
        }
    }

    public function documentReference(): ?string
    {
        return $this->validated('document_reference');
    }
}
