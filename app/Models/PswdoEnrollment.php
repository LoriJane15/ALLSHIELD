<?php

namespace App\Models;

use App\Enums\PswdoEnrollmentDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PswdoEnrollment extends Model
{
    protected $guarded = ['id', 'ib39_surfaced_former_rebel_id'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }

    public function surfacedFormerRebel(): BelongsTo
    {
        return $this->belongsTo(Ib39SurfacedFormerRebel::class, 'ib39_surfaced_former_rebel_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PswdoEnrollmentDocument::class, 'pswdo_enrollment_id');
    }

    public function documentDrafts(): HasMany
    {
        return $this->hasMany(PswdoEnrollmentDocumentDraft::class, 'pswdo_enrollment_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PswdoEnrollmentComment::class, 'pswdo_enrollment_id');
    }

    public function hasDocument(PswdoEnrollmentDocumentType $type): bool
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return $documents->contains(fn (PswdoEnrollmentDocument $document): bool => $document->document_type === $type);
    }

    public function isCompleted(): bool
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return collect(PswdoEnrollmentDocumentType::cases())->every(fn (PswdoEnrollmentDocumentType $type): bool => $documents->contains(fn (PswdoEnrollmentDocument $document): bool => $document->document_type === $type
                && $document->isConfirmedFinal()));
    }

    public function completedDocumentCount(): int
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return $documents->filter(fn (PswdoEnrollmentDocument $document): bool => $document->isConfirmedFinal())->pluck('document_type')->unique()->count();
    }
}
