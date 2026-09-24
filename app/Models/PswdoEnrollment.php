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

    public function hasDocument(PswdoEnrollmentDocumentType $type): bool
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return $documents->contains(fn (PswdoEnrollmentDocument $document): bool => $document->document_type === $type);
    }

    public function isCompleted(): bool
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return collect(PswdoEnrollmentDocumentType::cases())->every(fn (PswdoEnrollmentDocumentType $type): bool => $documents->contains(fn (PswdoEnrollmentDocument $document): bool => $document->document_type === $type
                && $this->isConfirmedFinal($document)));
    }

    public function completedDocumentCount(): int
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return $documents->filter($this->isConfirmedFinal(...))->pluck('document_type')->unique()->count();
    }

    private function isConfirmedFinal(PswdoEnrollmentDocument $document): bool
    {
        return $document->correct_document_type_confirmed
            && $document->belongs_to_fr_confirmed
            && $document->final_signed_confirmed
            && $document->uploaded_at !== null;
    }
}
