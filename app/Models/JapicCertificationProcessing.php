<?php

namespace App\Models;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JapicCertificationProcessing extends Model
{
    protected $guarded = ['id', 'ib39_surfaced_former_rebel_id'];

    protected $hidden = ['control_number'];

    protected function casts(): array
    {
        return [
            'status' => JapicCertificationStatus::class,
            'received_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'control_number' => 'encrypted',
            'lock_version' => 'integer',
        ];
    }

    protected function delayed(): Attribute
    {
        return Attribute::get(function (): bool {
            if (! $this->due_at
                || in_array($this->status, [JapicCertificationStatus::Completed, JapicCertificationStatus::Cancelled], true)) {
                return false;
            }

            return $this->surfacedFormerRebel?->cancellation === null
                && now()->greaterThan($this->due_at);
        });
    }

    protected function timelineProgress(): Attribute
    {
        return Attribute::get(function (): int {
            $status = $this->status;
            if ($status === JapicCertificationStatus::Cancelled) {
                $cancellation = $this->relationLoaded('histories')
                    ? $this->histories->where('event', JapicCertificationEvent::FrCancelled)->last()
                    : $this->histories()->where('event', JapicCertificationEvent::FrCancelled->value)->latest('occurred_at')->first();
                $status = $cancellation?->from_status ?? JapicCertificationStatus::Pending;
            }

            return match ($status) {
                JapicCertificationStatus::Drafting => 1,
                JapicCertificationStatus::ForSigning, JapicCertificationStatus::AwaitingFinalUpload => 2,
                JapicCertificationStatus::Completed => 3,
                default => 0,
            };
        });
    }

    protected function deadlineDays(): Attribute
    {
        return Attribute::get(function (): int {
            $reference = match ($this->status) {
                JapicCertificationStatus::Completed => $this->completed_at,
                JapicCertificationStatus::Cancelled => $this->surfacedFormerRebel?->cancellation?->cancelled_at,
                default => now(),
            } ?? now();

            return (int) $reference->copy()->startOfDay()->diffInDays($this->due_at->copy()->startOfDay(), false);
        });
    }

    protected function deadlineLabel(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->deadline_days < 0 => abs($this->deadline_days).' '.str('day')->plural(abs($this->deadline_days)).' overdue',
            $this->deadline_days === 0 => 'Due today',
            default => $this->deadline_days.' '.str('day')->plural($this->deadline_days).' remaining',
        });
    }

    public function scopeWithTiming(Builder $query, string $timing): Builder
    {
        $operator = $timing === 'overdue' ? '>' : '<=';

        return $query->whereRaw('DATE('.self::deadlineReferenceSql().") {$operator} DATE(due_at)", [
            JapicCertificationStatus::Completed->value,
            JapicCertificationStatus::Cancelled->value,
            now()->toDateTimeString(),
        ]);
    }

    public static function deadlineReferenceSql(): string
    {
        return 'CASE WHEN status = ? THEN completed_at WHEN status = ? THEN (SELECT cancelled_at FROM ib39_fr_cancellations WHERE ib39_fr_cancellations.ib39_surfaced_former_rebel_id = japic_certification_processings.ib39_surfaced_former_rebel_id LIMIT 1) ELSE ? END';
    }

    public function surfacedFormerRebel(): BelongsTo
    {
        return $this->belongsTo(Ib39SurfacedFormerRebel::class, 'ib39_surfaced_former_rebel_id');
    }

    public function triggeringCdrDocumentVersion(): BelongsTo
    {
        return $this->belongsTo(Ib39CdrDocumentVersion::class, 'triggering_cdr_document_version_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function draft(): HasOne
    {
        return $this->hasOne(JapicCertificationDraft::class, 'processing_id');
    }

    public function draftHistories(): HasMany
    {
        return $this->hasMany(JapicCertificationDraftHistory::class, 'processing_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(JapicCertificationHistory::class, 'processing_id');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(JapicCertificationDocumentVersion::class, 'processing_id');
    }

    public function photoVersions(): HasMany
    {
        return $this->hasMany(JapicCertificationPhotoVersion::class, 'processing_id');
    }

    public function currentPhotoVersion(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationPhotoVersion::class, 'current_photo_version_id');
    }

    public function currentFinalVersion(): BelongsTo
    {
        return $this->belongsTo(JapicCertificationDocumentVersion::class, 'current_final_version_id');
    }
}
