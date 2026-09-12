<?php

namespace App\Services;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

class SurfacedFrDocumentsRecordsService
{
    public function summaries(
        Ib39SurfacedFormerRebel $record,
        callable $pswdoPreviewUrl,
        callable $pswdoDownloadUrl,
    ): array {
        $this->assertRelationsLoaded($record);
        $certification = $record->japicCertificationProcessing;
        $enrollment = $record->pswdoEnrollment;
        $feaCount = $this->availableFeaDocuments($record)->count();

        $pswdoDocuments = collect(PswdoEnrollmentDocumentType::cases())->map(function (PswdoEnrollmentDocumentType $type) use ($enrollment, $pswdoPreviewUrl, $pswdoDownloadUrl): array {
            $document = $enrollment?->documents->firstWhere('document_type', $type);

            return [
                'label' => $type->label(),
                'status' => $document ? 'Completed' : 'Pending',
                'availability' => $document
                    ? 'Final signed PDF is available.'
                    : 'No PSWDO enrollment document is available yet.',
                'previewUrl' => $document ? $pswdoPreviewUrl($enrollment, $document) : null,
                'downloadUrl' => $document ? $pswdoDownloadUrl($enrollment, $document) : null,
            ];
        })->all();

        $availablePswdoCount = collect($pswdoDocuments)->whereNotNull('previewUrl')->count();

        return [
            'cdr' => [
                'label' => 'CDR',
                'status' => $record->cdr_status,
                'availability' => $record->hasCompletedCdrWithCurrentFinalDocument()
                    ? 'Current final CDR document is available.'
                    : 'No completed CDR document is available yet.',
            ],
            'japic' => [
                'label' => 'JAPIC Certification',
                'status' => $certification?->status->value ?? 'Not Available',
                'availability' => $record->hasCompletedJapicCertificationWithCurrentFinalDocument()
                    ? 'Current final JAPIC certification is available.'
                    : 'No final JAPIC certification document is available yet.',
            ],
            'pswdo' => [
                'label' => 'PSWDO Enrollment Documents',
                'status' => $enrollment?->isCompleted() ? 'Completed' : ($enrollment ? 'Pending' : 'Not Available'),
                'availability' => $availablePswdoCount > 0
                    ? $availablePswdoCount.' of 4 final signed PSWDO '.($availablePswdoCount === 1 ? 'document is' : 'documents are').' available.'
                    : 'No PSWDO enrollment documents are available yet.',
                'documents' => $pswdoDocuments,
            ],
            'fea' => [
                'label' => 'FEA Processing Documents',
                'status' => $record->feaProcessing?->overallStatus()->value ?? ($record->possessed_firearms ? 'Not Available' : 'Not Applicable'),
                'availability' => $feaCount > 0
                    ? $feaCount.' FEA processing document '.($feaCount === 1 ? 'type is' : 'types are').' available.'
                    : 'No FEA processing documents are available yet.',
            ],
            'assistance' => [
                'label' => 'Assistance Records',
                'status' => 'Not Available',
                'availability' => 'No assistance records are available yet.',
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
        ];
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
                $document->currentDraftVersion,
                $document->currentSupportingPhotoVersion,
                $document->currentSurrenderedPhotoVersion,
            ])->filter()->map(fn ($version): array => [
                'label' => $version->slot->label(),
                'previewUrl' => $previewUrl($fea, $document, $version),
                'downloadUrl' => $downloadUrl($fea, $document, $version),
            ])->values();

            return ['label' => $document->document_type->label(), 'versions' => $versions];
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
        ];
    }

    private function availableFeaDocuments(Ib39SurfacedFormerRebel $record): Collection
    {
        return $record->feaProcessing?->documents->filter(fn ($document): bool => $document->currentDraftVersion !== null
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
            foreach (['currentDraftVersion', 'currentSupportingPhotoVersion', 'currentSurrenderedPhotoVersion'] as $relation) {
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
