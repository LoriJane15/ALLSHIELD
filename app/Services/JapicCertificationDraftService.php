<?php

namespace App\Services;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\JapicCertificationDraft;
use App\Models\JapicCertificationPhotoVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Support\JapicCertificationDraftSchema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class JapicCertificationDraftService
{
    public function __construct(private readonly JapicCertificationDraftSchema $schema) {}

    public function save(JapicCertificationProcessing $processing, array $manual, string $controlNumber, int $expectedRevision, int $expectedLockVersion, ?string $delayReason, User $actor): JapicCertificationDraft
    {
        return DB::transaction(function () use ($processing, $manual, $controlNumber, $expectedRevision, $expectedLockVersion, $delayReason, $actor) {
            $locked = JapicCertificationProcessing::query()->lockForUpdate()->findOrFail($processing->id);
            $this->assertActor($locked, $actor);
            $this->assertEditable($locked);
            $this->assertLockVersion($locked, $expectedLockVersion);
            $draft = JapicCertificationDraft::query()->where('processing_id', $locked->id)->lockForUpdate()->first();
            $this->assertRevision($draft, $expectedRevision);
            $this->assertDelay($locked, $delayReason);

            $controlNumber = trim($controlNumber);
            $controlHash = $this->schema->controlNumberHash($controlNumber);
            if (filled($locked->control_number_hash) && ! hash_equals((string) $locked->control_number_hash, $controlHash)) {
                throw ValidationException::withMessages(['control_number' => 'The assigned control number cannot be changed.']);
            }
            if (JapicCertificationProcessing::query()->where('control_number_hash', $controlHash)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['control_number' => 'This control number is unavailable.']);
            }

            $source = $draft?->payload['source_snapshot'] ?? $this->schema->sourceSnapshot($locked);
            $payload = $this->schema->normalize($manual, $source, $controlNumber, $locked->current_photo_version_id);
            $current = $draft ? $this->schema->forReading($draft->payload, $locked->control_number) : null;
            if ($current && hash_equals($this->schema->fingerprint($current), $this->schema->fingerprint($payload))
                && hash_equals((string) $locked->control_number_hash, $controlHash)) {
                return $draft;
            }

            try {
                return $this->persist($locked, $draft, $payload, $controlNumber, $controlHash, $delayReason, $actor);
            } catch (QueryException $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'control')) {
                    throw ValidationException::withMessages(['control_number' => 'This control number is unavailable.']);
                }

                throw $exception;
            }
        }, 5);
    }

    public function recordPhotoSelection(
        JapicCertificationProcessing $processing,
        JapicCertificationPhotoVersion $photo,
        int $expectedRevision,
        int $expectedLockVersion,
        User $actor,
    ): JapicCertificationDraft {
        return DB::transaction(function () use ($processing, $photo, $expectedRevision, $expectedLockVersion, $actor) {
            $locked = JapicCertificationProcessing::query()->lockForUpdate()->findOrFail($processing->id);
            $this->assertActor($locked, $actor);
            $this->assertEditable($locked);
            $this->assertLockVersion($locked, $expectedLockVersion);
            abort_unless($photo->processing_id === $locked->id, 404);
            $draft = JapicCertificationDraft::query()->where('processing_id', $locked->id)->lockForUpdate()->first();
            $this->assertRevision($draft, $expectedRevision);
            $source = $draft?->payload['source_snapshot'] ?? $this->schema->sourceSnapshot($locked);
            $payload = $draft
                ? $this->schema->forReading($draft->payload, $locked->control_number)
                : $this->schema->initial($source, $locked->control_number, null);
            data_set($payload, 'certificate.photo_version_id', $photo->id);
            $payload = $this->schema->forReading($payload, $locked->control_number);
            $locked->forceFill(['current_photo_version_id' => $photo->id])->save();

            return $this->persist(
                $locked,
                $draft,
                $payload,
                $locked->control_number,
                $locked->control_number_hash,
                null,
                $actor,
            );
        }, 5);
    }

    private function persist(
        JapicCertificationProcessing $processing,
        ?JapicCertificationDraft $draft,
        array $payload,
        ?string $controlNumber,
        ?string $controlHash,
        ?string $delayReason,
        User $actor,
    ): JapicCertificationDraft {
        $revision = ($draft?->revision ?? 0) + 1;
        $now = now();
        $draft ??= new JapicCertificationDraft;
        $draft->forceFill([
            'processing_id' => $processing->id,
            'payload' => $payload,
            'schema_version' => JapicCertificationDraftSchema::VERSION,
            'revision' => $revision,
            'last_saved_by' => $actor->id,
            'last_saved_at' => $now,
        ])->save();
        $processing->draftHistories()->create([
            'revision' => $revision,
            'payload' => $payload,
            'saved_by' => $actor->id,
            'saved_at' => $now,
        ]);
        $from = $processing->status;
        $processing->forceFill([
            'status' => JapicCertificationStatus::Drafting,
            'started_at' => $processing->started_at ?? $now,
            'started_by' => $processing->started_by ?? $actor->id,
            'control_number' => $controlNumber,
            'control_number_hash' => $controlHash,
            'lock_version' => $processing->lock_version + 1,
        ])->save();
        $processing->histories()->create([
            'actor_id' => $actor->id,
            'from_status' => $from,
            'to_status' => JapicCertificationStatus::Drafting,
            'event' => $from === JapicCertificationStatus::Pending ? JapicCertificationEvent::Started : JapicCertificationEvent::DraftSaved,
            'delay_reason' => $delayReason,
            'metadata' => [
                'revision' => $revision,
                'schema_version' => JapicCertificationDraftSchema::VERSION,
                'payload_fingerprint' => $this->schema->fingerprint($payload),
            ],
            'occurred_at' => $now,
        ]);

        return $draft->fresh();
    }

    private function assertActor(JapicCertificationProcessing $processing, User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasRole('japic') && ($processing->assigned_to === null || $processing->assigned_to === $actor->id), 403);
        abort_if($processing->surfacedFormerRebel()->whereHas('cancellation')->exists(), 403, 'Cancelled certifications are read-only.');
    }

    private function assertEditable(JapicCertificationProcessing $processing): void
    {
        if (! in_array($processing->status, [JapicCertificationStatus::Pending, JapicCertificationStatus::Drafting], true)) {
            throw ValidationException::withMessages(['draft' => 'This certification is not available for editing.']);
        }
    }

    private function assertLockVersion(JapicCertificationProcessing $processing, int $expected): void
    {
        if ($processing->lock_version !== $expected) {
            throw new ConflictHttpException('A newer certification change exists. Reload before saving.');
        }
    }

    private function assertRevision(?JapicCertificationDraft $draft, int $expected): void
    {
        if (($draft?->revision ?? 0) !== $expected) {
            throw new ConflictHttpException('A newer draft revision exists. Reload before saving.');
        }
    }

    private function assertDelay(JapicCertificationProcessing $processing, ?string $reason): void
    {
        if ($processing->due_at->copy()->startOfDay()->lt(now()->startOfDay()) && blank($reason)) {
            throw ValidationException::withMessages(['delay_reason' => 'A delay reason is required for an overdue certification.']);
        }
    }
}
