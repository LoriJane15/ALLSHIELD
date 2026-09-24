<?php

namespace App\Services;

use App\Enums\JapicCertificationStatus;
use App\Models\JapicCertificationDraft;
use App\Models\JapicCertificationPhotoVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
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

class JapicCertificationPhotoService
{
    public function __construct(private readonly JapicCertificationDraftService $drafts) {}

    public function store(
        JapicCertificationProcessing $processing,
        UploadedFile $file,
        int $expectedRevision,
        int $expectedLockVersion,
        User $actor,
    ): JapicCertificationPhotoVersion {
        $this->assertActor($processing, $actor);
        $metadata = JapicCertificationUploadedFile::inspect($file);
        $path = sprintf('japic/certifications/%d/photos/%s.%s', $processing->id, Str::uuid(), $metadata['extension']);
        $stored = false;

        try {
            return DB::transaction(function () use ($processing, $file, $expectedRevision, $expectedLockVersion, $actor, $metadata, $path, &$stored) {
                $locked = JapicCertificationProcessing::query()->lockForUpdate()->findOrFail($processing->id);
                $this->assertActor($locked, $actor);
                $this->assertEditable($locked);
                if ($locked->lock_version !== $expectedLockVersion) {
                    throw new ConflictHttpException('A newer certification change exists. Reload before uploading.');
                }
                $draft = JapicCertificationDraft::query()->where('processing_id', $locked->id)->lockForUpdate()->first();
                if (($draft?->revision ?? 0) !== $expectedRevision) {
                    throw new ConflictHttpException('A newer draft revision exists. Reload before uploading.');
                }
                $replaced = $locked->current_photo_version_id
                    ? JapicCertificationPhotoVersion::query()->lockForUpdate()->where('processing_id', $locked->id)->findOrFail($locked->current_photo_version_id)
                    : null;

                if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
                    throw new RuntimeException('The certification photograph could not be stored.');
                }
                $stored = true;
                $version = $locked->photoVersions()->create([
                    'version_number' => ((int) $locked->photoVersions()->max('version_number')) + 1,
                    'replaces_version_id' => $replaced?->id,
                    'storage_path' => $path,
                    'original_filename' => $metadata['original_filename'],
                    'mime_type' => $metadata['mime_type'],
                    'size_bytes' => $metadata['size_bytes'],
                    'width' => $metadata['width'],
                    'height' => $metadata['height'],
                    'sha256' => $metadata['sha256'],
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now(),
                ]);
                $this->drafts->recordPhotoSelection($locked, $version, $expectedRevision, $expectedLockVersion, $actor);

                return $version->fresh();
            }, 5);
        } catch (Throwable $exception) {
            if ($stored) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    public function response(JapicCertificationPhotoVersion $version): StreamedResponse
    {
        $path = $version->getRawOriginal('storage_path');
        abort_unless(is_string($path) && str_starts_with($path, 'japic/certifications/'), 404);
        abort_unless(in_array($version->mime_type, ['image/jpeg', 'image/png'], true), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function assertActor(JapicCertificationProcessing $processing, User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasRole('japic') && ($processing->assigned_to === null || $processing->assigned_to === $actor->id), 403);
        abort_if($processing->surfacedFormerRebel()->whereHas('cancellation')->exists(), 403, 'Cancelled certifications are read-only.');
    }

    private function assertEditable(JapicCertificationProcessing $processing): void
    {
        if (! in_array($processing->status, [JapicCertificationStatus::Pending, JapicCertificationStatus::Drafting], true)) {
            throw ValidationException::withMessages(['photo' => 'This certification photograph is not available for editing.']);
        }
    }
}
