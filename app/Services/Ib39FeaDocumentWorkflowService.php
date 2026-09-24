<?php

namespace App\Services;

use App\Contracts\Ib39FeaReadiness;
use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentHistoryEvent;
use App\Enums\Ib39FeaDocumentStatus;
use App\Models\AuditLog;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaProcessing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ib39FeaDocumentWorkflowService
{
    public function __construct(
        private readonly Ib39FeaDraftSchema $draftSchema,
        private readonly Ib39FeaReadiness $readiness,
    ) {}

    public function start(Ib39FeaProcessing $processing, Ib39FeaDocument $document, User $actor): Ib39FeaDocument
    {
        return DB::transaction(function () use ($processing, $document, $actor) {
            [$lockedProcessing, $lockedDocument] = $this->lock($processing, $document, $actor);

            if ($lockedDocument->status === Ib39FeaDocumentStatus::Completed) {
                throw ValidationException::withMessages(['document' => 'Completed documents cannot be changed in Stage 2.']);
            }

            if ($lockedDocument->status === Ib39FeaDocumentStatus::Processing) {
                return $lockedDocument;
            }

            $overallBefore = $lockedProcessing->overallStatus();
            $startedAt = now();
            $lockedDocument->update([
                'status' => Ib39FeaDocumentStatus::Processing,
                'started_at' => $startedAt,
                'prepared_by' => $actor->id,
                'last_updated_by' => $actor->id,
            ]);
            $this->history(
                $lockedDocument,
                $actor,
                Ib39FeaDocumentHistoryEvent::ProcessingStarted,
                ['status' => Ib39FeaDocumentStatus::Pending->value],
                ['status' => Ib39FeaDocumentStatus::Processing->value],
            );
            $this->audit($lockedDocument, $actor, 'ib39_fea_preliminary_work_started', ['status']);
            $this->recordProcessingStatusChange($lockedProcessing, $overallBefore, $actor);

            return $lockedDocument->fresh();
        }, 5);
    }

    public function update(
        Ib39FeaProcessing $processing,
        Ib39FeaDocument $document,
        array $data,
        User $actor,
    ): Ib39FeaDocument {
        return DB::transaction(function () use ($processing, $document, $data, $actor) {
            [$lockedProcessing, $lockedDocument] = $this->lock($processing, $document, $actor);

            if ($lockedDocument->status === Ib39FeaDocumentStatus::Completed
                || ($data['status'] ?? null) !== Ib39FeaDocumentStatus::Processing->value) {
                throw ValidationException::withMessages(['document.status' => 'Only preliminary Processing status is allowed in Stage 2.']);
            }

            $overallBefore = $lockedProcessing->overallStatus();
            $events = [];
            $changes = [];

            if ($lockedDocument->status === Ib39FeaDocumentStatus::Pending) {
                $changes += [
                    'status' => Ib39FeaDocumentStatus::Processing,
                    'started_at' => now(),
                    'prepared_by' => $actor->id,
                ];
                $events[] = [
                    Ib39FeaDocumentHistoryEvent::ProcessingStarted,
                    ['status' => Ib39FeaDocumentStatus::Pending->value],
                    ['status' => Ib39FeaDocumentStatus::Processing->value],
                ];
            }

            $compliance = Ib39FeaComplianceStatus::from($data['compliance_status']);
            $complianceReason = $compliance === Ib39FeaComplianceStatus::None ? null : $data['compliance_reason'];
            if ($lockedDocument->compliance_status !== $compliance || $lockedDocument->compliance_reason !== $complianceReason) {
                $changes['compliance_status'] = $compliance;
                $changes['compliance_reason'] = $complianceReason;
                $events[] = [
                    Ib39FeaDocumentHistoryEvent::ComplianceChanged,
                    ['status' => $lockedDocument->compliance_status->value, 'reason' => $lockedDocument->compliance_reason],
                    ['status' => $compliance->value, 'reason' => $complianceReason],
                ];
            }

            if ($lockedDocument->remarks !== $data['remarks']) {
                $changes['remarks'] = $data['remarks'];
                $events[] = [
                    Ib39FeaDocumentHistoryEvent::RemarksChanged,
                    ['remarks' => $lockedDocument->remarks],
                    ['remarks' => $data['remarks']],
                ];
            }

            $isDelayed = (bool) $data['is_delayed'];
            $delayReason = $isDelayed ? $data['delay_reason'] : null;
            if ($lockedDocument->is_delayed !== $isDelayed || $lockedDocument->delay_reason !== $delayReason) {
                $changes['is_delayed'] = $isDelayed;
                $changes['delay_reason'] = $delayReason;
                $events[] = [
                    Ib39FeaDocumentHistoryEvent::DelayChanged,
                    ['is_delayed' => $lockedDocument->is_delayed, 'reason' => $lockedDocument->delay_reason],
                    ['is_delayed' => $isDelayed, 'reason' => $delayReason],
                ];
            }

            if ($events === []) {
                return $lockedDocument;
            }

            $changes['last_updated_by'] = $actor->id;
            $lockedDocument->update($changes);
            foreach ($events as [$event, $previous, $new]) {
                $this->history($lockedDocument, $actor, $event, $previous, $new);
            }

            $this->audit($lockedDocument, $actor, 'ib39_fea_preliminary_document_updated', array_keys($changes));
            $this->recordProcessingStatusChange($lockedProcessing, $overallBefore, $actor);

            return $lockedDocument->fresh();
        }, 5);
    }

    public function saveDraft(
        Ib39FeaProcessing $processing,
        Ib39FeaDocument $document,
        array $draft,
        int $expectedRevision,
        User $actor,
    ): Ib39FeaDocument {
        return DB::transaction(function () use ($processing, $document, $draft, $expectedRevision, $actor) {
            [$lockedProcessing, $lockedDocument] = $this->lock($processing, $document, $actor);
            abort_unless($lockedDocument->document_type->hasDraftEditor(), 403);

            if ($lockedDocument->status === Ib39FeaDocumentStatus::Completed) {
                throw ValidationException::withMessages(['draft' => 'Completed documents cannot be edited.']);
            }
            if ($lockedDocument->draft_revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'revision' => 'This draft was changed by another user. Reload the editor and review the latest revision before saving.',
                ]);
            }

            $previous = $lockedDocument->draft_data ?? [];
            // Preserve encrypted values retired by newer schemas without exposing
            // them as editable or accepting them back from the request.
            $draft = array_replace($previous, $draft);
            $changedFields = $this->draftSchema->changedFields($previous, $draft);
            if ($lockedDocument->draft_data !== null && $changedFields === []) {
                return $lockedDocument;
            }

            if ($lockedDocument->status === Ib39FeaDocumentStatus::Pending) {
                $this->start($lockedProcessing, $lockedDocument, $actor);
                $lockedDocument->refresh();
            }

            $revision = $lockedDocument->draft_revision + 1;
            $lockedDocument->update([
                'draft_data' => $draft,
                'draft_schema_version' => Ib39FeaDraftSchema::VERSION,
                'draft_revision' => $revision,
                'draft_saved_at' => now(),
                'draft_saved_by' => $actor->id,
                'last_updated_by' => $actor->id,
            ]);
            $lockedDocument->draftHistories()->create([
                'fea_processing_id' => $lockedProcessing->id,
                'user_id' => $actor->id,
                'revision' => $revision,
                'changed_fields' => $changedFields,
            ]);
            $this->audit($lockedDocument, $actor, 'ib39_fea_draft_saved', ['draft_revision', ...$changedFields]);

            return $lockedDocument->fresh();
        }, 5);
    }

    private function lock(Ib39FeaProcessing $processing, Ib39FeaDocument $document, User $actor): array
    {
        abort_unless($actor->is_active && $actor->hasRole('39th_ib'), 403);
        $lockedProcessing = Ib39FeaProcessing::query()->lockForUpdate()->findOrFail($processing->id);
        $record = $lockedProcessing->surfacedFormerRebel()
            ->whereDoesntHave('cancellation')
            ->lockForUpdate()
            ->first();
        abort_unless($record, 403);
        $lockedDocument = Ib39FeaDocument::query()
            ->where('fea_processing_id', $lockedProcessing->id)
            ->lockForUpdate()
            ->findOrFail($document->id);
        $this->readiness->assertReady($record);

        return [$lockedProcessing, $lockedDocument];
    }

    private function history(
        Ib39FeaDocument $document,
        User $actor,
        Ib39FeaDocumentHistoryEvent $event,
        array $previous,
        array $new,
    ): void {
        $document->histories()->create([
            'fea_processing_id' => $document->fea_processing_id,
            'user_id' => $actor->id,
            'event' => $event,
            'previous_values' => $previous,
            'new_values' => $new,
        ]);
    }

    private function recordProcessingStatusChange(Ib39FeaProcessing $processing, $before, User $actor): void
    {
        $after = $processing->fresh()->overallStatus();
        if ($before === $after) {
            return;
        }

        $processing->histories()->create([
            'user_id' => $actor->id,
            'from_status' => $before,
            'to_status' => $after,
            'event' => 'overall_status_changed',
        ]);
    }

    private function audit(Ib39FeaDocument $document, User $actor, string $action, array $changedFields): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'entity_type' => Ib39FeaDocument::class,
            'entity_id' => $document->id,
            'previous_values' => null,
            'new_values' => [
                'document_type' => $document->document_type->value,
                'changed_fields' => array_values(array_diff($changedFields, ['last_updated_by'])),
            ],
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}
