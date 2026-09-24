<?php

namespace App\Http\Requests\Ib39;

use Illuminate\Foundation\Http\FormRequest;

class CancelSurfacedFormerRebelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('ib39SurfacedFormerRebel')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'confirmed' => ['accepted'],
            'cancelled_by' => ['missing'],
            'cancelled_at' => ['missing'],
            'previous_overall_status' => ['missing'],
            'status' => ['missing'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function validatedReason(): string
    {
        return $this->validated('reason');
    }
}
