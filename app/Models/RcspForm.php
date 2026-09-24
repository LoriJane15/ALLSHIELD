<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RcspForm extends Model
{
    protected $fillable = [
        'lgu_user_id', 'rcsp_barangay_id', 'rcsp_phase_id', 'rcsp_activity_id',
        'submission_version', 'conduct', 'file', 'original_filename', 'detected_mime_type',
        'file_size_bytes', 'submitted_at', 'status', 'remarks', 'reviewed_by_user_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_version' => 'integer',
            'file_size_bytes' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function lguUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lgu_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
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

    public function reviews(): HasMany
    {
        return $this->hasMany(RcspFormReview::class);
    }
}
