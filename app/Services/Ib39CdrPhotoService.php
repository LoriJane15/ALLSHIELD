<?php

namespace App\Services;

use App\Enums\Ib39CdrPhotoType;
use App\Models\AuditLog;
use App\Models\Ib39CdrPhoto;
use App\Models\Ib39CdrPhotoVersion;
use App\Models\Ib39CdrProcessing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Ib39CdrPhotoService
{
    public function __construct(private readonly Ib39CdrStatusService $statuses) {}

    public function store(
        Ib39CdrProcessing $processing,
        Ib39CdrPhotoType $type,
        UploadedFile $file,
        User $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Ib39CdrPhotoVersion {
        $mimeType = $file->getMimeType();
        $extension = $mimeType === 'image/png' ? 'png' : 'jpg';
        $path = sprintf('ib39/cdr/%d/photos/%s/%s.%s', $processing->id, $type->value, Str::uuid(), $extension);

        if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
            throw new RuntimeException('The CDR photograph could not be stored.');
        }

        try {
            return DB::transaction(function () use ($processing, $type, $file, $actor, $path, $mimeType, $ipAddress, $userAgent) {
                $locked = Ib39CdrProcessing::query()->lockForUpdate()->findOrFail($processing->id);
                abort_unless($locked->surfacedFormerRebel()->whereDoesntHave('cancellation')->exists(), 403);
                $this->statuses->recordDraftSaved($locked, $actor, $ipAddress, $userAgent);

                $photo = $locked->photos()->firstOrCreate([
                    'photo_type' => $type,
                ]);
                $photo = Ib39CdrPhoto::query()->lockForUpdate()->findOrFail($photo->id);
                $versionNumber = ((int) $photo->versions()->max('version_number')) + 1;

                $version = $photo->versions()->create([
                    'version_number' => $versionNumber,
                    'storage_path' => $path,
                    'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'mime_type' => $mimeType,
                    'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $actor->id,
                ]);
                $photo->update(['current_photo_version_id' => $version->id]);
                AuditLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => 'ib39_cdr_draft_photo_saved',
                    'entity_type' => Ib39CdrProcessing::class,
                    'entity_id' => $locked->id,
                    'previous_values' => null,
                    'new_values' => ['photo_type' => $type->value, 'version_number' => $versionNumber],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                ]);

                return $version;
            }, 5);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function preview(Ib39CdrPhotoVersion $version): StreamedResponse
    {
        abort_unless(str_starts_with($version->storage_path, 'ib39/cdr/'), 404);
        abort_unless(in_array($version->mime_type, ['image/jpeg', 'image/png'], true), 404);
        abort_unless(Storage::disk('local')->exists($version->storage_path), 404);

        return Storage::disk('local')->response($version->storage_path, null, [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
