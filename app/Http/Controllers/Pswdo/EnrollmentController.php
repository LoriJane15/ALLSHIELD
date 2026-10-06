<?php

namespace App\Http\Controllers\Pswdo;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Http\Controllers\Controller;
use App\Models\PswdoEnrollment;
use App\Models\PswdoEnrollmentDocument;
use App\Services\PswdoEligibilityService;
use App\Services\SurfacedFrDocumentsRecordsService;
use App\Services\SurfacedFrProgressTimelineService;
use App\Support\ProcessingWorkspaceHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Request $request, PswdoEligibilityService $eligibility): View
    {
        Gate::authorize('viewAny', PswdoEnrollment::class);
        $status = $request->query('status');
        $requiredDocuments = count(PswdoEnrollmentDocumentType::cases());
        $enrollments = PswdoEnrollment::query()
            ->whereHas('surfacedFormerRebel', fn ($query) => $eligibility->apply($query))
            ->when($status === 'completed', fn ($query) => $query->whereHas('documents', fn ($documents) => $documents
                ->selectRaw('1')->groupBy('pswdo_enrollment_id')
                ->havingRaw('COUNT(DISTINCT document_type) = ?', [$requiredDocuments])))
            ->when($status === 'pending', fn ($query) => $query->whereRaw(
                '(SELECT COUNT(DISTINCT document_type) FROM pswdo_enrollment_documents WHERE pswdo_enrollment_documents.pswdo_enrollment_id = pswdo_enrollments.id) < ?',
                [$requiredDocuments],
            ))
            ->with(['documents', 'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay'])
            ->latest('id')->paginate(20)->withQueryString();

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
                'cdr' => $record->cdrProcessing
                    ? route('cdr.workspace', $record->cdrProcessing)
                    : route('pswdo.enrollments.records.cdr', $pswdoEnrollment),
                'japic' => $record->japicCertificationProcessing
                    ? route('japic.certifications.workspace', $record->japicCertificationProcessing)
                    : route('pswdo.enrollments.records.certification', $pswdoEnrollment),
                'pswdo' => route('pswdo.enrollments.records.pswdo', $pswdoEnrollment),
                'fea' => route('pswdo.enrollments.records.fea', $pswdoEnrollment),
                'assistance' => route('pswdo.enrollments.records.assistance', $pswdoEnrollment),
            ],
        ]);
    }

    public function workspace(Request $request, PswdoEnrollment $pswdoEnrollment, ?PswdoEnrollmentDocument $document = null): View|RedirectResponse
    {
        $isProcessor = Gate::allows('view', $pswdoEnrollment);
        abort_if(! $isProcessor && ! $document, 404);
        $activeType = $document?->document_type
            ?? PswdoEnrollmentDocumentType::tryFrom((string) $request->query('document_type', PswdoEnrollmentDocumentType::EclipEnrollmentForm->value))
            ?? PswdoEnrollmentDocumentType::EclipEnrollmentForm;

        $documentPreviewUrl = null;
        if ($document) {
            abort_unless($document->pswdo_enrollment_id === $pswdoEnrollment->id, 404);
            $document->setRelation('enrollment', $pswdoEnrollment);
            Gate::authorize('view', $document);
            if (! $document->isConfirmedFinal()) {
                $pswdoEnrollment->loadMissing('surfacedFormerRebel.japicCertificationProcessing');
                $record = $pswdoEnrollment->surfacedFormerRebel;
                $destination = match (true) {
                    request()->user()->hasRole('pswdo') => route('pswdo.enrollments.show', $pswdoEnrollment),
                    request()->user()->hasRole('japic') => route('japic.certifications.show', $record->japicCertificationProcessing),
                    default => route('ib39.fr-profiles.show', $record),
                };

                return redirect($destination)->with('error', 'Document is not available yet.');
            }

            $document->loadMissing('uploader:id,name,role');
            if (! $isProcessor) {
                $previewRoute = request()->user()->hasRole('japic')
                    ? 'japic.pswdo-enrollment-documents.preview'
                    : 'ib39.pswdo-enrollment-documents.preview';
                $documentPreviewUrl = route($previewRoute, [$pswdoEnrollment, $document]);
                $pswdoEnrollment->setRelation('documents', collect([$document]));
            }
        }

        $pswdoEnrollment->loadMissing([
            'documents.uploader:id,name,role', 'documentDrafts.savedBy:id,name,role',
            'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay',
            'comments' => fn ($query) => $query->with('user:id,name,role,logo,gov_agency_id', 'user.govAgency:id,profile')->oldest()->oldest('id'),
        ]);
        $activeDocument = $pswdoEnrollment->documents->first(
            fn (PswdoEnrollmentDocument $candidate): bool => $candidate->document_type === $activeType
        );
        $eclipDraft = $pswdoEnrollment->documentDrafts->first(
            fn ($draft): bool => $draft->document_type === PswdoEnrollmentDocumentType::EclipEnrollmentForm
        );
        $eclipPayload = array_replace($this->initialEclipPayload($pswdoEnrollment), $eclipDraft?->payload ?? []);
        $eclipReadOnly = $activeDocument?->document_type === PswdoEnrollmentDocumentType::EclipEnrollmentForm
            && $activeDocument->isConfirmedFinal();
        $initialInterviewDraft = $pswdoEnrollment->documentDrafts->first(
            fn ($draft): bool => $draft->document_type === PswdoEnrollmentDocumentType::InitialInterviewForm
        );
        $initialInterviewPayload = array_replace(
            $this->initialInterviewPayload($pswdoEnrollment),
            $initialInterviewDraft?->payload ?? [],
        );
        $initialInterviewReadOnly = $activeDocument?->document_type === PswdoEnrollmentDocumentType::InitialInterviewForm
            && $activeDocument->isConfirmedFinal();

        return view('pswdo.enrollments.workspace', [
            'enrollment' => $pswdoEnrollment,
            'types' => $document && ! $isProcessor ? [$document->document_type] : PswdoEnrollmentDocumentType::cases(),
            'selectedDocument' => $isProcessor ? null : $document,
            'activeType' => $activeType,
            'activeDocument' => $activeDocument,
            'documentPreviewUrl' => $documentPreviewUrl,
            'eclipDraft' => $eclipDraft,
            'eclipPayload' => $eclipPayload,
            'eclipReadOnly' => $eclipReadOnly,
            'canEditEclipDraft' => $isProcessor && Gate::allows('editDraft', [$pswdoEnrollment, PswdoEnrollmentDocumentType::EclipEnrollmentForm]),
            'initialInterviewDraft' => $initialInterviewDraft,
            'initialInterviewPayload' => $initialInterviewPayload,
            'initialInterviewReadOnly' => $initialInterviewReadOnly,
            'canEditInitialInterviewDraft' => $isProcessor && Gate::allows('editDraft', [$pswdoEnrollment, PswdoEnrollmentDocumentType::InitialInterviewForm]),
            'canComment' => Gate::allows('comment', $pswdoEnrollment),
            'workspaceEvents' => ProcessingWorkspaceHistory::pswdo($pswdoEnrollment),
        ]);
    }

    public function cdr(PswdoEnrollment $pswdoEnrollment): RedirectResponse
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->loadMissing('surfacedFormerRebel.cdrProcessing');
        $record = $pswdoEnrollment->surfacedFormerRebel;

        return $record->cdrProcessing
            ? redirect()->route('cdr.workspace', $record->cdrProcessing)
            : redirect()->route('pswdo.enrollments.show', $pswdoEnrollment)
                ->with('error', 'Document is not available yet.');
    }

    public function certification(PswdoEnrollment $pswdoEnrollment): RedirectResponse
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->loadMissing('surfacedFormerRebel.japicCertificationProcessing');
        $record = $pswdoEnrollment->surfacedFormerRebel;

        return $record->japicCertificationProcessing
            ? redirect()->route('japic.certifications.workspace', $record->japicCertificationProcessing)
            : redirect()->route('pswdo.enrollments.show', $pswdoEnrollment)
                ->with('error', 'Document is not available yet.');
    }

    public function fea(PswdoEnrollment $pswdoEnrollment, SurfacedFrDocumentsRecordsService $records): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $pswdoEnrollment->load([
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
        ]);

        return view('japic.certifications.records.fea', [
            'heading' => 'PSWDO Enrollment',
            'backUrl' => route('pswdo.enrollments.show', $pswdoEnrollment),
            'documents' => $records->feaRecords(
                $pswdoEnrollment->surfacedFormerRebel,
                fn ($fea, $document, $version): string => route('fea.documents.view', [$fea, $document]),
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
                fn ($enrollment, $document): string => route('pswdo.enrollments.workspace', [$enrollment, $document]),
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
        $enrollment->loadMissing([
            'documents', 'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay',
            'surfacedFormerRebel.cancellation', 'surfacedFormerRebel.cdrProcessing.finalDocument',
            'surfacedFormerRebel.japicCertificationProcessing.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
        ]);

        $record = $enrollment->surfacedFormerRebel;
        if ($record?->feaProcessing) {
            $record->feaProcessing->setRelation('surfacedFormerRebel', $record);
        }
    }

    private function initialEclipPayload(PswdoEnrollment $enrollment): array
    {
        $record = $enrollment->surfacedFormerRebel;
        $address = collect([
            $record?->specific_location,
            $record?->barangay?->name,
            $record?->municipality?->name,
            $record?->province,
        ])->filter(fn ($part): bool => filled($part))->join(', ');

        return [
            'last_name' => (string) $record?->last_name,
            'first_name' => (string) $record?->first_name,
            'middle_name' => '',
            'address' => $address,
            'reintegration_monitoring_number' => '',
            'japic_validation_date' => '',
            'civil_society_organization' => '',
            'other_government_agency' => '',
            'remarks' => '',
            'firearm_type' => '',
            'caliber' => '',
            'make' => '',
            'serial_number' => '',
            'firearm_remarks' => '',
            'date_of_issuance' => '',
            'place_of_issuance' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function initialInterviewPayload(PswdoEnrollment $enrollment): array
    {
        $record = $enrollment->surfacedFormerRebel;

        return [
            'last_name' => (string) $record?->last_name,
            'first_name' => (string) $record?->first_name,
            'middle_name' => '',
            'current_street' => (string) $record?->specific_location,
            'current_sitio' => '',
            'current_barangay' => (string) $record?->barangay?->name,
            'current_municipality_city' => (string) $record?->municipality?->name,
            'current_province' => (string) $record?->province,
            'current_psgc_barangay_code' => '',
        ];
    }
}
