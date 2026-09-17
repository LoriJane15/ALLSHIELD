<?php

namespace App\Services;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\Ib39SurfacedFormerRebel;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

class SurfacedFrDocumentsRecordsService
{
    public function summaries(Ib39SurfacedFormerRebel $record): array
    {
        $this->assertRelationsLoaded($record);
        $certification = $record->japicCertificationProcessing;
        $enrollment = $record->pswdoEnrollment;

        return [
            'cdr' => [
                'label' => 'CDR',
                'status' => $record->cdr_status,
            ],
            'japic' => [
                'label' => 'JAPIC Certification',
                'status' => $certification?->status->value ?? 'Not Available',
            ],
            'pswdo' => [
                'label' => 'PSWDO Enrollment Documents',
                'status' => $enrollment?->isCompleted() ? 'Completed' : ($enrollment ? 'Pending' : 'Not Available'),
            ],
            'fea' => [
                'label' => 'FEA Processing Documents',
                'status' => $record->feaProcessing?->overallStatus()->value ?? ($record->possessed_firearms ? 'Not Available' : 'Not Applicable'),
            ],
            'assistance' => [
                'label' => 'Assistance Records',
                'status' => 'Not Available',
            ],
        ];
    }

    public function cdrRecord(Ib39SurfacedFormerRebel $record): array
    {
        $this->assertCdrLoaded($record);
        $cdr = $record->cdrProcessing;
        $final = $record->hasCompletedCdrWithCurrentFinalDocument() ? $cdr->currentFinalVersion : null;

        return [
            'cdrStatus' => $cdr?->status->value ?? 'Not Available',
            'cdr' => $cdr,
            'finalCdr' => $final,
            'canDownload' => $final?->source_type === Ib39CdrDocumentSource::Uploaded,
            'date' => $this->date($cdr?->completed_at, 'Completed', $final?->finalized_at, 'Finalized'),
        ];
    }

    public function pswdoRecords(
        Ib39SurfacedFormerRebel $record,
        callable $previewUrl,
        callable $downloadUrl,
    ): Collection {
        $this->assertPswdoLoaded($record);
        $enrollment = $record->pswdoEnrollment;
        if (! $enrollment) {
            return collect();
        }

        return collect(PswdoEnrollmentDocumentType::cases())
            ->map(function (PswdoEnrollmentDocumentType $type) use ($enrollment, $previewUrl, $downloadUrl): ?array {
                $document = $enrollment->documents->firstWhere('document_type', $type);
                if (! $document) {
                    return null;
                }

                return [
                    'label' => $type->label(),
                    'date' => $this->date($document->uploaded_at, 'Uploaded'),
                    'previewUrl' => $previewUrl($enrollment, $document),
                    'downloadUrl' => $downloadUrl($enrollment, $document),
                ];
            })
            ->filter()
            ->values();
    }

    public function feaRecords(Ib39SurfacedFormerRebel $record, callable $previewUrl, callable $downloadUrl): Collection
    {
        $this->assertFeaLoaded($record);
        $fea = $record->feaProcessing;
        if (! $fea) {
            return collect();
        }

        return $this->availableFeaDocuments($record)->map(function ($document) use ($fea, $previewUrl, $downloadUrl): array {
            $versions = collect([
                $document->currentFinalVersion,
                $document->currentDraftVersion,
                $document->currentSupportingPhotoVersion,
                $document->currentSurrenderedPhotoVersion,
            ])->filter()->map(fn ($version): array => [
                'label' => $version->slot->label(),
                'date' => $this->date($version->created_at, 'Uploaded'),
                'previewUrl' => $previewUrl($fea, $document, $version),
                'downloadUrl' => $downloadUrl($fea, $document, $version),
            ])->values();

            return [
                'label' => $document->document_type->label(),
                'status' => $document->status->value,
                'versions' => $versions,
            ];
        })->values();
    }

    public function certificationRecord(Ib39SurfacedFormerRebel $record): array
    {
        $this->assertCertificationLoaded($record);
        $certification = $record->japicCertificationProcessing;
        $final = $record->hasCompletedJapicCertificationWithCurrentFinalDocument() ? $certification->currentFinalVersion : null;

        return [
            'certificationStatus' => $certification?->status->value ?? 'Not Available',
            'certification' => $certification,
            'finalCertification' => $final,
            'date' => $this->date($certification?->completed_at, 'Completed', $final?->uploaded_at, 'Uploaded'),
        ];
    }

    private function date(
        ?DateTimeInterface $primary,
        string $primaryLabel,
        ?DateTimeInterface $fallback = null,
        ?string $fallbackLabel = null,
    ): array {
        $date = $primary ?? $fallback;
        if (! $date) {
            return ['label' => null, 'value' => 'Date unavailable'];
        }

        return [
            'label' => $primary ? $primaryLabel : $fallbackLabel,
            'value' => $date->format('F d, Y · h:i A'),
        ];
    }

    private function availableFeaDocuments(Ib39SurfacedFormerRebel $record): Collection
    {
        return $record->feaProcessing?->documents->filter(fn ($document): bool => $document->currentDraftVersion !== null
            || $document->currentFinalVersion !== null
            || $document->currentSupportingPhotoVersion !== null
            || $document->currentSurrenderedPhotoVersion !== null)->values() ?? collect();
    }

    private function assertRelationsLoaded(Ib39SurfacedFormerRebel $record): void
    {
        $this->assertCdrLoaded($record);
        $this->assertFeaLoaded($record);
        $this->assertCertificationLoaded($record);
        $this->assertPswdoLoaded($record);
    }

    private function assertCdrLoaded(Ib39SurfacedFormerRebel $record): void
    {
        $this->assertLoaded($record, 'cdrProcessing');
        if ($record->cdrProcessing) {
            $this->assertLoaded($record->cdrProcessing, 'currentFinalVersion');
        }
    }

    private function assertFeaLoaded(Ib39SurfacedFormerRebel $record): void
    {
        $this->assertLoaded($record, 'feaProcessing');
        if (! $record->feaProcessing) {
            return;
        }
        $this->assertLoaded($record->feaProcessing, 'documents');
        foreach ($record->feaProcessing->documents as $document) {
            foreach (['currentFinalVersion', 'currentDraftVersion', 'currentSupportingPhotoVersion', 'currentSurrenderedPhotoVersion'] as $relation) {
                $this->assertLoaded($document, $relation);
            }
        }
    }

    private function assertCertificationLoaded(Ib39SurfacedFormerRebel $record): void
    {
        $this->assertLoaded($record, 'japicCertificationProcessing');
        if ($record->japicCertificationProcessing) {
            $this->assertLoaded($record->japicCertificationProcessing, 'currentFinalVersion');
        }
    }

    private function assertPswdoLoaded(Ib39SurfacedFormerRebel $record): void
    {
        $this->assertLoaded($record, 'pswdoEnrollment');
        if ($record->pswdoEnrollment) {
            $this->assertLoaded($record->pswdoEnrollment, 'documents');
        }
    }

    private function assertLoaded(Model $model, string $relation): void
    {
        if (! $model->relationLoaded($relation)) {
            throw new LogicException("The {$relation} relation must be eager loaded before building Documents/Records data.");
        }
    }
}
