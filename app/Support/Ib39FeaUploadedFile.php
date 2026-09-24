<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Ib39FeaUploadedFile
{
    private const MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public const MAX_DIMENSION = 12000;

    public const MAX_PIXELS = 40_000_000;

    public static function inspect(UploadedFile $file, bool $photo, string $fallbackName = 'fea-draft'): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $allowed = $photo ? ['image/jpeg', 'image/png'] : ['application/pdf'];
        $path = $file->getRealPath();

        if (! in_array($mime, $allowed, true)
            || ! isset(self::MIME_EXTENSIONS[$mime])
            || ! in_array($extension, self::MIME_EXTENSIONS[$mime], true)
            || ! is_string($path) || ! is_file($path)) {
            self::invalid($photo);
        }

        if ($photo) {
            self::inspectImage($path, $mime);
        } else {
            self::inspectPdf($path);
        }

        $base = pathinfo(basename($file->getClientOriginalName()), PATHINFO_FILENAME);
        $safe = Str::of($base)->ascii()->replaceMatches('/[^A-Za-z0-9._-]+/', '-')->trim('.-_')->limit(180, '')->toString();
        $storedExtension = $mime === 'image/jpeg' ? 'jpg' : $extension;

        return [
            'mime_type' => $mime,
            'extension' => $storedExtension,
            'original_filename' => ($safe !== '' ? $safe : $fallbackName).'.'.$storedExtension,
            'size_bytes' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private static function inspectPdf(string $path): void
    {
        $handle = fopen($path, 'rb');
        $signature = $handle === false ? false : fread($handle, 5);
        if (is_resource($handle)) {
            fclose($handle);
        }
        $size = filesize($path);
        $tail = is_int($size) ? file_get_contents($path, false, null, max(0, $size - 2048)) : false;
        if ($signature !== '%PDF-' || ! is_string($tail) || ! str_contains($tail, '%%EOF')) {
            self::invalid(false);
        }
    }

    private static function inspectImage(string $path, string $mime): void
    {
        $dimensions = @getimagesize($path);
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        if (($dimensions['mime'] ?? null) !== $mime || $width < 1 || $height < 1
            || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION
            || ($width * $height) > self::MAX_PIXELS) {
            self::invalid(true);
        }

        $decoder = $mime === 'image/png' ? 'imagecreatefrompng' : 'imagecreatefromjpeg';
        if (! function_exists($decoder) || ! function_exists('imagedestroy')) {
            throw ValidationException::withMessages([
                'file' => 'Image uploads are unavailable because the server image decoder is not installed.',
            ]);
        }
        $image = @$decoder($path);
        if ($image === false) {
            self::invalid(true);
        }
        imagedestroy($image);
    }

    private static function invalid(bool $photo): never
    {
        throw ValidationException::withMessages([
            'file' => $photo
                ? 'The file must be a decodable JPEG or PNG image whose extension matches its contents.'
                : 'The file must be a PDF whose extension and detected contents match.',
        ]);
    }
}
