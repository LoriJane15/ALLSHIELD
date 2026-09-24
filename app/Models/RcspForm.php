<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RcspForm extends Model
{
    protected $fillable = [
        'lgu_user_id', 'rcsp_barangay_id', 'rcsp_phase_id', 'rcsp_activity_id',
        'conduct', 'file', 'status', 'remarks',
    ];

    public function lguUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lgu_user_id');
    }

    public function rcspBarangay(): BelongsTo
    {
        return $this->belongsTo(RcspBarangay::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(RcspPhase::class, 'rcsp_phase_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(RcspActivity::class, 'rcsp_activity_id');
    }

    public function fileComments(): HasMany
    {
        return $this->hasMany(RcspFileComment::class);
    }
}
