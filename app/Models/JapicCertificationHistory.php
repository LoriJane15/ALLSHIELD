<?php

namespace App\Models;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JapicCertificationHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id', 'processing_id'];

    protected $hidden = ['remarks', 'delay_reason'];

    protected function casts(): array
    {
        return ['from_status' => JapicCertificationStatus::class, 'to_status' => JapicCertificationStatus::class, 'event' => JapicCertificationEvent::class, 'remarks' => 'encrypted', 'delay_reason' => 'encrypted', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('JAPIC histories are immutable.'));
        static::deleting(fn () => throw new LogicException('JAPIC histories are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationProcessing::class, 'processing_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationDocumentVersion::class, 'document_version_id');
    }
}
