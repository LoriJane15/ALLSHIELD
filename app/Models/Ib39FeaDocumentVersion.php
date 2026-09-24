<?php

namespace App\Models;

use App\Enums\Ib39FeaUploadSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39FeaDocumentVersion extends Model
{
    protected $fillable = [
        'fea_processing_id', 'slot', 'version_number', 'replaces_version_id',
        'replacement_reason', 'storage_path', 'original_filename', 'mime_type',
        'size_bytes', 'sha256', 'uploaded_by',
    ];

    protected $hidden = ['storage_path', 'sha256'];

    protected function casts(): array
    {
        return [
            'slot' => Ib39FeaUploadSlot::class,
            'version_number' => 'integer',
            'replacement_reason' => 'encrypted',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('FEA file versions are immutable.'));
        static::deleting(fn () => throw new LogicException('FEA file versions are immutable.'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaDocument::class, 'fea_document_id');
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39FeaProcessing::class, 'fea_processing_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function replacesVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_version_id');
    }
}
