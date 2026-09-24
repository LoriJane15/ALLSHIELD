<?php

namespace App\Services;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Models\AuditLog;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39CdrProcessing;
use App\Models\User;
use App\Support\Ib39CdrUploadedDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Ib39CdrDocumentService
{
    public function __construct(private readonly JapicCertificationIntakeService $japicIntake) {}

    public function uploadFinal(
        Ib39CdrProcessing $processing,
        UploadedFile $file,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrDocumentVersion {
        return $this->store($processing, $file, null, $actor, $ipAddress, $userAgent);
    }

    public function replaceFinal(
        Ib39CdrProcessing $processing,
        UploadedFile $file,
        string $reason,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrDocumentVersion {
        return $this->store($processing, $file, trim($reason), $actor, $ipAddress, $userAgent);
    }

    public function preview(Ib39CdrDocumentVersion $version, User $actor, ?string $ipAddress, ?string $userAgent): StreamedResponse
    {
        $response = $this->fileResponse($version, 'inline');
        $this->recordAccess($version, $actor, 'preview', $ipAddress, $userAgent);

        return $response;
    }

    public function download(Ib39CdrDocumentVersion $version, User $actor, ?string $ipAddress, ?string $userAgent): StreamedResponse
    {
        $response = $this->fileResponse($version, 'attachment');
        $this->recordAccess($version, $actor, 'download', $ipAddress, $userAgent);

        return $response;
    }

    public function recordGeneratedPreview(Ib39CdrDocumentVersion $version, User $actor, ?string $ipAddress, ?string $userAgent): void
    {
        abort_unless($version->source_type === Ib39CdrDocumentSource::Generated, 404);
        $this->recordAccess($version, $actor, 'preview', $ipAddress, $userAgent);
    }

    private function store(
        Ib39CdrProcessing $processing,
        UploadedFile $file,
        ?string $replacementReason,
        User $actor,
        ?string $ipAddress,
        ?string $userAgent,
    ): Ib39CdrDocumentVersion {
        abort_unless($actor->is_active && $actor->hasRole('39th_ib'), 403);
        $metadata = Ib39CdrUploadedDocument::inspect($file);
        $path = sprintf('ib39/cdr/%d/final-documents/%s.%s', $processing->id, Str::uuid(), $metadata['extension']);

        if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
            throw new RuntimeException('The final CDR could not be stored.');
        }

        try {
            $result = DB::transaction(function () use ($processing, $replacementReason, $actor, $path, $metadata, $ipAddress, $userAgent) {
                $locked = Ib39CdrProcessing::query()->lockForUpdate()->findOrFail($processing->id);
                abort_unless($locked->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists(), 403);
                $isReplacement = $replacementReason !== null;

                if ($isReplacement) {
                    if ($locked->status !== Ib39CdrStatus::Completed || $locked->current_final_version_id === null) {
                        throw ValidationException::withMessages(['document' => 'This CDR is no longer available for replacement.']);
                    }
                    $current = Ib39CdrDocumentVersion::query()->where('cdr_processing_id', $locked->id)->lockForUpdate()->findOrFail($locked->current_final_version_id);
                    if ($current->source_type === Ib39CdrDocumentSource::Uploaded
                        && hash_equals($current->sha256, $metadata['sha256'])
                        && $current->replacement_reason === $replacementReason) {
                        return ['version' => $current, 'duplicate' => true];
                    }
                } else {
                    if (! in_array($locked->status, [Ib39CdrStatus::Pending, Ib39CdrStatus::Ongoing], true) || $locked->current_final_version_id !== null) {
                        throw ValidationException::withMessages(['document' => 'This CDR is no longer available for direct final upload.']);
                    }
                    $current = null;
                }

                $fromStatus = $locked->status;
                $versionNumber = ((int) $locked->documentVersions()->max('version_number')) + 1;
                $finalizedAt = now();
                $version = $locked->documentVersions()->create([
                    'version_number' => $versionNumber,
                    'source_type' => Ib39CdrDocumentSource::Uploaded,
                    'replaces_version_id' => $current?->id,
                    'replacement_reason' => $replacementReason,
                    'storage_path' => $path,
                    'original_filename' => $metadata['original_filename'],
                    'mime_type' => $metadata['mime_type'],
                    'size_bytes' => $metadata['size_bytes'],
                    'sha256' => $metadata['sha256'],
                    'content_schema_version' => null,
                    'content_snapshot' => null,
                    'created_by' => $actor->id,
                    'finalized_at' => $finalizedAt,
                ]);

                $changes = ['current_final_version_id' => $version->id];
                if (! $isReplacement) {
                    $changes += [
                        'status' => Ib39CdrStatus::Completed,
                        'completed_at' => $finalizedAt,
                        'completed_by' => $actor->id,
                    ];
                }
                $locked->update($changes);
                $locked->statusHistories()->create([
                    'user_id' => $actor->id,
                    'from_status' => $fromStatus,
                    'to_status' => Ib39CdrStatus::Completed,
                    'event' => $isReplacement ? 'final_document_replaced' : 'direct_final_uploaded',
                    'document_version_id' => $version->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => $isReplacement ? 'ib39_cdr_final_document_replaced' : 'ib39_cdr_direct_final_uploaded',
                    'entity_type' => Ib39CdrProcessing::class,
                    'entity_id' => $locked->id,
                    'previous_values' => ['status' => $fromStatus->value, 'current_version_number' => $current?->version_number],
                    'new_values' => ['status' => Ib39CdrStatus::Completed->value, 'version_number' => $versionNumber, 'source_type' => Ib39CdrDocumentSource::Uploaded->value],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);

                if (! $isReplacement) {
                    $this->japicIntake->createForCompletedCdr($locked->fresh());
                }

                return ['version' => $version, 'duplicate' => false];
            }, 5);

            if ($result['duplicate']) {
                Storage::disk('local')->delete($path);
            }

            return $result['version'];
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    private function fileResponse(Ib39CdrDocumentVersion $version, string $disposition): StreamedResponse
    {
        abort_unless($version->source_type === Ib39CdrDocumentSource::Uploaded, 404);
        abort_unless(str_starts_with($version->storage_path, 'ib39/cdr/'), 404);
        abort_unless(in_array($version->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);
        abort_unless(Storage::disk('local')->exists($version->storage_path), 404);

        return Storage::disk('local')->response($version->storage_path, $version->original_filename, [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($version->original_filename, '"\\').'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
        ]);
    }

    private function recordAccess(Ib39CdrDocumentVersion $version, User $actor, string $access, ?string $ipAddress, ?string $userAgent): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'ib39_cdr_final_document_'.$access,
            'entity_type' => Ib39CdrDocumentVersion::class,
            'entity_id' => $version->id,
            'previous_values' => null,
            'new_values' => ['version_number' => $version->version_number, 'source_type' => $version->source_type->value],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
        ]);
    }
}
