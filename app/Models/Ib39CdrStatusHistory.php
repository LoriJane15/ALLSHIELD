<?php

namespace App\Models;

use App\Enums\Ib39CdrStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39CdrStatusHistory extends Model
{
    protected $fillable = [
        'user_id',
        'from_status',
        'to_status',
        'event',
        'remarks',
        'delay_reason',
        'document_version_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => Ib39CdrStatus::class,
            'to_status' => Ib39CdrStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('CDR status history entries are immutable.'));
        static::deleting(fn () => throw new LogicException('CDR status history entries are immutable.'));
    }

    public function processing(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrProcessing::class, 'cdr_processing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrDocumentVersion::class, 'document_version_id');
    }
}
