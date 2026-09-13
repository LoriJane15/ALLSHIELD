<?php

namespace App\Http\Controllers\Pswdo;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Http\Controllers\Controller;
use App\Models\PswdoEnrollment;
use App\Services\PswdoEligibilityService;
use App\Services\SurfacedFrDocumentsRecordsService;
use App\Services\SurfacedFrProgressTimelineService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(PswdoEligibilityService $eligibility): View
    {
        Gate::authorize('viewAny', PswdoEnrollment::class);
        $enrollments = PswdoEnrollment::query()
            ->whereHas('surfacedFormerRebel', fn ($query) => $eligibility->apply($query))
            ->with(['documents', 'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay'])
            ->latest('id')->paginate(20);

        return view('pswdo.enrollments.index', compact('enrollments'));
    }

    public function show(
        PswdoEnrollment $pswdoEnrollment,
        SurfacedFrDocumentsRecordsService $records,
        SurfacedFrProgressTimelineService $progressTimeline,
    ): View {
        Gate::authorize('view', $pswdoEnrollment);
        $this->loadProfile($pswdoEnrollment);
        $record = $pswdoEnrollment->surfacedFormerRebel;
        $record->setRelation('pswdoEnrollment', $pswdoEnrollment);

        return view('pswdo.enrollments.show', [
            'enrollment' => $pswdoEnrollment,
            'documentSummaries' => $records->summaries($record),
            'progressTimeline' => $progressTimeline->timeline($record),
            'documentLinks' => [
                'cdr' => route('pswdo.enrollments.records.cdr', $pswdoEnrollment),
                'japic' => route('pswdo.enrollments.records.certification', $pswdoEnrollment),
                'pswdo' => route('pswdo.enrollments.records.pswdo', $pswdoEnrollment),
                'fea' => route('pswdo.enrollments.records.fea', $pswdoEnrollment),
                'assistance' => route('pswdo.enrollments.records.assistance', $pswdoEnrollment),
            ],
        ]);
    }

    public function workspace(PswdoEnrollment $pswdoEnrollment): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->load(['documents.uploader:id,name', 'surfacedFormerRebel']);

        return view('pswdo.enrollments.workspace', [
            'enrollment' => $pswdoEnrollment,
            'types' => PswdoEnrollmentDocumentType::cases(),
        ]);
    }

    public function cdr(PswdoEnrollment $pswdoEnrollment, SurfacedFrDocumentsRecordsService $records): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->load('surfacedFormerRebel.cdrProcessing.currentFinalVersion');
        $record = $pswdoEnrollment->surfacedFormerRebel;
        $data = $records->cdrRecord($record);
        $final = $data['finalCdr'];

        return view('japic.certifications.records.cdr', $data + [
            'heading' => 'PSWDO Enrollment',
            'backUrl' => route('pswdo.enrollments.show', $pswdoEnrollment),
            'referenceNumber' => $record->reference_number,
            'previewUrl' => $final ? route('pswdo.cdr.documents.preview', [$data['cdr'], $final]) : null,
            'downloadUrl' => $final && $data['canDownload'] ? route('pswdo.cdr.documents.download', [$data['cdr'], $final]) : null,
        ]);
    }

    public function certification(PswdoEnrollment $pswdoEnrollment, SurfacedFrDocumentsRecordsService $records): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->load('surfacedFormerRebel.japicCertificationProcessing.currentFinalVersion');
        $record = $pswdoEnrollment->surfacedFormerRebel;
        $data = $records->certificationRecord($record);
        $final = $data['finalCertification'];

        return view('japic.certifications.records.certification', $data + [
            'heading' => 'PSWDO Enrollment',
            'backUrl' => route('pswdo.enrollments.show', $pswdoEnrollment),
            'previewUrl' => $final ? route('pswdo.japic.document-versions.preview', [$data['certification'], $final]) : null,
            'downloadUrl' => $final ? route('pswdo.japic.document-versions.download', [$data['certification'], $final]) : null,
        ]);
    }

    public function fea(PswdoEnrollment $pswdoEnrollment, SurfacedFrDocumentsRecordsService $records): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->load([
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
        ]);

        return view('japic.certifications.records.fea', [
            'heading' => 'PSWDO Enrollment',
            'backUrl' => route('pswdo.enrollments.show', $pswdoEnrollment),
            'documents' => $records->feaRecords(
                $pswdoEnrollment->surfacedFormerRebel,
                fn ($fea, $document, $version): string => route('pswdo.fea.documents.versions.preview', [$fea, $document, $version]),
                fn ($fea, $document, $version): string => route('pswdo.fea.documents.versions.download', [$fea, $document, $version]),
            ),
        ]);
    }

    public function pswdo(PswdoEnrollment $pswdoEnrollment, SurfacedFrDocumentsRecordsService $records): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->load(['documents', 'surfacedFormerRebel']);
        $record = $pswdoEnrollment->surfacedFormerRebel;
        $record->setRelation('pswdoEnrollment', $pswdoEnrollment);

        return view('japic.certifications.records.pswdo', [
            'heading' => 'PSWDO Enrollment',
            'backUrl' => route('pswdo.enrollments.show', $pswdoEnrollment),
            'documents' => $records->pswdoRecords(
                $record,
                fn ($enrollment, $document): string => route('pswdo.enrollments.documents.preview', [$enrollment, $document]),
                fn ($enrollment, $document): string => route('pswdo.enrollments.documents.download', [$enrollment, $document]),
            ),
        ]);
    }

    public function assistance(PswdoEnrollment $pswdoEnrollment): View
    {
        Gate::authorize('view', $pswdoEnrollment);

        return view('japic.certifications.records.assistance', [
            'heading' => 'PSWDO Enrollment',
            'backUrl' => route('pswdo.enrollments.show', $pswdoEnrollment),
        ]);
    }

    private function loadProfile(PswdoEnrollment $enrollment): void
    {
        $enrollment->load([
            'documents', 'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay',
            'surfacedFormerRebel.cancellation', 'surfacedFormerRebel.cdrProcessing.currentFinalVersion',
            'surfacedFormerRebel.japicCertificationProcessing.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
        ]);
    }
}
