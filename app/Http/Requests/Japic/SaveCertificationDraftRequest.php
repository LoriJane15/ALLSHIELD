<?php

namespace App\Http\Requests\Japic;

use App\Support\JapicCertificationDraftSchema;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveCertificationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('saveDraft', $this->route('japicCertificationProcessing')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['control_number' => is_string($this->control_number) ? trim($this->control_number) : $this->control_number,
            'delay_reason' => is_string($this->delay_reason) ? trim($this->delay_reason) : $this->delay_reason]);
    }

    public function rules(): array
    {
        $rules = [
            'revision' => ['required', 'integer', 'min:0'], 'lock_version' => ['required', 'integer', 'min:0'],
            'control_number' => ['required', 'string', 'max:100'], 'delay_reason' => ['nullable', 'string', 'max:2000'],
            'certificate' => ['required', 'array:date_issued,narrative_values,prepared_by,attested_by'],
            'certificate.date_issued' => ['nullable', 'date_format:Y-m-d'],
            'certificate.narrative_values' => ['required', 'array:fr_name,residence,former_organization_or_category,areas_of_operation,affiliated_organization,surrendered_to,surrendered_on,surrendered_at'],
            'certificate.narrative_values.fr_name' => ['nullable', 'string', 'max:255'],
            'certificate.narrative_values.residence' => ['nullable', 'string', 'max:1000'],
            'certificate.narrative_values.former_organization_or_category' => ['nullable', 'string', 'max:500'],
            'certificate.narrative_values.areas_of_operation' => ['nullable', 'string', 'max:2000'],
            'certificate.narrative_values.affiliated_organization' => ['nullable', 'string', 'max:500'],
            'certificate.narrative_values.surrendered_to' => ['nullable', 'string', 'max:500'],
            'certificate.narrative_values.surrendered_on' => ['nullable', 'date_format:Y-m-d'],
            'certificate.narrative_values.surrendered_at' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (['prepared_by', 'attested_by'] as $section) {
            $rules["certificate.{$section}"] = ['required', 'array', 'list', 'min:1', 'max:'.JapicCertificationDraftSchema::MAX_PERSONNEL_ROWS];
            $rules["certificate.{$section}.*"] = ['required', 'array:full_name,rank'];
            $rules["certificate.{$section}.*.full_name"] = ['required', 'string', 'max:255'];
            $rules["certificate.{$section}.*.rank"] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['_token', '_method', 'revision', 'lock_version', 'control_number', 'delay_reason', 'certificate'];
            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add('request', 'The request contains unsupported or server-owned fields.');
            }
        }];
    }

    public function manualPayload(): array
    {
        return ['certificate' => $this->validated('certificate', [])];
    }
}
