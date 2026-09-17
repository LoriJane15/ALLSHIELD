<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RcspPhaseTransition extends Model
{
    protected $fillable = [
        'rcsp_barangay_id', 'from_phase', 'to_phase', 'advanced_by_user_id', 'advanced_at',
    ];

    protected function casts(): array
    {
        return ['advanced_at' => 'datetime'];
    }

    public function rcspBarangay(): BelongsTo
    {
        return $this->belongsTo(RcspBarangay::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advanced_by_user_id');
    }
}
