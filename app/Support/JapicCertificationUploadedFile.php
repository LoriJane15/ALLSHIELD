<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JapicCertificationUploadedFile
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const FINAL_PDF_MAX_BYTES = PrivatePdfUpload::MAX_BYTES;

    public const MIN_DIMENSION = 100;

    public const MAX_DIMENSION = 8000;

    public const MAX_PIXELS = 16_000_000;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public static function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        if (! is_string($path) || ! is_file($path) || ! is_int($size) || $size < 1 || $size > self::MAX_BYTES
            || ! isset(self::MIME_EXTENSIONS[$mimeType]) || ! in_array($extension, self::MIME_EXTENSIONS[$mimeType], true)) {
            self::invalid();
        }

        $handle = fopen($path, 'rb');
        $signature = $handle === false ? false : fread($handle, 8);
        if (is_resource($handle)) {
            fclose($handle);
        }
        $validSignature = $mimeType === 'image/png'
            ? $signature === "\x89PNG\r\n\x1a\n"
            : is_string($signature) && str_starts_with($signature, "\xFF\xD8\xFF");
        if (! $validSignature) {
            self::invalid();
        }

        $dimensions = @getimagesize($path);
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        $detectedMime = $dimensions['mime'] ?? null;
        if ($detectedMime !== $mimeType || $width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION
            || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || ($width * $height) > self::MAX_PIXELS) {
            self::invalid();
        }

        $contents = file_get_contents($path);
        $decoded = is_string($contents) ? @imagecreatefromstring($contents) : false;
        if ($decoded === false) {
            self::invalid();
        }
        imagedestroy($decoded);

        $original = pathinfo(basename($file->getClientOriginalName()), PATHINFO_FILENAME);
        $safeBase = Str::of($original)->ascii()->replaceMatches('/[^A-Za-z0-9._-]+/', '-')->trim('.-_')->limit(180, '')->toString();
        $storedExtension = $mimeType === 'image/png' ? 'png' : 'jpg';

        return [
            'mime_type' => $mimeType,
            'extension' => $storedExtension,
            'original_filename' => ($safeBase !== '' ? $safeBase : 'certification-photo').'.'.$storedExtension,
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'sha256' => hash_file('sha256', $path),
        ];
    }

    public static function inspectFinalPdf(UploadedFile $file): array
    {
        return PrivatePdfUpload::inspect(
            $file,
            'final-certification',
            'document',
            'The final certification must be a valid non-empty PDF whose extension, detected MIME type, signature, EOF marker, and size are allowed.',
        );
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages([
            'photo' => 'The photograph must be a valid JPEG or PNG whose extension, signature, decoded contents, dimensions, and size are allowed.',
        ]);
    }
}
