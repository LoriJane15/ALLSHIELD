<?php

namespace App\Services;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\JapicCertificationDraft;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Support\JapicCertificationDraftSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class JapicCertificationWorkflowService
{
    public function __construct(private readonly JapicCertificationDraftSchema $schema) {}

    public function submitForSigning(JapicCertificationProcessing $processing, int $revision, int $lockVersion, ?string $delayReason, User $actor): void
    {
        $this->transition($processing, $revision, $lockVersion, $delayReason, $actor, JapicCertificationStatus::Drafting,
            JapicCertificationStatus::ForSigning, JapicCertificationEvent::MarkedForSigning, true);
    }

    public function confirmSigningComplete(JapicCertificationProcessing $processing, int $revision, int $lockVersion, ?string $delayReason, User $actor): void
    {
        $this->transition($processing, $revision, $lockVersion, $delayReason, $actor, JapicCertificationStatus::ForSigning,
            JapicCertificationStatus::AwaitingFinalUpload, JapicCertificationEvent::SignaturesSecured, false);
    }

    private function transition(JapicCertificationProcessing $processing, int $revision, int $lockVersion, ?string $delayReason, User $actor, JapicCertificationStatus $from, JapicCertificationStatus $to, JapicCertificationEvent $event, bool $requireComplete): void
    {
        DB::transaction(function () use ($processing, $revision, $lockVersion, $delayReason, $actor, $from, $to, $event, $requireComplete) {
            $locked = JapicCertificationProcessing::query()->lockForUpdate()->findOrFail($processing->id);
            abort_unless($actor->is_active && $actor->hasRole('japic') && ($locked->assigned_to === null || $locked->assigned_to === $actor->id), 403);
            abort_if($locked->surfacedFormerRebel()->whereHas('cancellation')->exists(), 403, 'Cancelled certifications are read-only.');
            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['status' => 'This workflow action is not available in the current status.']);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw new ConflictHttpException('A newer certification change exists. Reload before continuing.');
            }
            $draft = JapicCertificationDraft::query()->where('processing_id', $locked->id)->lockForUpdate()->firstOrFail();
            if ($draft->revision !== $revision) {
                throw new ConflictHttpException('A newer draft revision exists. Reload before continuing.');
            }
            if ($locked->due_at->copy()->startOfDay()->lt(now()->startOfDay()) && blank($delayReason)) {
                throw ValidationException::withMessages(['delay_reason' => 'A delay reason is required for an overdue certification.']);
            }
            $fingerprint = $this->schema->fingerprint($draft->payload);
            $normalized = $this->schema->forReading($draft->payload, $locked->control_number);
            if ($requireComplete && ($missing = $this->schema->missingForSigning($normalized, $locked->control_number)) !== []) {
                throw ValidationException::withMessages(['draft' => 'Complete all required certification fields before submitting for signing.', 'missing_fields' => implode(', ', $missing)]);
            }
            if (! $requireComplete) {
                $frozen = $locked->histories()->where('event', JapicCertificationEvent::MarkedForSigning->value)->latest('occurred_at')->firstOrFail();
                if ((int) data_get($frozen->metadata, 'revision') !== $revision || ! hash_equals((string) data_get($frozen->metadata, 'payload_fingerprint'), $fingerprint)) {
                    throw new ConflictHttpException('The frozen signing revision no longer matches.');
                }
            }
            $now = now();
            $locked->forceFill(['status' => $to, 'lock_version' => $locked->lock_version + 1])->save();
            $locked->histories()->create(['actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $to, 'event' => $event,
                'delay_reason' => $delayReason, 'metadata' => ['revision' => $revision, 'schema_version' => $draft->schema_version,
                    'payload_fingerprint' => $fingerprint, 'control_number_hash' => $locked->control_number_hash], 'occurred_at' => $now]);
        }, 5);
    }
}
