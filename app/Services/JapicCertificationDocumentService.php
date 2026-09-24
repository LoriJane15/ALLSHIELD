<?php

namespace App\Services;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\AuditLog;
use App\Models\Ib39CdrPhotoVersion;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationDraft;
use App\Models\JapicCertificationPhotoVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Support\JapicCertificationDraftSchema;
use App\Support\JapicCertificationUploadedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class JapicCertificationDocumentService
{
    public function __construct(
        private readonly JapicCertificationDraftSchema $schema,
        private readonly PswdoEnrollmentIntakeService $pswdoIntake,
    ) {}

    public function data(JapicCertificationProcessing $processing): array
    {
        $processing->loadMissing(['draft', 'draftHistories', 'surfacedFormerRebel.cancellation', 'triggeringCdrDocumentVersion']);
        abort_unless($processing->draft, 404, 'No certification draft is available.');
        $storedPayload = $processing->draft->payload;
        $revision = $processing->draft->revision;
        $event = $processing->histories()->where('event', JapicCertificationEvent::MarkedForSigning->value)->latest('occurred_at')->first();
        $requiresFrozen = in_array($processing->status, [JapicCertificationStatus::ForSigning, JapicCertificationStatus::AwaitingFinalUpload, JapicCertificationStatus::Completed], true);
        if ($requiresFrozen || ($processing->status === JapicCertificationStatus::Cancelled && $event)) {
            abort_unless($event, 409, 'The frozen signing revision is unavailable.');
            $revision = (int) data_get($event->metadata, 'revision');
            $history = $processing->draftHistories->firstWhere('revision', $revision);
            abort_unless($history && hash_equals((string) data_get($event->metadata, 'payload_fingerprint'), $this->schema->fingerprint($history->payload)), 409, 'The frozen signing revision is inconsistent.');
            $storedPayload = $history->payload;
        }
        $payload = $this->schema->forReading($storedPayload, $processing->control_number);

        return [
            'processing' => $processing,
            'payload' => $payload,
            'revision' => $revision,
            'wording' => $this->schema->wording($payload),
            'affiliationPeriod' => data_get($payload, 'source_snapshot.affiliation_period'),
            'copyFurnished' => JapicCertificationDraftSchema::COPY_FURNISHED,
            'photoDataUri' => $this->photoDataUri($processing, $payload),
        ];
    }

    public function uploadFinal(
        JapicCertificationProcessing $processing,
        UploadedFile $file,
        int $expectedRevision,
        int $expectedLockVersion,
        bool $allSignatoriesConfirmed,
        bool $correctFinalConfirmed,
        User $actor,
        ?string $ipAddress,
        ?string $userAgent,
    ): JapicCertificationDocumentVersion {
        $this->assertUploadActor($processing, $actor);
        $metadata = JapicCertificationUploadedFile::inspectFinalPdf($file);
        $path = sprintf('japic/certifications/%d/final-documents/%s.pdf', $processing->id, Str::uuid());

        if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
            throw new RuntimeException('The final certification could not be stored.');
        }

        try {
            return DB::transaction(function () use (
                $processing,
                $expectedRevision,
                $expectedLockVersion,
                $allSignatoriesConfirmed,
                $correctFinalConfirmed,
                $actor,
                $ipAddress,
                $userAgent,
                $metadata,
                $path,
            ): JapicCertificationDocumentVersion {
                $locked = JapicCertificationProcessing::query()->lockForUpdate()->findOrFail($processing->id);
                $this->assertUploadActor($locked, $actor);
                if (! in_array($locked->status, [
                    JapicCertificationStatus::Pending,
                    JapicCertificationStatus::Drafting,
                    JapicCertificationStatus::ForSigning,
                    JapicCertificationStatus::AwaitingFinalUpload,
                ], true) || $locked->current_final_version_id !== null) {
                    throw ValidationException::withMessages([
                        'document' => 'This certification is no longer available for final upload.',
                    ]);
                }
                if ($locked->lock_version !== $expectedLockVersion) {
                    throw new ConflictHttpException('A newer certification change exists. Reload before uploading.');
                }
                if (! $allSignatoriesConfirmed || ! $correctFinalConfirmed) {
                    throw ValidationException::withMessages([
                        'document' => 'Both final-document confirmations are required.',
                    ]);
                }
                if ($locked->documentVersions()->where('sha256', $metadata['sha256'])->exists()) {
                    throw ValidationException::withMessages([
                        'document' => 'This final certification has already been uploaded for this FR.',
                    ]);
                }

                $draft = JapicCertificationDraft::query()->where('processing_id', $locked->id)->lockForUpdate()->first();
                if ($locked->status === JapicCertificationStatus::Pending) {
                    if ($draft !== null || $expectedRevision !== 0) {
                        throw new ConflictHttpException('The certification draft state changed. Reload before uploading.');
                    }
                    $completionPath = 'direct_from_pending';
                } else {
                    if (! $draft || $draft->revision !== $expectedRevision) {
                        throw new ConflictHttpException('A newer certification draft revision exists. Reload before uploading.');
                    }
                    $payload = $this->schema->forReading($draft->payload, $locked->control_number);
                    if (($missing = $this->schema->missingForSigning($payload, $locked->control_number)) !== []) {
                        throw ValidationException::withMessages([
                            'draft' => 'Complete all required certification fields, including Prepared By and Attested By personnel and ranks, before uploading the final certification.',
                            'missing_fields' => implode(', ', $missing),
                        ]);
                    }
                    if (in_array($locked->status, [JapicCertificationStatus::ForSigning, JapicCertificationStatus::AwaitingFinalUpload], true)) {
                        $frozen = $locked->histories()->where('event', JapicCertificationEvent::MarkedForSigning->value)
                            ->latest('occurred_at')->first();
                        $fingerprint = $this->schema->fingerprint($draft->payload);
                        if (! $frozen || (int) data_get($frozen->metadata, 'revision') !== $draft->revision
                            || ! hash_equals((string) data_get($frozen->metadata, 'payload_fingerprint'), $fingerprint)) {
                            throw new ConflictHttpException('The frozen signing revision no longer matches.');
                        }
                    }
                    $completionPath = $locked->status === JapicCertificationStatus::Drafting
                        ? 'direct_from_drafting'
                        : 'signed_workflow';
                }

                $fromStatus = $locked->status;
                $uploadedAt = now();
                $version = $locked->documentVersions()->create([
                    'version_number' => ((int) $locked->documentVersions()->max('version_number')) + 1,
                    'replaces_version_id' => null,
                    'replacement_reason' => null,
                    'storage_path' => $path,
                    'original_filename' => $metadata['original_filename'],
                    'mime_type' => $metadata['mime_type'],
                    'size_bytes' => $metadata['size_bytes'],
                    'sha256' => $metadata['sha256'],
                    'uploaded_by' => $actor->id,
                    'all_signatories_confirmed' => true,
                    'correct_final_confirmed' => true,
                    'uploaded_at' => $uploadedAt,
                ]);
                $locked->forceFill([
                    'status' => JapicCertificationStatus::Completed,
                    'current_final_version_id' => $version->id,
                    'completed_at' => $uploadedAt,
                    'completed_by' => $actor->id,
                    'lock_version' => $locked->lock_version + 1,
                ])->save();
                $locked->histories()->create([
                    'actor_id' => $actor->id,
                    'from_status' => $fromStatus,
                    'to_status' => JapicCertificationStatus::Completed,
                    'event' => JapicCertificationEvent::FinalUploaded,
                    'document_version_id' => $version->id,
                    'metadata' => array_filter([
                        'completion_path' => $completionPath,
                        'draft_revision' => $draft?->revision,
                    ], fn ($value) => $value !== null),
                    'occurred_at' => $uploadedAt,
                ]);
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => 'japic_certification_final_uploaded',
                    'entity_type' => JapicCertificationProcessing::class,
                    'entity_id' => $locked->id,
                    'previous_values' => ['status' => $fromStatus->value],
                    'new_values' => [
                        'status' => JapicCertificationStatus::Completed->value,
                        'version_number' => $version->version_number,
                        'completion_path' => $completionPath,
                    ],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);
                $this->pswdoIntake->receiveEligible($locked->ib39_surfaced_former_rebel_id);

                return $version;
            }, 5);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function previewFinal(
        JapicCertificationProcessing $processing,
        JapicCertificationDocumentVersion $version,
        User $actor,
        ?string $ipAddress,
        ?string $userAgent,
    ): StreamedResponse {
        return $this->finalResponse($processing, $version, $actor, 'inline', $ipAddress, $userAgent);
    }

    public function downloadFinal(
        JapicCertificationProcessing $processing,
        JapicCertificationDocumentVersion $version,
        User $actor,
        ?string $ipAddress,
        ?string $userAgent,
    ): StreamedResponse {
        return $this->finalResponse($processing, $version, $actor, 'attachment', $ipAddress, $userAgent);
    }

    private function finalResponse(
        JapicCertificationProcessing $processing,
        JapicCertificationDocumentVersion $version,
        User $actor,
        string $disposition,
        ?string $ipAddress,
        ?string $userAgent,
    ): StreamedResponse {
        $ability = $disposition === 'inline' ? 'preview' : 'download';
        abort_unless($actor->can($ability, $version), 403);
        abort_unless($version->processing_id === $processing->id
            && $processing->status === JapicCertificationStatus::Completed
            && $processing->current_final_version_id === $version->id, 404);
        $path = $version->getRawOriginal('storage_path');
        $prefix = "japic/certifications/{$processing->id}/final-documents/";
        abort_unless(is_string($path) && str_starts_with($path, $prefix), 404);
        abort_unless($version->mime_type === 'application/pdf' && Storage::disk('local')->exists($path), 404);

        $response = Storage::disk('local')->response($path, $version->original_filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($version->original_filename, '"\\').'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
        ]);
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'japic_certification_final_'.($disposition === 'inline' ? 'previewed' : 'downloaded'),
            'entity_type' => JapicCertificationDocumentVersion::class,
            'entity_id' => $version->id,
            'new_values' => ['version_number' => $version->version_number],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
        ]);

        return $response;
    }

    private function assertUploadActor(JapicCertificationProcessing $processing, User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasRole('japic')
            && ($processing->assigned_to === null || $processing->assigned_to === $actor->id), 403);
        abort_if($processing->surfacedFormerRebel()->whereHas('cancellation')->exists(), 403, 'Cancelled certifications are read-only.');
    }

    private function photoDataUri(JapicCertificationProcessing $processing, array $payload): ?string
    {
        $selected = data_get($payload, 'certificate.photo_version_id');
        if ($selected) {
            $photo = JapicCertificationPhotoVersion::query()->where('processing_id', $processing->id)->find((int) $selected);
            abort_unless($photo, 409, 'The selected certification photograph is unavailable.');

            return $this->dataUri($photo->getRawOriginal('storage_path'), $photo->mime_type, 'selected certification');
        }

        $sourceId = data_get($payload, 'source_snapshot.subject_photo_version_id');
        if (! $sourceId) {
            return null;
        }
        $cdrId = $processing->triggeringCdrDocumentVersion?->cdr_processing_id;
        $photo = Ib39CdrPhotoVersion::query()->whereKey((int) $sourceId)
            ->whereHas('photo', fn ($query) => $query->where('cdr_processing_id', $cdrId))->first();
        abort_unless($photo, 409, 'The authoritative subject photograph is unavailable.');

        return $this->dataUri($photo->getRawOriginal('storage_path'), $photo->mime_type, 'authoritative subject');
    }

    private function dataUri(mixed $path, mixed $mimeType, string $label): string
    {
        abort_unless(is_string($path) && is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png'], true), 409, "The {$label} photograph is unavailable.");
        abort_unless(Storage::disk('local')->exists($path), 404, "The {$label} photograph is unavailable.");

        return 'data:'.$mimeType.';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
