<?php

namespace App\Models;

use App\Enums\Ib39CdrStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ib39CdrProcessing extends Model
{
    protected $fillable = [
        'status',
        'started_at',
        'due_at',
        'completed_at',
        'completed_by',
        'current_final_version_id',
        'remarks',
        'delay_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => Ib39CdrStatus::class,
            'started_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function surfacedFormerRebel(): BelongsTo
    {
        return $this->belongsTo(Ib39SurfacedFormerRebel::class, 'ib39_surfaced_former_rebel_id');
    }

    public function form(): HasOne
    {
        return $this->hasOne(Ib39CdrForm::class, 'cdr_processing_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(Ib39CdrStatusHistory::class, 'cdr_processing_id');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(Ib39CdrDocumentVersion::class, 'cdr_processing_id');
    }

    public function currentFinalVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrDocumentVersion::class, 'current_final_version_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Ib39CdrPhoto::class, 'cdr_processing_id');
    }
}
