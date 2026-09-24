<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Ib39FrCancellation extends Model
{
    protected $guarded = [
        'id',
        'ib39_surfaced_former_rebel_id',
        'previous_overall_status',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => 'encrypted',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('FR cancellation records are immutable.'));
        static::deleting(fn () => throw new LogicException('FR cancellation records are immutable.'));
    }

    public function surfacedFormerRebel(): BelongsTo
    {
        return $this->belongsTo(Ib39SurfacedFormerRebel::class, 'ib39_surfaced_former_rebel_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
