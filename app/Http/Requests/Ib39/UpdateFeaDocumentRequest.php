<?php

namespace App\Http\Requests\Ib39;

use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFeaDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updatePreliminary', [$this->route('document'), $this->route('fea')]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $document = $this->input('document');

        if (! is_array($document)) {
            return;
        }

        foreach (['status', 'compliance_status', 'remarks', 'compliance_reason', 'delay_reason'] as $key) {
            if (array_key_exists($key, $document) && is_string($document[$key])) {
                $trimmed = trim($document[$key]);
                $document[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        $this->merge(['document' => $document]);
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'array:status,compliance_status,remarks,compliance_reason,is_delayed,delay_reason'],
            'document.status' => ['required', Rule::in([Ib39FeaDocumentStatus::Processing->value])],
            'document.compliance_status' => ['required', Rule::enum(Ib39FeaComplianceStatus::class)],
            'document.remarks' => ['present', 'nullable', 'string', 'max:2000'],
            'document.compliance_reason' => ['present', 'nullable', 'string', 'max:2000'],
            'document.is_delayed' => ['required', 'boolean'],
            'document.delay_reason' => ['present', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->all()), ['_token', '_method', 'document']);
            if ($unknown !== []) {
                $validator->errors()->add('request', 'The request contains unsupported fields.');
            }

            $compliance = data_get($this->all(), 'document.compliance_status');
            if (in_array($compliance, [
                Ib39FeaComplianceStatus::ReturnedForCompliance->value,
                Ib39FeaComplianceStatus::HasIssue->value,
            ], true) && blank(data_get($this->all(), 'document.compliance_reason'))) {
                $validator->errors()->add('document.compliance_reason', 'A compliance reason is required for the selected compliance status.');
            }

            if (filter_var(data_get($this->all(), 'document.is_delayed'), FILTER_VALIDATE_BOOLEAN)
                && blank(data_get($this->all(), 'document.delay_reason'))) {
                $validator->errors()->add('document.delay_reason', 'A reason for delay is required when the document is marked delayed.');
            }
        }];
    }

    public function documentData(): array
    {
        return $this->validated('document');
    }
}
