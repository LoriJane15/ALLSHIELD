<?php

namespace App\Http\Requests\Rcsp;

use App\Models\RcspForm;
use App\Models\RcspPhase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewRcspPhaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('remarks'))) {
            $this->merge([
                'remarks' => collect($this->input('remarks'))
                    ->map(fn ($remark) => is_string($remark) ? trim($remark) : $remark)->all(),
            ]);
        }
    }

    public function authorize(): bool
    {
        $barangay = $this->route('rcspBarangay');

        return $barangay && ($this->user()?->can('review', $barangay) ?? false);
    }

    public function rules(): array
    {
        return [
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', Rule::in(['approved', 'disapproved', 'to be complied', 'to be conducted'])],
            'remarks' => ['sometimes', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $barangay = $this->route('rcspBarangay');
            $phase = $barangay ? RcspPhase::where('catalog_key', $barangay->catalog_key)
                ->where('number', $barangay->current_phase)->first() : null;
            if (! $phase) {
                $validator->errors()->add('statuses', 'The current review phase is unavailable.');

                return;
            }
            $ids = collect(array_keys((array) $this->input('statuses', [])))->map(fn ($id) => (int) $id);
            $forms = RcspForm::where('rcsp_barangay_id', $barangay->id)->where('rcsp_phase_id', $phase->id)
                ->whereIn('id', $ids)->get()->keyBy('id');
            if ($forms->count() !== $ids->unique()->count()) {
                $validator->errors()->add('statuses', 'Every reviewed form must belong to the selected barangay and phase.');
            }
            $latestIds = RcspForm::where('rcsp_barangay_id', $barangay->id)
                ->where('rcsp_phase_id', $phase->id)
                ->whereIn('rcsp_activity_id', $forms->pluck('rcsp_activity_id'))
                ->orderByDesc('submission_version')->orderByDesc('id')
                ->get(['id', 'rcsp_activity_id'])->unique('rcsp_activity_id')
                ->pluck('id', 'rcsp_activity_id');
            foreach ($forms as $form) {
                if ($form->id !== $latestIds->get($form->rcsp_activity_id)
                    || $form->status !== 'submitted') {
                    $validator->errors()->add("statuses.{$form->id}", 'Only the latest submitted activity version may be reviewed.');
                }
                $submittedStatuses = (array) $this->input('statuses', []);
                if (! $form->file && (($submittedStatuses[$form->id] ?? null) === 'approved')) {
                    $validator->errors()->add("statuses.{$form->id}", 'Supporting evidence is required before approval.');
                }
            }
            $remarks = (array) $this->input('remarks', []);
            if (array_diff(array_keys($remarks), $ids->all())) {
                $validator->errors()->add('remarks', 'A remark references an unknown form.');
            }
            foreach ((array) $this->input('statuses', []) as $id => $status) {
                if ($status !== 'approved' && trim((string) ($remarks[$id] ?? '')) === '') {
                    $validator->errors()->add("remarks.$id", 'Remarks are required when returning or disapproving an activity.');
                }
            }
            if (array_diff(array_keys($this->all()), ['_token', 'statuses', 'remarks'])) {
                $validator->errors()->add('request', 'Unknown review fields are not accepted.');
            }
        }];
    }
}
