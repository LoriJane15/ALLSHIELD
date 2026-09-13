<?php

namespace App\Http\Controllers\Japic;

use App\Enums\JapicCertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Japic\IndexCertificationRequest;
use App\Models\JapicCertificationProcessing;
use App\Services\JapicCertificationRevisionHistoryService;
use App\Services\SurfacedFrDocumentsRecordsService;
use App\Services\SurfacedFrProgressTimelineService;
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
                'surfacedFormerRebel.cancellation', 'surfacedFormerRebel.cdrProcessing.currentFinalVersion',
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
        $japicCertificationProcessing->load([
            'surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay',
            'surfacedFormerRebel.cancellation',
            'surfacedFormerRebel.cdrProcessing.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
            'surfacedFormerRebel.pswdoEnrollment.documents',
            'draft.lastSavedBy',
            'currentFinalVersion',
            'histories' => fn ($query) => $query->with('actor:id,name')->oldest('occurred_at'),
        ]);
        $record = $japicCertificationProcessing->surfacedFormerRebel;
        $record->setRelation('japicCertificationProcessing', $japicCertificationProcessing);

        return view('japic.certifications.show', [
            'processing' => $japicCertificationProcessing,
            'documentSummaries' => $documents->summaries($record),
            'progressTimeline' => $progressTimeline->timeline($record),
            'documentLinks' => [
                'cdr' => route('japic.certifications.records.cdr', $japicCertificationProcessing),
                'japic' => route('japic.certifications.records.certification', $japicCertificationProcessing),
                'pswdo' => route('japic.certifications.records.pswdo', $japicCertificationProcessing),
                'fea' => route('japic.certifications.records.fea', $japicCertificationProcessing),
                'assistance' => route('japic.certifications.records.assistance', $japicCertificationProcessing),
            ],
        ]);
    }

    public function cdr(JapicCertificationProcessing $japicCertificationProcessing, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $japicCertificationProcessing);
        $japicCertificationProcessing->load('surfacedFormerRebel.cdrProcessing.currentFinalVersion');
        $record = $japicCertificationProcessing->surfacedFormerRebel;
        $data = $documents->cdrRecord($record);
        $final = $data['finalCdr'];

        return view('japic.certifications.records.cdr', $data + [
            'backUrl' => route('japic.certifications.show', $japicCertificationProcessing),
            'referenceNumber' => $record->reference_number,
            'previewUrl' => $final ? route('japic.cdr.documents.preview', [$data['cdr'], $final]) : null,
            'downloadUrl' => $final && $data['canDownload'] ? route('japic.cdr.documents.download', [$data['cdr'], $final]) : null,
        ]);
    }

    public function fea(JapicCertificationProcessing $japicCertificationProcessing, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $japicCertificationProcessing);
        $japicCertificationProcessing->load([
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
        ]);

        return view('japic.certifications.records.fea', [
            'backUrl' => route('japic.certifications.show', $japicCertificationProcessing),
            'documents' => $documents->feaRecords(
                $japicCertificationProcessing->surfacedFormerRebel,
                fn ($fea, $document, $version): string => route('japic.fea.documents.versions.preview', [$fea, $document, $version]),
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
                fn ($enrollment, $document): string => route('japic.pswdo-enrollment-documents.preview', [$enrollment, $document]),
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

    public function certification(JapicCertificationProcessing $japicCertificationProcessing, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $japicCertificationProcessing);
        $japicCertificationProcessing->load(['surfacedFormerRebel', 'currentFinalVersion']);
        $record = $japicCertificationProcessing->surfacedFormerRebel;
        $record->setRelation('japicCertificationProcessing', $japicCertificationProcessing);
        $data = $documents->certificationRecord($record);
        $final = $data['finalCertification'];

        return view('japic.certifications.records.certification', $data + [
            'backUrl' => route('japic.certifications.show', $japicCertificationProcessing),
            'previewUrl' => $final ? route('japic.certifications.document-versions.preview', [$japicCertificationProcessing, $final]) : null,
            'downloadUrl' => $final ? route('japic.certifications.document-versions.download', [$japicCertificationProcessing, $final]) : null,
        ]);
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
