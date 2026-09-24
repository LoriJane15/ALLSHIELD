<?php

namespace App\Models;

use App\Enums\Ib39CdrDocumentSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Ib39CdrDocumentVersion extends Model
{
    protected $fillable = [
        'version_number',
        'source_type',
        'replaces_version_id',
        'replacement_reason',
        'storage_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'sha256',
        'content_schema_version',
        'content_snapshot',
        'created_by',
        'finalized_at',
    ];

    protected $hidden = [
        'storage_path',
        'content_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'source_type' => Ib39CdrDocumentSource::class,
            'size_bytes' => 'integer',
            'content_schema_version' => 'integer',
            'content_snapshot' => 'encrypted:array',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('CDR document versions are immutable.'));
        static::deleting(fn () => throw new LogicException('CDR document versions are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrProcessing::class, 'cdr_processing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replacesVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_version_id');
    }

    public function replacementVersions(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_version_id');
    }
}
