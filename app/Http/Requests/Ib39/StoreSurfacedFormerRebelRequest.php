<?php

namespace App\Http\Requests\Ib39;

use App\Enums\Ib39FrCategory;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurfacedFormerRebelRequest extends FormRequest
{
    private const PROHIBITED_FIELDS = [
        'reference_number',
        'created_by',
        'forwarded',
        'forwarding_organization',
        'other_organization_specification',
        'firstname',
        'middlename',
        'lastname',
        'real_name',
        'alias',
        'nickname',
        'identity_document_number',
        'former_rebel_id',
    ];

    public function authorize(): bool
    {
        return $this->user()?->is_active
            && $this->user()->hasRole('39th_ib');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::enum(Ib39FrCategory::class)],
            'other_category_specification' => [
                'nullable',
                'string',
                'max:255',
                'required_if:category,'.Ib39FrCategory::Other->value,
                'prohibited_unless:category,'.Ib39FrCategory::Other->value,
            ],
            'province' => ['required', 'string', Rule::in([Ib39SurfacedFormerRebel::DEFAULT_PROVINCE])],
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
            'barangay_id' => [
                'nullable',
                'integer',
                Rule::exists('barangays', 'id')->where('municipality_id', $this->input('municipality_id')),
            ],
            'specific_location' => ['nullable', 'string', 'max:500'],
            'surfaced_at' => ['required', 'date', 'before_or_equal:today'],
            'possessed_firearms' => ['required', 'boolean'],
            'initial_remarks' => ['nullable', 'string', 'max:5000'],
            ...collect(self::PROHIBITED_FIELDS)->mapWithKeys(fn (string $field) => [$field => ['missing']])->all(),
        ];
    }

    public function messages(): array
    {
        return [
            'other_category_specification.required_if' => 'Specify the FR category when Other is selected.',
            'province.in' => 'The province must be Davao del Sur.',
            'barangay_id.exists' => 'The selected barangay does not belong to the selected municipality or city.',
            'surfaced_at.before_or_equal' => 'The date of surfacing cannot be in the future.',
            '*.missing' => 'This protected field must not be submitted.',
        ];
    }

    public function validatedForCreation(): array
    {
        return $this->safe()->only([
            'first_name',
            'last_name',
            'category',
            'other_category_specification',
            'province',
            'municipality_id',
            'barangay_id',
            'specific_location',
            'surfaced_at',
            'possessed_firearms',
            'initial_remarks',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $category = $this->trimmed('category');

        $this->merge([
            'first_name' => $this->trimmed('first_name'),
            'last_name' => $this->trimmed('last_name'),
            'category' => $category,
            'other_category_specification' => $category === Ib39FrCategory::Other->value
                ? $this->trimmed('other_category_specification')
                : null,
            'province' => $this->trimmed('province'),
            'barangay_id' => $this->filled('barangay_id') ? $this->input('barangay_id') : null,
            'specific_location' => $this->trimmed('specific_location'),
            'initial_remarks' => $this->trimmed('initial_remarks'),
        ]);
    }

    private function trimmed(string $field): ?string
    {
        $value = trim((string) $this->input($field, ''));

        return $value === '' ? null : $value;
    }
}
