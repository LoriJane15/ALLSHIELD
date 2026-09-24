<?php

namespace App\Http\Requests\Ib39;

use App\Enums\Ib39FrCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSurfacedFormerRebelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && $this->user()->hasRole('39th_ib');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(Ib39FrCategory::class)],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'possessed_firearms' => ['nullable', Rule::in(['0', '1'])],
        ];
    }

    public function validatedFilters(): array
    {
        $filters = array_filter(
            $this->validated(),
            fn ($value) => $value !== null && $value !== '',
        );

        if (array_key_exists('municipality_id', $filters)) {
            $filters['municipality_id'] = (int) $filters['municipality_id'];
        }

        if (array_key_exists('possessed_firearms', $filters)) {
            $filters['possessed_firearms'] = $filters['possessed_firearms'] === '1';
        }

        return $filters;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->trimmed('search'),
            'category' => $this->trimmed('category'),
            'municipality_id' => $this->trimmed('municipality_id'),
            'possessed_firearms' => $this->trimmed('possessed_firearms'),
        ]);
    }

    private function trimmed(string $field): mixed
    {
        $value = $this->input($field);

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
