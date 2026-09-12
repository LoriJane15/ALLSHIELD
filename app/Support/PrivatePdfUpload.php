<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PrivatePdfUpload
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public static function inspect(
        UploadedFile $file,
        string $fallbackFilename = 'final-document',
        string $errorField = 'document',
        string $errorMessage = 'The final PDF is invalid or unsafe.',
    ): array {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        if (! is_string($path) || ! is_file($path) || ! is_int($size) || $size < 1
            || $size > self::MAX_BYTES || $extension !== 'pdf' || $mimeType !== 'application/pdf') {
            self::invalid($errorField, $errorMessage);
        }

        $handle = fopen($path, 'rb');
        $signature = $handle === false ? false : fread($handle, 5);
        if (is_resource($handle)) {
            fclose($handle);
        }
        $tail = file_get_contents($path, false, null, max(0, $size - 2048));
        if ($signature !== '%PDF-' || ! is_string($tail) || ! str_contains($tail, '%%EOF')) {
            self::invalid($errorField, $errorMessage);
        }

        $original = pathinfo(basename($file->getClientOriginalName()), PATHINFO_FILENAME);
        $safeBase = Str::of($original)->ascii()->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('.-_')->limit(180, '')->toString();

        return [
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'original_filename' => ($safeBase !== '' ? $safeBase : $fallbackFilename).'.pdf',
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private static function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
