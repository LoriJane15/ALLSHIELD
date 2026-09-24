<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class JapicCertificationDocumentVersion extends Model
{
    protected $guarded = ['id', 'processing_id'];

    protected $hidden = ['storage_path', 'replacement_reason'];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'size_bytes' => 'integer', 'replacement_reason' => 'encrypted', 'all_signatories_confirmed' => 'boolean', 'correct_final_confirmed' => 'boolean', 'uploaded_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('JAPIC document versions are immutable.'));
        static::deleting(fn () => throw new LogicException('JAPIC document versions are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationProcessing::class, 'processing_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
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
