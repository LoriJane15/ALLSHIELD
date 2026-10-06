<?php

namespace App\Http\Controllers\Japic;

use App\Enums\JapicCertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Japic\IndexCertificationRequest;
use App\Models\JapicCertificationProcessing;
use App\Services\JapicCertificationRevisionHistoryService;
use App\Services\SurfacedFrDocumentsRecordsService;
use App\Services\SurfacedFrProgressTimelineService;
use App\Support\JapicCertificationDraftSchema;
use App\Support\ProcessingWorkspaceHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CertificationController extends Controller
{
    public function index(IndexCertificationRequest $request): View
    {
        Gate::authorize('viewAny', JapicCertificationProcessing::class);
        $filters = $request->validatedFilters();
        $search = $filters['search'] ?? null;
        $escaped = $search === null ? null : str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        $records = JapicCertificationProcessing::query()
            ->where(fn ($query) => $query->whereNull('assigned_to')->orWhere('assigned_to', $request->user()->id))
            ->with([
                'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay',
                'surfacedFormerRebel.cancellation', 'surfacedFormerRebel.cdrProcessing.finalDocument',
                'currentFinalVersion',
            ])
            ->when($escaped, fn ($query) => $query->whereHas('surfacedFormerRebel', fn ($fr) => $fr
                ->where(fn ($match) => $match
                    ->whereRaw("reference_number LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                    ->orWhereRaw("first_name LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                    ->orWhereRaw("last_name LIKE ? ESCAPE '\\'", ["%{$escaped}%"]))))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['timing'] ?? null, fn ($query, $timing) => $query->withTiming($timing))
            ->when($filters['received_from'] ?? null, fn ($query, $date) => $query->whereDate('received_at', '>=', $date))
            ->when($filters['received_to'] ?? null, fn ($query, $date) => $query->whereDate('received_at', '<=', $date))
            ->when($filters['due_from'] ?? null, fn ($query, $date) => $query->whereDate('due_at', '>=', $date))
            ->when($filters['due_to'] ?? null, fn ($query, $date) => $query->whereDate('due_at', '<=', $date))
            ->when(array_key_exists('cancelled', $filters), fn ($query) => $filters['cancelled']
                ? $query->whereHas('surfacedFormerRebel.cancellation')
                : $query->whereDoesntHave('surfacedFormerRebel.cancellation'))
            ->orderBy('due_at')->orderBy('id')->paginate(15)->appends($filters);
        $records->getCollection()->each(function (JapicCertificationProcessing $processing): void {
            $processing->surfacedFormerRebel->setRelation('japicCertificationProcessing', $processing);
        });

        return view('japic.certifications.index', [
            'records' => $records,
            'statuses' => JapicCertificationStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(
        JapicCertificationProcessing $japicCertificationProcessing,
        SurfacedFrDocumentsRecordsService $documents,
        SurfacedFrProgressTimelineService $progressTimeline,
    ): View {
        Gate::authorize('view', $japicCertificationProcessing);
        $japicCertificationProcessing->loadMissing([
            'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay',
            'surfacedFormerRebel.cancellation',
            'surfacedFormerRebel.cdrProcessing.finalDocument',
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
            'surfacedFormerRebel.pswdoEnrollment.documents',
            'currentFinalVersion',
        ]);
        $record = $japicCertificationProcessing->surfacedFormerRebel;
        $record->setRelation('japicCertificationProcessing', $japicCertificationProcessing);

        return view('japic.certifications.show', [
            'processing' => $japicCertificationProcessing,
            'documentSummaries' => $documents->summaries($record),
            'progressTimeline' => $progressTimeline->timeline($record),
            'documentLinks' => [
                'cdr' => $record->cdrProcessing
                    ? route('cdr.workspace', $record->cdrProcessing)
                    : route('japic.certifications.records.cdr', $japicCertificationProcessing),
                'japic' => route('japic.certifications.workspace', $japicCertificationProcessing),
                'pswdo' => route('japic.certifications.records.pswdo', $japicCertificationProcessing),
                'fea' => route('japic.certifications.records.fea', $japicCertificationProcessing),
                'assistance' => route('japic.certifications.records.assistance', $japicCertificationProcessing),
            ],
        ]);
    }

    public function workspace(JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationDraftSchema $schema): View|RedirectResponse
    {
        Gate::authorize('viewDocument', $japicCertificationProcessing);
        if (! Gate::allows('view', $japicCertificationProcessing)
            && $japicCertificationProcessing->status !== JapicCertificationStatus::Completed) {
            $japicCertificationProcessing->loadMissing('surfacedFormerRebel.pswdoEnrollment');
            $record = $japicCertificationProcessing->surfacedFormerRebel;
            $destination = request()->user()->hasRole('pswdo')
                ? route('pswdo.enrollments.show', $record->pswdoEnrollment)
                : route('ib39.fr-profiles.show', $record);

            return redirect($destination)->with('error', 'Document is not available yet.');
        }
        $japicCertificationProcessing->loadMissing([
            'surfacedFormerRebel.cancellation',
            'draft',
            'currentFinalVersion',
            'histories' => fn ($query) => $query->with(['actor:id,name,role', 'documentVersion:id,processing_id,version_number,original_filename'])->oldest('occurred_at')->oldest('id'),
            'comments' => fn ($query) => $query->with('user:id,name,role,logo,gov_agency_id', 'user.govAgency:id,profile')->oldest()->oldest('id'),
        ]);

        $responsibleProcessor = Gate::allows('view', $japicCertificationProcessing);
        $canEditDraft = Gate::allows('editDraft', $japicCertificationProcessing);
        $canUploadFinal = Gate::allows('uploadFinal', $japicCertificationProcessing);
        $draft = $japicCertificationProcessing->draft;
        $final = $japicCertificationProcessing->currentFinalVersion;
        $draftReadOnly = $responsibleProcessor
            && $japicCertificationProcessing->status === JapicCertificationStatus::Completed;
        $final?->setRelation('processing', $japicCertificationProcessing);
        $payload = null;
        if ($canEditDraft || $draftReadOnly) {
            $japicCertificationProcessing->loadMissing('currentPhotoVersion');
            $payload = $draft
                ? $schema->forReading($draft->payload, $japicCertificationProcessing->control_number)
                : $schema->normalize(['certificate' => []], $schema->sourceSnapshot($japicCertificationProcessing), $japicCertificationProcessing->control_number, null);
        }

        return view('japic.certifications.workspace', [
            'processing' => $japicCertificationProcessing,
            'workspaceEvents' => ProcessingWorkspaceHistory::japic($japicCertificationProcessing),
            'responsibleProcessor' => $responsibleProcessor,
            'draftReadOnly' => $draftReadOnly,
            'canEditDraft' => $canEditDraft,
            'canUploadFinal' => $canUploadFinal,
            'canPreviewDraft' => $draft !== null && Gate::allows('previewDraft', $japicCertificationProcessing),
            'canComment' => Gate::allows('comment', $japicCertificationProcessing),
            'canSubmitForSigning' => $draft !== null && Gate::allows('submitForSigning', $japicCertificationProcessing),
            'canPreviewFinal' => $final !== null && Gate::allows('preview', $final),
            'payload' => $payload,
            'wording' => $payload ? $schema->wording($payload) : null,
            'affiliationPeriod' => $payload ? data_get($payload, 'source_snapshot.affiliation_period') : null,
            'maxPersonnelRows' => JapicCertificationDraftSchema::MAX_PERSONNEL_ROWS,
        ]);
    }

    public function cdr(JapicCertificationProcessing $japicCertificationProcessing): RedirectResponse
    {
        Gate::authorize('view', $japicCertificationProcessing);
        $japicCertificationProcessing->loadMissing('surfacedFormerRebel.cdrProcessing');
        $record = $japicCertificationProcessing->surfacedFormerRebel;

        return $record->cdrProcessing
            ? redirect()->route('cdr.workspace', $record->cdrProcessing)
            : redirect()->route('japic.certifications.show', $japicCertificationProcessing)
                ->with('error', 'Document is not available yet.');
    }

    public function fea(JapicCertificationProcessing $japicCertificationProcessing, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $japicCertificationProcessing);
        $japicCertificationProcessing->load([
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
        ]);

        return view('japic.certifications.records.fea', [
            'backUrl' => route('japic.certifications.show', $japicCertificationProcessing),
            'documents' => $documents->feaRecords(
                $japicCertificationProcessing->surfacedFormerRebel,
                fn ($fea, $document, $version): string => route('fea.documents.view', [$fea, $document]),
                fn ($fea, $document, $version): string => route('japic.fea.documents.versions.download', [$fea, $document, $version]),
            ),
        ]);
    }

    public function pswdo(JapicCertificationProcessing $japicCertificationProcessing, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $japicCertificationProcessing);
        $japicCertificationProcessing->load('surfacedFormerRebel.pswdoEnrollment.documents');
        $record = $japicCertificationProcessing->surfacedFormerRebel;

        return view('japic.certifications.records.pswdo', [
            'backUrl' => route('japic.certifications.show', $japicCertificationProcessing),
            'documents' => $documents->pswdoRecords(
                $record,
                fn ($enrollment, $document): string => route('pswdo.enrollments.workspace', [$enrollment, $document]),
                fn ($enrollment, $document): string => route('japic.pswdo-enrollment-documents.download', [$enrollment, $document]),
            ),
        ]);
    }

    public function assistance(JapicCertificationProcessing $japicCertificationProcessing): View
    {
        Gate::authorize('view', $japicCertificationProcessing);

        return view('japic.certifications.records.assistance', [
            'backUrl' => route('japic.certifications.show', $japicCertificationProcessing),
        ]);
    }

    public function certification(JapicCertificationProcessing $japicCertificationProcessing): RedirectResponse
    {
        Gate::authorize('view', $japicCertificationProcessing);

        return redirect()->route('japic.certifications.workspace', $japicCertificationProcessing);
    }

    public function history(
        JapicCertificationProcessing $japicCertificationProcessing,
        JapicCertificationRevisionHistoryService $history,
        ?int $revision = null,
    ): View {
        Gate::authorize('view', $japicCertificationProcessing);

        return view('japic.certifications.history', $history->viewModel($japicCertificationProcessing, $revision));
    }
}
