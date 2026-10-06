<?php

namespace App\Models;

use App\Enums\Ib39CdrDocumentSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39CdrFinalDocument extends Model
{
    protected $hidden = ['storage_path', 'sha256'];

    protected $fillable = [
        'cdr_processing_id',
        'source_type',
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

    protected function casts(): array
    {
        return [
            'source_type' => Ib39CdrDocumentSource::class,
            'size_bytes' => 'integer',
            'content_schema_version' => 'integer',
            'content_snapshot' => 'encrypted:array',
            'finalized_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Final CDR documents are immutable.'));
        static::deleting(fn () => throw new LogicException('Final CDR documents are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrProcessing::class, 'cdr_processing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
