<?php

namespace App\Http\Requests\Ib39;

use App\Support\Ib39CdrFormSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCdrDraftRequest extends FormRequest
{
    private const PROTECTED_FIELDS = [
        'status', 'started_at', 'due_at', 'completed_at', 'completed_by',
        'current_final_version_id', 'created_by', 'last_edited_by',
        'schema_version', 'storage_path', 'document_versions', 'history',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('updateDraft', $this->route('cdr')) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'content' => ['nullable', 'array:'.implode(',', Ib39CdrFormSchema::allowedKeys())],
            ...collect(self::PROTECTED_FIELDS)->mapWithKeys(fn (string $field) => [$field => ['missing']])->all(),
        ];

        foreach (Ib39CdrFormSchema::sections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                $rules["content.{$key}"] = ['nullable', 'string', 'max:'.$field['max']];
                if (($field['type'] ?? null) === 'date') {
                    $rules["content.{$key}"][] = 'date_format:Y-m-d';
                }
            }
        }

        foreach (Ib39CdrFormSchema::repeatableSections() as $key => $section) {
            if (isset($section['status_key'])) {
                $rules['content.'.$section['status_key']] = ['nullable', Rule::in(Ib39CdrFormSchema::APPLICABILITY)];
            }
            $columnKeys = array_keys($section['columns']);
            $rules["content.{$key}"] = ['nullable', 'array', 'max:'.Ib39CdrFormSchema::MAX_ROWS];
            $rules["content.{$key}.*"] = ['array:'.implode(',', $columnKeys)];
            foreach ($columnKeys as $column) {
                $rules["content.{$key}.*.{$column}"] = ['nullable', 'string', 'max:10000'];
            }
        }

        return $rules;
    }

    public function validatedContent(): array
    {
        return $this->normalize($this->validated('content', []));
    }

    public function messages(): array
    {
        return [
            '*.missing' => 'Protected workflow fields must not be submitted.',
            'content.*.max' => 'A repeatable CDR section may contain at most '.Ib39CdrFormSchema::MAX_ROWS.' rows.',
        ];
    }

    private function normalize(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                $content[$key] = trim($value);

                continue;
            }

            if (is_array($value)) {
                $rows = array_is_list($value) ? $value : [$value];
                $content[$key] = collect($rows)
                    ->map(fn ($row) => is_array($row)
                        ? collect($row)->map(fn ($cell) => is_string($cell) ? trim($cell) : $cell)->all()
                        : $row)
                    ->filter(fn ($row) => ! is_array($row) || collect($row)->contains(fn ($cell) => filled($cell)))
                    ->values()
                    ->all();
            }
        }

        foreach (Ib39CdrFormSchema::repeatableSections() as $key => $section) {
            if (isset($section['status_key']) && ($content[$section['status_key']] ?? null) === 'na') {
                $content[$key] = [];
            }
        }
        foreach (['significant_information' => 'significant_information_status', 'white_area_information' => 'white_area_status', 'projected_enemy_operations' => 'projected_enemy_status'] as $field => $status) {
            if (($content[$status] ?? null) === 'na') {
                $content[$field] = '';
            }
        }

        return $content;
    }
}
