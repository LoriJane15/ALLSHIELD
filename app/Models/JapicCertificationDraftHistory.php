<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JapicCertificationDraftHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id', 'processing_id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'revision' => 'integer', 'saved_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('JAPIC draft histories are immutable.'));
        static::deleting(fn () => throw new LogicException('JAPIC draft histories are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationProcessing::class, 'processing_id');
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
