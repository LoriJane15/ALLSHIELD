<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RcspActivity extends Model
{
    protected $fillable = ['rcsp_phase_id', 'description'];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(RcspPhase::class, 'rcsp_phase_id');
    }
}
