<?php

namespace App\Http\Requests\Rcsp;

use App\Models\RcspActivity;
use App\Models\RcspPhase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRcspActivityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    public function authorize(): bool
    {
        $barangay = $this->route('rcspBarangay');

        return $barangay && ($this->user()?->can('createActivity', $barangay) ?? false);
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255', 'not_regex:/[\r\n\t]/']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['_token', 'title'])) {
                $validator->errors()->add('request', 'Unknown activity fields are not accepted.');
            }

            $barangay = $this->route('rcspBarangay');
            if (! $barangay || $barangay->status === 'Completed') {
                return;
            }

            $phase = RcspPhase::where('catalog_key', $barangay->catalog_key)
                ->where('number', $barangay->current_phase)->first();
            if (! $phase) {
                $validator->errors()->add('title', 'The current RCSP phase is unavailable.');

                return;
            }

            $normalized = RcspActivity::normalizeTitle($this->title());
            if ($normalized === '') {
                $validator->errors()->add('title', 'The activity title must not be blank.');

                return;
            }

            if (RcspActivity::where('rcsp_barangay_id', $barangay->id)
                ->where('rcsp_phase_id', $phase->id)
                ->where('normalized_title', $normalized)->exists()) {
                $validator->errors()->add('title', 'This activity title already exists in the current phase.');
            }
        }];
    }

    public function title(): string
    {
        return trim((string) $this->input('title'));
    }

    public function normalizedTitle(): string
    {
        return RcspActivity::normalizeTitle($this->title());
    }
}
