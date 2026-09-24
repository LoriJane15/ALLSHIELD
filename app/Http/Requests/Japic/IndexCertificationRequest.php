<?php

namespace App\Http\Requests\Japic;

use App\Enums\JapicCertificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexCertificationRequest extends FormRequest
{
    private const FILTERS = ['search', 'status', 'timing', 'received_from', 'received_to', 'due_from', 'due_to', 'cancelled'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active && $this->user()->hasRole('japic');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(JapicCertificationStatus::class)],
            'timing' => ['nullable', Rule::in(['on_time', 'overdue'])],
            'received_from' => ['nullable', 'date_format:Y-m-d'],
            'received_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:received_from'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'],
            'cancelled' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), [...self::FILTERS, 'page']) as $key) {
                $validator->errors()->add($key, 'This filter is not allowed.');
            }
        }];
    }

    public function validatedFilters(): array
    {
        $filters = array_filter($this->safe()->only(self::FILTERS), fn ($value) => $value !== null && $value !== '');
        if (array_key_exists('cancelled', $filters)) {
            $filters['cancelled'] = filter_var($filters['cancelled'], FILTER_VALIDATE_BOOL);
        }

        return $filters;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (self::FILTERS as $key) {
            $value = $this->query($key);
            $normalized[$key] = is_string($value) ? (trim($value) ?: null) : $value;
        }
        $this->merge($normalized);
    }
}
