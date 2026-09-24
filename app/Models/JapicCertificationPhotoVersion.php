<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JapicCertificationPhotoVersion extends Model
{
    public $timestamps = false;

    protected $guarded = ['id', 'processing_id'];

    protected $hidden = ['storage_path'];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'uploaded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('JAPIC certification photo versions are immutable.'));
        static::deleting(fn () => throw new LogicException('JAPIC certification photo versions are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationProcessing::class, 'processing_id');
    }

    public function replacesVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_version_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
