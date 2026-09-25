<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspFormReview;
use App\Models\RcspPhase;
use App\Models\RcspPhaseTransition;
use App\Models\User;
use App\Support\RcspActivityCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RcspWorkflowService
{
    public function createBarangay(int $barangayId, User $user): RcspBarangay
    {
        try {
            return DB::transaction(function () use ($barangayId, $user): RcspBarangay {
                Gate::forUser($user)->authorize('create', RcspBarangay::class);
                $barangay = Barangay::whereKey($barangayId)->lockForUpdate()->first();
                if (! $barangay || $barangay->municipality_id !== $user->municipality_id) {
                    throw ValidationException::withMessages([
                        'barangay_id' => 'The selected barangay is outside your assigned municipality.',
                    ]);
                }

                $phases = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                    ->orderBy('number')->get(['id', 'number', 'name']);
                if ($phases->mapWithKeys(fn (RcspPhase $phase): array => [(int) $phase->number => $phase->name])->all()
                    !== RcspPhase::STRUCTURAL_NAMES) {
                    throw ValidationException::withMessages([
                        'barangay_id' => 'The configurable RCSP phase structure is unavailable.',
                    ]);
                }

                if (RcspBarangay::where('barangay_id', $barangay->id)->exists()) {
                    throw ValidationException::withMessages([
                        'barangay_id' => 'This barangay is already registered for RCSP.',
                    ]);
                }

                $record = RcspBarangay::create([
                    'barangay_id' => $barangay->id,
                    'municipality_id' => $user->municipality_id,
                    'status' => 'Pending',
                    'current_phase' => 0,
                    'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY,
                ]);
                $record->phaseStatus()->create([]);
                $this->provisionCatalogActivities($record, $phases);

                return $record;
            });
        } catch (QueryException $exception) {
            if ($this->isBarangayConflict($exception)) {
                throw ValidationException::withMessages([
                    'barangay_id' => 'This barangay is already registered for RCSP.',
                ]);
            }

            throw $exception;
        }
    }

    private function provisionCatalogActivities(RcspBarangay $barangay, Collection $phases): void
    {
        $now = now();
        $rows = [];

        foreach ($phases as $phase) {
            foreach (RcspActivityCatalog::ACTIVITIES[(int) $phase->number] ?? [] as $description) {
                $rows[] = [
                    'rcsp_phase_id' => $phase->id,
                    'rcsp_barangay_id' => $barangay->id,
                    'created_by_user_id' => null,
                    'description' => $description,
                    'normalized_title' => RcspActivity::normalizeTitle($description),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('rcsp_activities')->insert($rows);
    }

    public function submitActivity(
        RcspBarangay $barangay,
        RcspActivity $activity,
        User $user,
        string $conduct,
        ?UploadedFile $evidence
    ): RcspForm {
        $newPath = null;

        try {
            return DB::transaction(function () use ($barangay, $activity, $user, $conduct, $evidence, &$newPath): RcspForm {
                $lockedBarangay = RcspBarangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
                $lockedActivity = RcspActivity::with('phase')->whereKey($activity->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('submitActivity', [$lockedBarangay, $lockedActivity]);
                $phase = $this->currentPhase($lockedBarangay);

                if ($lockedActivity->rcsp_phase_id !== $phase->id
                    || ! RcspActivity::forBarangayPhase($lockedBarangay, $phase)->whereKey($lockedActivity->id)->exists()) {
                    throw ValidationException::withMessages([
                        'activity' => 'The activity must belong to this barangay and its current phase.',
                    ]);
                }

                $latest = RcspForm::where('rcsp_barangay_id', $lockedBarangay->id)
                    ->where('rcsp_phase_id', $phase->id)
                    ->where('rcsp_activity_id', $lockedActivity->id)
                    ->orderByDesc('submission_version')->orderByDesc('id')->lockForUpdate()->first();
                if ($latest && ! in_array($latest->status, ['disapproved', 'to be complied', 'to be conducted'], true)) {
                    throw ValidationException::withMessages([
                        'activity' => 'Only a returned activity may be resubmitted.',
                    ]);
                }

                $file = $latest?->file;
                $originalFilename = $latest?->original_filename;
                $detectedMimeType = $latest?->detected_mime_type;
                $fileSizeBytes = $latest?->file_size_bytes;
                if ($evidence) {
                    $newPath = $evidence->store("rcsp/{$lockedBarangay->id}", 'local');
                    if (! $newPath) {
                        throw ValidationException::withMessages(['evidence' => 'The evidence file could not be stored.']);
                    }
                    $file = 'private:'.$newPath;
                    $originalFilename = basename(str_replace('\\', '/', $evidence->getClientOriginalName()));
                    $detectedMimeType = $evidence->getMimeType();
                    $fileSizeBytes = $evidence->getSize();
                } elseif ($conduct === 'yes' && ! $file) {
                    throw ValidationException::withMessages([
                        'evidence' => 'Evidence is required when the activity is marked as Conducted.',
                    ]);
                }

                if ($file) {
                    $path = str_starts_with($file, 'private:') ? substr($file, 8) : $file;
                    if (! $path || ! $this->evidenceExists(new RcspForm([
                        'rcsp_barangay_id' => $lockedBarangay->id,
                        'file' => $file,
                    ]))) {
                        throw ValidationException::withMessages(['evidence' => 'The supporting evidence file is unavailable.']);
                    }
                }

                $form = RcspForm::create([
                    'lgu_user_id' => $user->id,
                    'rcsp_barangay_id' => $lockedBarangay->id,
                    'rcsp_phase_id' => $phase->id,
                    'rcsp_activity_id' => $lockedActivity->id,
                    'submission_version' => ($latest?->submission_version ?? 0) + 1,
                    'conduct' => $conduct,
                    'file' => $file,
                    'original_filename' => $originalFilename,
                    'detected_mime_type' => $detectedMimeType,
                    'file_size_bytes' => $fileSizeBytes,
                    'submitted_at' => now(),
                    'status' => 'submitted',
                ]);

                if ($lockedBarangay->status === 'Pending') {
                    $lockedBarangay->update(['status' => 'Ongoing']);
                }

                return $form;
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }

            if ($exception instanceof QueryException && $this->isFormVersionConflict($exception)) {
                throw ValidationException::withMessages([
                    'activity' => 'This activity was already resubmitted. Refresh before trying again.',
                ]);
            }

            throw $exception;
        }
    }

    public function reviewPhase(RcspBarangay $barangay, User $reviewer, array $statuses, array $remarks): void
    {
        DB::transaction(function () use ($barangay, $reviewer, $statuses, $remarks): void {
            $lockedBarangay = RcspBarangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($reviewer)->authorize('review', $lockedBarangay);
            $currentPhase = $this->currentPhase($lockedBarangay);

            $forms = RcspForm::where('rcsp_barangay_id', $lockedBarangay->id)
                ->where('rcsp_phase_id', $currentPhase->id)
                ->whereIn('id', array_keys($statuses))->lockForUpdate()->get()->keyBy('id');
            $latestIds = RcspForm::where('rcsp_barangay_id', $lockedBarangay->id)
                ->where('rcsp_phase_id', $currentPhase->id)
                ->whereIn('rcsp_activity_id', $forms->pluck('rcsp_activity_id'))
                ->orderByDesc('submission_version')->orderByDesc('id')->lockForUpdate()
                ->get(['id', 'rcsp_activity_id'])->unique('rcsp_activity_id')
                ->pluck('id', 'rcsp_activity_id');
            foreach ($statuses as $id => $status) {
                $form = $forms->get((int) $id);
                if (! $form
                    || $form->id !== $latestIds->get($form->rcsp_activity_id)
                    || $form->status !== 'submitted') {
                    throw ValidationException::withMessages([
                        "statuses.$id" => 'Only the latest submitted activity version may be reviewed.',
                    ]);
                }
                if ($status === 'approved'
                    && $form->conduct === 'yes'
                    && ! $this->evidenceExists($form)) {
                    throw ValidationException::withMessages([
                        "statuses.$id" => 'The supporting evidence file is unavailable.',
                    ]);
                }

                $remark = trim((string) ($remarks[$id] ?? '')) ?: null;
                $reviewedAt = now();
                RcspFormReview::create([
                    'rcsp_form_id' => $form->id,
                    'reviewer_user_id' => $reviewer->id,
                    'status' => $status,
                    'remarks' => $remark,
                    'reviewed_at' => $reviewedAt,
                ]);
                $form->update([
                    'status' => $status,
                    'remarks' => $remark,
                    'reviewed_by_user_id' => $reviewer->id,
                    'reviewed_at' => $reviewedAt,
                ]);
            }
        });
    }

    public function advance(RcspBarangay $barangay, User $actor): void
    {
        DB::transaction(function () use ($barangay, $actor): void {
            $lockedBarangay = RcspBarangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $lockedBarangay);
            $phase = $this->currentPhase($lockedBarangay);
            $activities = RcspActivity::forBarangayPhase($lockedBarangay, $phase)
                ->orderBy('id')->lockForUpdate()->get();
            if ($activities->isEmpty()) {
                throw ValidationException::withMessages([
                    'phase' => 'At least one current-phase activity is required before proceeding.',
                ]);
            }

            $latestForms = RcspForm::where('rcsp_barangay_id', $lockedBarangay->id)
                ->where('rcsp_phase_id', $phase->id)
                ->whereIn('rcsp_activity_id', $activities->pluck('id'))
                ->orderByDesc('submission_version')->orderByDesc('id')->lockForUpdate()->get()
                ->unique('rcsp_activity_id')->keyBy('rcsp_activity_id');
            foreach ($activities as $activity) {
                $latest = $latestForms->get($activity->id);
                if (! $latest
                    || $latest->status !== 'approved'
                    || ($latest->conduct === 'yes' && ! $this->evidenceExists($latest))) {
                    throw ValidationException::withMessages([
                        'phase' => 'Every current-phase activity must have an approved latest submission, with evidence for Conducted activities.',
                    ]);
                }
            }

            $number = (int) $lockedBarangay->current_phase;
            RcspPhaseTransition::create([
                'rcsp_barangay_id' => $lockedBarangay->id,
                'from_phase' => $number,
                'to_phase' => $number >= 5 ? null : $number + 1,
                'advanced_by_user_id' => $actor->id,
                'advanced_at' => now(),
            ]);
            $lockedBarangay->phaseStatus()->lockForUpdate()->first();
            $lockedBarangay->phaseStatus()->updateOrCreate([], ["phase{$number}_completed" => true]);
            $number >= 5
                ? $lockedBarangay->update(['status' => 'Completed'])
                : $lockedBarangay->update(['current_phase' => $number + 1]);
        });
    }

    private function currentPhase(RcspBarangay $barangay): RcspPhase
    {
        $phase = RcspPhase::where('catalog_key', $barangay->catalog_key)
            ->where('number', $barangay->current_phase)->first();
        if (! $phase || ! array_key_exists((int) $phase->number, RcspPhase::STRUCTURAL_NAMES)) {
            throw ValidationException::withMessages(['phase' => 'The current RCSP phase is unavailable.']);
        }

        return $phase;
    }

    private function evidenceExists(RcspForm $form): bool
    {
        if (! $form->file || str_contains($form->file, '..') || str_contains($form->file, '\\')) {
            return false;
        }

        $private = str_starts_with($form->file, 'private:');
        $path = $private ? substr($form->file, 8) : $form->file;

        return str_starts_with($path, "rcsp/{$form->rcsp_barangay_id}/")
            && Storage::disk($private ? 'local' : 'public')->exists($path);
    }

    private function isBarangayConflict(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rcsp_barangays_barangay_unique')
            || (str_contains($message, 'unique constraint') && str_contains($message, 'rcsp_barangays.barangay_id'));
    }

    private function isFormVersionConflict(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rcsp_form_submission_version_unique')
            || (str_contains($message, 'unique constraint') && str_contains($message, 'submission_version'));
    }
}
