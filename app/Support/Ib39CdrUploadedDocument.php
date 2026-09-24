<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Ib39CdrUploadedDocument
{
    private const MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public static function inspect(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        if (! isset(self::MIME_EXTENSIONS[$mimeType]) || ! in_array($extension, self::MIME_EXTENSIONS[$mimeType], true)) {
            self::invalid();
        }

        $path = $file->getRealPath();
        if (! is_string($path) || ! is_file($path)) {
            self::invalid();
        }

        if ($mimeType === 'application/pdf') {
            $handle = fopen($path, 'rb');
            $signature = $handle === false ? false : fread($handle, 5);
            if (is_resource($handle)) {
                fclose($handle);
            }
            $size = filesize($path);
            $tail = file_get_contents($path, false, null, max(0, $size - 2048));
            if ($signature !== '%PDF-' || ! is_string($tail) || ! str_contains($tail, '%%EOF')) {
                self::invalid();
            }
        } else {
            $dimensions = @getimagesize($path);
            $width = (int) ($dimensions[0] ?? 0);
            $height = (int) ($dimensions[1] ?? 0);
            if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || ($width * $height) > 40_000_000) {
                self::invalid();
            }
        }

        $original = pathinfo(basename($file->getClientOriginalName()), PATHINFO_FILENAME);
        $safeBase = Str::of($original)->ascii()->replaceMatches('/[^A-Za-z0-9._-]+/', '-')->trim('.-_')->limit(180, '')->toString();

        return [
            'mime_type' => $mimeType,
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'original_filename' => ($safeBase !== '' ? $safeBase : 'cdr-document').'.'.$extension,
            'size_bytes' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages([
            'document' => 'The document must be a valid PDF, JPEG, or PNG file whose extension matches its contents.',
        ]);
    }
}
