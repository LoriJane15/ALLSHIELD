<?php

namespace App\Services;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\AuditLog;
use App\Models\PswdoEnrollment;
use App\Models\PswdoEnrollmentDocument;
use App\Models\User;
use App\Support\PrivatePdfUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class PswdoEnrollmentDocumentService
{
    public function __construct(private readonly PswdoEligibilityService $eligibility) {}

    public function uploadFinal(
        PswdoEnrollment $enrollment,
        PswdoEnrollmentDocumentType $type,
        UploadedFile $file,
        int $expectedLockVersion,
        bool $correctDocumentTypeConfirmed,
        bool $belongsToFrConfirmed,
        bool $finalSignedConfirmed,
        User $actor,
        ?string $ipAddress,
        ?string $userAgent,
    ): PswdoEnrollmentDocument {
        $this->assertActor($enrollment, $actor);
        if (! $correctDocumentTypeConfirmed || ! $belongsToFrConfirmed || ! $finalSignedConfirmed) {
            throw ValidationException::withMessages([
                'document' => 'All three final-document confirmations are required.',
            ]);
        }
        $metadata = PrivatePdfUpload::inspect(
            $file,
            'final-'.str_replace('_', '-', $type->value),
            'document',
            'The final document must be a non-empty, valid PDF within the allowed private-file size.',
        );
        $path = sprintf('pswdo/enrollments/%d/%s/%s.pdf', $enrollment->id, $type->value, Str::uuid());

        if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
            throw new RuntimeException('The final PSWDO document could not be stored.');
        }

        try {
            return DB::transaction(function () use ($enrollment, $type, $expectedLockVersion, $actor, $ipAddress, $userAgent, $metadata, $path): PswdoEnrollmentDocument {
                $locked = PswdoEnrollment::query()->with([
                    'documents',
                    'surfacedFormerRebel.cancellation',
                    'surfacedFormerRebel.cdrProcessing.currentFinalVersion',
                    'surfacedFormerRebel.japicCertificationProcessing.currentFinalVersion',
                ])->lockForUpdate()->findOrFail($enrollment->id);
                $this->assertActor($locked, $actor);
                if ($locked->lock_version !== $expectedLockVersion) {
                    throw new ConflictHttpException('A newer PSWDO enrollment change exists. Reload before uploading.');
                }
                if ($locked->documents->contains('document_type', $type)) {
                    throw ValidationException::withMessages(['document' => 'A final document of this type has already been uploaded.']);
                }
                if ($locked->documents->contains('sha256', $metadata['sha256'])) {
                    throw ValidationException::withMessages(['document' => 'This PDF has already been used for another PSWDO document for this FR.']);
                }
                if ($type->isEndorsementLetter()
                    && ! collect(PswdoEnrollmentDocumentType::prerequisites())->every(fn ($required): bool => $locked->hasDocument($required))) {
                    throw ValidationException::withMessages(['document' => 'Complete the first three enrollment documents before uploading the Endorsement Letter.']);
                }

                $uploadedAt = now();
                $document = $locked->documents()->create([
                    'document_type' => $type,
                    'storage_path' => $path,
                    'original_filename' => $metadata['original_filename'],
                    'mime_type' => $metadata['mime_type'],
                    'size_bytes' => $metadata['size_bytes'],
                    'sha256' => $metadata['sha256'],
                    'uploaded_by' => $actor->id,
                    'correct_document_type_confirmed' => true,
                    'belongs_to_fr_confirmed' => true,
                    'final_signed_confirmed' => true,
                    'uploaded_at' => $uploadedAt,
                ]);
                $locked->forceFill(['lock_version' => $locked->lock_version + 1])->save();
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => 'pswdo_final_document_uploaded',
                    'entity_type' => PswdoEnrollmentDocument::class,
                    'entity_id' => $document->id,
                    'new_values' => ['document_type' => $type->value, 'enrollment_id' => $locked->id],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);

                return $document;
            }, 5);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function preview(PswdoEnrollment $enrollment, PswdoEnrollmentDocument $document, User $actor, ?string $ip, ?string $agent): StreamedResponse
    {
        return $this->response($enrollment, $document, $actor, 'inline', $ip, $agent);
    }

    public function download(PswdoEnrollment $enrollment, PswdoEnrollmentDocument $document, User $actor, ?string $ip, ?string $agent): StreamedResponse
    {
        return $this->response($enrollment, $document, $actor, 'attachment', $ip, $agent);
    }

    private function response(PswdoEnrollment $enrollment, PswdoEnrollmentDocument $document, User $actor, string $disposition, ?string $ip, ?string $agent): StreamedResponse
    {
        abort_unless($document->pswdo_enrollment_id === $enrollment->id, 404);
        $document->setRelation('enrollment', $enrollment);
        abort_unless($actor->can($disposition === 'inline' ? 'preview' : 'download', $document), 403);
        $path = $document->getRawOriginal('storage_path');
        $prefix = "pswdo/enrollments/{$enrollment->id}/{$document->document_type->value}/";
        abort_unless(is_string($path) && str_starts_with($path, $prefix), 404);
        abort_unless($document->mime_type === 'application/pdf' && Storage::disk('local')->exists($path), 404);

        $response = Storage::disk('local')->response($path, $document->original_filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($document->original_filename, '"\\').'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
        ]);
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'pswdo_final_document_'.($disposition === 'inline' ? 'previewed' : 'downloaded'),
            'entity_type' => PswdoEnrollmentDocument::class,
            'entity_id' => $document->id,
            'new_values' => ['document_type' => $document->document_type->value, 'enrollment_id' => $enrollment->id],
            'ip_address' => $ip,
            'user_agent' => $agent ? mb_substr($agent, 0, 1000) : null,
        ]);

        return $response;
    }

    private function assertActor(PswdoEnrollment $enrollment, User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasRole('pswdo') && $actor->can('view', $enrollment), 403);
        $enrollment->loadMissing('surfacedFormerRebel');
        abort_unless($enrollment->surfacedFormerRebel && $this->eligibility->isEligible($enrollment->surfacedFormerRebel), 403);
    }
}
