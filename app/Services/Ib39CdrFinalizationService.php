<?php

namespace App\Services;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrPhotoType;
use App\Enums\Ib39CdrStatus;
use App\Models\AuditLog;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39CdrForm;
use App\Models\Ib39CdrProcessing;
use App\Models\User;
use App\Support\Ib39CdrFormSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ib39CdrFinalizationService
{
    public function __construct(private readonly JapicCertificationIntakeService $japicIntake) {}

    public function fingerprint(Ib39CdrProcessing $processing): string
    {
        $form = $processing->form()->firstOrFail();
        $photoVersionId = $processing->photos()->where('photo_type', Ib39CdrPhotoType::FrPhoto)->value('current_photo_version_id');

        return $this->makeFingerprint($form, $photoVersionId);
    }

    public function finalize(
        Ib39CdrProcessing $processing,
        string $expectedFingerprint,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrDocumentVersion {
        abort_unless($actor->is_active && $actor->hasRole('39th_ib'), 403);

        return DB::transaction(function () use ($processing, $expectedFingerprint, $actor, $ipAddress, $userAgent) {
            $locked = Ib39CdrProcessing::query()->lockForUpdate()->findOrFail($processing->id);
            abort_unless($locked->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists(), 403);
            if ($locked->status !== Ib39CdrStatus::Ongoing || $locked->current_final_version_id !== null) {
                throw ValidationException::withMessages(['cdr' => 'This CDR is no longer available for finalization.']);
            }

            $form = Ib39CdrForm::query()->where('cdr_processing_id', $locked->id)->lockForUpdate()->firstOrFail();
            $photoVersionId = $locked->photos()->where('photo_type', Ib39CdrPhotoType::FrPhoto)->lockForUpdate()->value('current_photo_version_id');
            if (! hash_equals($this->makeFingerprint($form, $photoVersionId), $expectedFingerprint)) {
                throw ValidationException::withMessages(['draft_fingerprint' => 'The CDR draft changed after review. Review it again before final submission.']);
            }

            $snapshot = ['content' => $form->content ?? [], 'fr_photo_version_id' => $photoVersionId];
            $serialized = json_encode($snapshot, JSON_THROW_ON_ERROR);
            $versionNumber = ((int) $locked->documentVersions()->max('version_number')) + 1;
            $finalizedAt = now();
            $version = $locked->documentVersions()->create([
                'version_number' => $versionNumber,
                'source_type' => Ib39CdrDocumentSource::Generated,
                'replaces_version_id' => null,
                'replacement_reason' => null,
                'storage_path' => "generated/cdr/{$locked->id}/version/{$versionNumber}",
                'original_filename' => "generated-cdr-v{$versionNumber}.html",
                'mime_type' => 'text/html',
                'size_bytes' => strlen($serialized),
                'sha256' => hash('sha256', $serialized),
                'content_schema_version' => Ib39CdrFormSchema::VERSION,
                'content_snapshot' => $snapshot,
                'created_by' => $actor->id,
                'finalized_at' => $finalizedAt,
            ]);

            $locked->update([
                'status' => Ib39CdrStatus::Completed,
                'completed_at' => $finalizedAt,
                'completed_by' => $actor->id,
                'current_final_version_id' => $version->id,
            ]);
            $locked->statusHistories()->create([
                'user_id' => $actor->id,
                'from_status' => Ib39CdrStatus::Ongoing,
                'to_status' => Ib39CdrStatus::Completed,
                'event' => 'system_authored_finalized',
                'document_version_id' => $version->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            ]);
            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'ib39_cdr_system_authored_finalized',
                'entity_type' => Ib39CdrProcessing::class,
                'entity_id' => $locked->id,
                'previous_values' => ['status' => Ib39CdrStatus::Ongoing->value],
                'new_values' => ['status' => Ib39CdrStatus::Completed->value, 'version_number' => $versionNumber, 'source_type' => Ib39CdrDocumentSource::Generated->value],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            ]);

            $this->japicIntake->createForCompletedCdr($locked->fresh());

            return $version;
        }, 5);
    }

    private function makeFingerprint(Ib39CdrForm $form, ?int $photoVersionId): string
    {
        $payload = json_encode([
            'form_id' => $form->id,
            'schema_version' => $form->schema_version,
            'content' => $form->content ?? [],
            'updated_at' => $form->updated_at?->format('Y-m-d H:i:s.u'),
            'photo_version_id' => $photoVersionId,
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
