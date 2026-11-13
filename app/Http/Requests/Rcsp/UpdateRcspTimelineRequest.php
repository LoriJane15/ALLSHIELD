<?php

namespace App\Http\Requests\Rcsp;

use App\Services\RcspTimelineService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRcspTimelineRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $schedule = (array) $this->input('schedule', []);
        foreach (['strategic_objective', 'implementation_note'] as $field) {
            if (is_string($schedule[$field] ?? null)) {
                $schedule[$field] = trim($schedule[$field]);
            }
        }

        $this->merge([
            'schedule' => $schedule,
            'adjustment_reason' => is_string($this->input('adjustment_reason'))
                ? trim($this->input('adjustment_reason')) : $this->input('adjustment_reason'),
        ]);
    }

    public function authorize(): bool
    {
        $barangay = $this->route('rcspBarangay');

        return $barangay && ($this->user()?->can('update', $barangay) ?? false);
    }

    public function rules(): array
    {
        return [
            'phase_number' => ['required', 'integer', Rule::in(range(0, 5))],
            'schedule' => [
                'required',
                'array:planned_start_date,planned_end_date,strategic_objective,implementation_note',
            ],
            'schedule.planned_start_date' => ['required', 'date_format:Y-m-d'],
            'schedule.planned_end_date' => [
                'required', 'date_format:Y-m-d', 'after_or_equal:schedule.planned_start_date',
            ],
            'schedule.strategic_objective' => ['required', 'string', 'max:1000'],
            'schedule.implementation_note' => ['nullable', 'string', 'max:2000'],
            'adjustment_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $scheduleData = (array) $this->input('schedule');
            $objective = $scheduleData['strategic_objective'];
            if ($objective !== strip_tags($objective)) {
                $validator->errors()->add(
                    'schedule.strategic_objective',
                    'The strategic objective must be plain text without HTML.'
                );

                return;
            }

            $timeline = $this->route('rcspBarangay')?->implementationTimeline()
                ->with('schedules.phase')->first();
            if (! $timeline) {
                return;
            }

            $schedules = $timeline->schedules->keyBy(fn ($schedule) => (int) $schedule->phase->number);
            $phaseNumber = $this->integer('phase_number');
            $existing = $schedules->get($phaseNumber);
            if (! $existing) {
                return;
            }

            $officialObjective = RcspTimelineService::PHASE_DETAILS[$phaseNumber]['objective'];
            $existingObjective = $existing->strategic_objective ?: $officialObjective;
            $dateChanged = $existing->planned_start_date->format('Y-m-d') !== $scheduleData['planned_start_date']
                || $existing->planned_end_date->format('Y-m-d') !== $scheduleData['planned_end_date'];
            $objectiveChanged = $existingObjective !== $objective;

            if (($dateChanged || $objectiveChanged) && blank($this->input('adjustment_reason'))) {
                $validator->errors()->add(
                    'adjustment_reason',
                    'An adjustment reason is required when a saved planned date or strategic objective changes.'
                );
            }
        }];
    }
}
