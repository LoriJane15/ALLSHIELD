<?php

namespace App\Services;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Models\AuditLog;
use App\Models\Ib39CdrFinalDocument;
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
    ): Ib39CdrFinalDocument {
        return $this->store($processing, $file, $actor, $ipAddress, $userAgent);
    }

    public function preview(Ib39CdrFinalDocument $document, User $actor, ?string $ipAddress, ?string $userAgent): StreamedResponse
    {
        $response = $this->fileResponse($document, 'inline');
        $this->recordAccess($document, $actor, 'preview', $ipAddress, $userAgent);

        return $response;
    }

    public function download(Ib39CdrFinalDocument $document, User $actor, ?string $ipAddress, ?string $userAgent): StreamedResponse
    {
        $response = $this->fileResponse($document, 'attachment');
        $this->recordAccess($document, $actor, 'download', $ipAddress, $userAgent);

        return $response;
    }

    public function recordGeneratedAccess(Ib39CdrFinalDocument $document, User $actor, string $action, ?string $ipAddress, ?string $userAgent): void
    {
        abort_unless($document->source_type === Ib39CdrDocumentSource::Generated, 404);
        $this->recordAccess($document, $actor, $action, $ipAddress, $userAgent);
    }

    public function ensureUploadedFileIsAvailable(Ib39CdrFinalDocument $document): void
    {
        abort_unless($document->source_type === Ib39CdrDocumentSource::Uploaded, 404);
        abort_unless(str_starts_with($document->storage_path, 'ib39/cdr/'), 404);
        abort_unless(in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
    }

    private function store(
        Ib39CdrProcessing $processing,
        UploadedFile $file,
        User $actor,
        ?string $ipAddress,
        ?string $userAgent,
    ): Ib39CdrFinalDocument {
        abort_unless($actor->is_active && $actor->hasRole('39th_ib'), 403);
        $metadata = Ib39CdrUploadedDocument::inspect($file);
        $path = sprintf('ib39/cdr/%d/final-documents/%s.%s', $processing->id, Str::uuid(), $metadata['extension']);

        if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
            throw new RuntimeException('The final CDR could not be stored.');
        }

        try {
            $document = DB::transaction(function () use ($processing, $actor, $path, $metadata, $ipAddress, $userAgent) {
                $locked = Ib39CdrProcessing::query()->lockForUpdate()->findOrFail($processing->id);
                abort_unless($locked->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists(), 403);
                if (! in_array($locked->status, [Ib39CdrStatus::Pending, Ib39CdrStatus::Ongoing], true)
                    || $locked->finalDocument()->exists()) {
                    throw ValidationException::withMessages(['document' => 'This CDR already has a final document or is no longer available for upload.']);
                }

                $fromStatus = $locked->status;
                $finalizedAt = now();
                $document = $locked->finalDocument()->create([
                    'source_type' => Ib39CdrDocumentSource::Uploaded,
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

                $locked->update([
                    'status' => Ib39CdrStatus::Completed,
                    'completed_at' => $finalizedAt,
                    'completed_by' => $actor->id,
                ]);
                $locked->statusHistories()->create([
                    'user_id' => $actor->id,
                    'from_status' => $fromStatus,
                    'to_status' => Ib39CdrStatus::Completed,
                    'event' => 'final_document_uploaded',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => 'ib39_cdr_final_document_uploaded',
                    'entity_type' => Ib39CdrProcessing::class,
                    'entity_id' => $locked->id,
                    'previous_values' => ['status' => $fromStatus->value],
                    'new_values' => ['status' => Ib39CdrStatus::Completed->value, 'source_type' => Ib39CdrDocumentSource::Uploaded->value],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);

                $this->japicIntake->createForCompletedCdr($locked->fresh());

                return $document;
            }, 5);

            return $document;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    private function fileResponse(Ib39CdrFinalDocument $document, string $disposition): StreamedResponse
    {
        $this->ensureUploadedFileIsAvailable($document);

        return Storage::disk('local')->response($document->storage_path, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($document->original_filename, '"\\').'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
        ]);
    }

    private function recordAccess(Ib39CdrFinalDocument $document, User $actor, string $access, ?string $ipAddress, ?string $userAgent): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'ib39_cdr_final_document_'.$access,
            'entity_type' => Ib39CdrFinalDocument::class,
            'entity_id' => $document->id,
            'previous_values' => null,
            'new_values' => ['source_type' => $document->source_type->value],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
        ]);
    }
}
