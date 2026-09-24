<?php

namespace App\Services;

use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FeaOverallStatus;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class SurfacedFrProgressTimelineService
{
    /**
     * @return array<int, array{key: string, label: string, state: string, status: string, description: string, is_current: bool}>
     */
    public function timeline(Ib39SurfacedFormerRebel $record): array
    {
        $this->assertRequiredRelationsLoaded($record);

        $cdrCompleted = $record->hasCompletedCdrWithCurrentFinalDocument();
        $japicCompleted = $record->hasCompletedJapicCertificationWithCurrentFinalDocument();
        $pswdoCompleted = $record->pswdoEnrollment?->isCompleted() ?? false;
        $feaCompleted = $record->possessed_firearms
            && $record->feaProcessing?->overallStatus() === Ib39FeaOverallStatus::Completed;
        $feaNotApplicable = ! $record->possessed_firearms;

        return $this->markCurrentPhase([
            $this->cdrPhase($record, $cdrCompleted),
            $this->japicPhase($record, $cdrCompleted, $japicCompleted),
            $this->pswdoPhase($record, $japicCompleted, $pswdoCompleted),
            $this->feaPhase($record, $pswdoCompleted, $feaCompleted, $feaNotApplicable),
            $this->reintegrationPhase(
                $record,
                $cdrCompleted && $japicCompleted && $pswdoCompleted && ($feaCompleted || $feaNotApplicable),
            ),
        ]);
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function cdrPhase(Ib39SurfacedFormerRebel $record, bool $completed): array
    {
        if ($completed) {
            return $this->phase('cdr', 'CDR', 'completed', 'Completed', 'Completed CDR with a valid current final document.');
        }
        if ($record->cancellation) {
            return $this->cancelledPhase('cdr', 'CDR');
        }

        $status = $record->cdrProcessing?->status;

        return match ($status) {
            Ib39CdrStatus::Completed => $this->unavailablePhase('cdr', 'CDR', 'The completed CDR does not have a valid owned current final document.'),
            Ib39CdrStatus::Ongoing => $this->phase('cdr', 'CDR', 'ongoing', 'Ongoing', 'CDR processing is in progress.'),
            default => $this->phase('cdr', 'CDR', 'pending', 'Pending', 'CDR processing has not been completed.'),
        };
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function japicPhase(Ib39SurfacedFormerRebel $record, bool $cdrCompleted, bool $completed): array
    {
        if ($completed) {
            return $this->phase('japic', 'JAPIC Certification', 'completed', 'Completed', 'Completed certification with a valid owned final signed document.');
        }
        if (! $cdrCompleted) {
            return $this->lockedPhase('japic', 'JAPIC Certification');
        }
        if ($record->cancellation || $record->japicCertificationProcessing?->status === JapicCertificationStatus::Cancelled) {
            return $this->cancelledPhase('japic', 'JAPIC Certification');
        }

        $status = $record->japicCertificationProcessing?->status;

        return match ($status) {
            JapicCertificationStatus::Completed => $this->unavailablePhase('japic', 'JAPIC Certification', 'The completed certification does not have a valid owned final signed document.'),
            JapicCertificationStatus::Drafting,
            JapicCertificationStatus::ForSigning,
            JapicCertificationStatus::AwaitingFinalUpload => $this->phase('japic', 'JAPIC Certification', 'ongoing', 'Ongoing', "Certification is currently {$status->value}."),
            JapicCertificationStatus::Pending => $this->phase('japic', 'JAPIC Certification', 'pending', 'Pending', 'Certification processing has not started.'),
            default => $this->unavailablePhase('japic', 'JAPIC Certification', 'No certification workflow is available for the completed CDR.'),
        };
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function pswdoPhase(Ib39SurfacedFormerRebel $record, bool $japicCompleted, bool $completed): array
    {
        if ($completed) {
            return $this->phase('pswdo', 'PSWDO Enrollment', 'completed', 'Completed', 'All four required final signed enrollment documents are available.');
        }
        if (! $japicCompleted) {
            return $this->lockedPhase('pswdo', 'PSWDO Enrollment');
        }
        if ($record->cancellation) {
            return $this->cancelledPhase('pswdo', 'PSWDO Enrollment');
        }
        if (! $record->pswdoEnrollment) {
            return $this->phase('pswdo', 'PSWDO Enrollment', 'not-started', 'Not Started', 'No PSWDO enrollment workflow is available yet.');
        }

        $completedCount = $record->pswdoEnrollment->completedDocumentCount();

        return $completedCount > 0
            ? $this->phase('pswdo', 'PSWDO Enrollment', 'ongoing', 'Ongoing', "{$completedCount} of 4 required final signed documents are available.")
            : $this->phase('pswdo', 'PSWDO Enrollment', 'pending', 'Pending', 'No required final signed enrollment document is available yet.');
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function feaPhase(
        Ib39SurfacedFormerRebel $record,
        bool $pswdoCompleted,
        bool $completed,
        bool $notApplicable,
    ): array {
        if ($notApplicable) {
            return $this->phase('fea', 'FEA Processing', 'not-applicable', 'Not Applicable', 'The surfaced FR is recorded as not possessing firearms.');
        }
        if ($completed) {
            return $this->phase('fea', 'FEA Processing', 'completed', 'Completed', 'All required FEA documents have the authoritative completed status.');
        }
        if (! $pswdoCompleted) {
            return $this->lockedPhase('fea', 'FEA Processing');
        }
        if ($record->cancellation) {
            return $this->cancelledPhase('fea', 'FEA Processing');
        }
        if (! $record->feaProcessing) {
            return $this->unavailablePhase('fea', 'FEA Processing', 'No FEA processing workflow is available for this firearms record.');
        }

        return match ($record->feaProcessing->overallStatus()) {
            Ib39FeaOverallStatus::ForCompliance => $this->phase('fea', 'FEA Processing', 'ongoing', 'For Compliance', 'One or more required FEA documents require compliance.'),
            Ib39FeaOverallStatus::Processing => $this->phase('fea', 'FEA Processing', 'ongoing', 'Ongoing', 'FEA document processing is in progress.'),
            Ib39FeaOverallStatus::Pending,
            Ib39FeaOverallStatus::ReadyForProcessing => $this->phase('fea', 'FEA Processing', 'pending', 'Pending', 'Required FEA document processing is not complete.'),
            default => $this->unavailablePhase('fea', 'FEA Processing', 'The FEA workflow does not have an available completion state.'),
        };
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function reintegrationPhase(Ib39SurfacedFormerRebel $record, bool $prerequisitesCompleted): array
    {
        if (! $prerequisitesCompleted) {
            return $this->lockedPhase('reintegration', 'Reintegration');
        }
        if ($record->cancellation) {
            return $this->cancelledPhase('reintegration', 'Reintegration');
        }

        return $this->phase(
            'reintegration',
            'Reintegration',
            'not-started',
            'Not Started',
            'No authoritative surfaced-FR reintegration workflow is currently available.',
        );
    }

    /**
     * @param  array<int, array{key: string, label: string, state: string, status: string, description: string, is_current: bool}>  $phases
     * @return array<int, array{key: string, label: string, state: string, status: string, description: string, is_current: bool}>
     */
    private function markCurrentPhase(array $phases): array
    {
        $hasCurrentPhase = false;

        foreach ($phases as &$phase) {
            if (in_array($phase['state'], ['completed', 'not-applicable'], true)) {
                continue;
            }
            if (! $hasCurrentPhase) {
                $phase['is_current'] = true;
                $hasCurrentPhase = true;

                continue;
            }

            $phase = $this->lockedPhase($phase['key'], $phase['label']);
        }
        unset($phase);

        return $phases;
    }

    private function assertRequiredRelationsLoaded(Ib39SurfacedFormerRebel $record): void
    {
        foreach (['cdrProcessing', 'japicCertificationProcessing', 'pswdoEnrollment', 'feaProcessing', 'cancellation'] as $relation) {
            $this->assertRelationLoaded($record, $relation);
        }

        if ($record->cdrProcessing) {
            $this->assertRelationLoaded($record->cdrProcessing, 'currentFinalVersion');
        }
        if ($record->japicCertificationProcessing) {
            $this->assertRelationLoaded($record->japicCertificationProcessing, 'currentFinalVersion');
        }
        if ($record->pswdoEnrollment) {
            $this->assertRelationLoaded($record->pswdoEnrollment, 'documents');
        }
        if ($record->feaProcessing) {
            $this->assertRelationLoaded($record->feaProcessing, 'documents');
        }
    }

    private function assertRelationLoaded(Model $model, string $relation): void
    {
        if (! $model->relationLoaded($relation)) {
            throw new LogicException("The {$relation} relation must be eager loaded before building surfaced-FR progress.");
        }
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function phase(string $key, string $label, string $state, string $status, string $description): array
    {
        return compact('key', 'label', 'state', 'status', 'description') + ['is_current' => false];
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function lockedPhase(string $key, string $label): array
    {
        return $this->phase($key, $label, 'locked', 'Locked', 'Complete the preceding applicable phase first.');
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function cancelledPhase(string $key, string $label): array
    {
        return $this->phase($key, $label, 'cancelled', 'Cancelled', 'Processing stopped because the surfaced FR was cancelled.');
    }

    /** @return array{key: string, label: string, state: string, status: string, description: string, is_current: bool} */
    private function unavailablePhase(string $key, string $label, string $description): array
    {
        return $this->phase($key, $label, 'unavailable', 'Unavailable', $description);
    }
}
