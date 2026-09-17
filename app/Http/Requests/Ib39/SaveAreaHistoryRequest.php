<?php

namespace App\Http\Requests\Ib39;

use App\Models\Barangay;
use App\Models\MapBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveAreaHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->is_active && $user->role === '39th_ib';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'frs' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['_token', '_method', 'barangay_id', 'effective_date', 'frs'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'This input is not accepted.');
            }

            if ($validator->errors()->has('barangay_id')) {
                return;
            }

            $barangay = Barangay::query()->with('municipality')->find($this->integer('barangay_id'));
            if (! $barangay?->municipality) {
                $validator->errors()->add('barangay_id', 'The selected barangay has no valid municipality.');

                return;
            }

            $area = $this->route('area');
            if ($area instanceof MapBarangay && $area->barangay_id !== $barangay->id) {
                $validator->errors()->add('barangay_id', 'The selected barangay does not match this area.');
            }
        }];
    }

    public function canonicalBarangay(): Barangay
    {
        return Barangay::query()->with('municipality')->findOrFail($this->integer('barangay_id'));
    }
}
