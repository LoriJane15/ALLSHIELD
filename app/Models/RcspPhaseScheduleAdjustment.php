<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RcspPhaseScheduleAdjustment extends Model
{
    protected $fillable = [
        'rcsp_phase_schedule_id',
        'previous_planned_start_date', 'previous_planned_end_date',
        'updated_planned_start_date', 'updated_planned_end_date',
        'previous_strategic_objective', 'updated_strategic_objective',
        'reason', 'changed_by_user_id', 'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_planned_start_date' => 'date',
            'previous_planned_end_date' => 'date',
            'updated_planned_start_date' => 'date',
            'updated_planned_end_date' => 'date',
            'adjusted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('RCSP schedule adjustment history is immutable.'));
        static::deleting(fn () => throw new LogicException('RCSP schedule adjustment history is immutable.'));
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RcspPhaseSchedule::class, 'rcsp_phase_schedule_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
