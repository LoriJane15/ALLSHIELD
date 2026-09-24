<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39CdrPhotoVersion extends Model
{
    protected $fillable = [
        'version_number',
        'storage_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'sha256',
        'uploaded_by',
    ];

    protected $hidden = [
        'storage_path',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('CDR photo versions are immutable.'));
        static::deleting(fn () => throw new LogicException('CDR photo versions are immutable.'));
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrPhoto::class, 'cdr_photo_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
