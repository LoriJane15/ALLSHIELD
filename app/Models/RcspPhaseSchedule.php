<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RcspPhaseSchedule extends Model
{
    protected $fillable = [
        'rcsp_implementation_timeline_id', 'rcsp_phase_id',
        'planned_start_date', 'planned_end_date', 'estimated_duration_weeks',
        'strategic_objective', 'implementation_note',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'estimated_duration_weeks' => 'integer',
        ];
    }

    public function timeline(): BelongsTo
    {
        return $this->belongsTo(RcspImplementationTimeline::class, 'rcsp_implementation_timeline_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(RcspPhase::class, 'rcsp_phase_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(RcspPhaseScheduleAdjustment::class);
    }
}
