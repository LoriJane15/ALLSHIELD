<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RcspBarangay extends Model
{
    protected $fillable = ['barangay_id', 'municipality_id', 'status', 'current_phase'];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function phaseStatus(): HasOne
    {
        return $this->hasOne(RcspPhaseStatus::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(RcspForm::class);
    }

    /** Progress percentage across the 6 phases (0..5). */
    public function getProgressAttribute(): int
    {
        return (int) round(($this->current_phase / 5) * 100);
    }
}
