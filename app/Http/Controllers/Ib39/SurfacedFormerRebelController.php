<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39FrCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\IndexSurfacedFormerRebelRequest;
use App\Http\Requests\Ib39\StoreSurfacedFormerRebelRequest;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\SurfacedFrDocumentsRecordsService;
use App\Services\SurfacedFrProgressTimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SurfacedFormerRebelController extends Controller
{
    public function index(IndexSurfacedFormerRebelRequest $request): View
    {
        Gate::authorize('viewAny', Ib39SurfacedFormerRebel::class);

        $filters = $request->validatedFilters();
        $search = $filters['search'] ?? null;

        $records = Ib39SurfacedFormerRebel::query()
            ->with([
                'municipality',
                'barangay',
                'cdrProcessing.currentFinalVersion',
                'japicCertificationProcessing.currentFinalVersion',
                'cancellation:id,ib39_surfaced_former_rebel_id',
            ])
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['municipality_id'] ?? null, fn ($query, int $municipalityId) => $query->where('municipality_id', $municipalityId))
            ->when(array_key_exists('possessed_firearms', $filters), fn ($query) => $query->where('possessed_firearms', $filters['possessed_firearms']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($filters);

        return view('ib39.fr-profiles.index', [
            'records' => $records,
            'categories' => Ib39FrCategory::cases(),
            'municipalities' => Municipality::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'hasActiveFilters' => collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Ib39SurfacedFormerRebel::class);

        return view('ib39.fr-profiles.create', [
            'categories' => Ib39FrCategory::cases(),
            'municipalities' => Municipality::query()
                ->with(['barangays' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
        ]);
    }

    public function show(
        Ib39SurfacedFormerRebel $ib39SurfacedFormerRebel,
        SurfacedFrDocumentsRecordsService $documents,
        SurfacedFrProgressTimelineService $progressTimeline,
    ): View {
        Gate::authorize('view', $ib39SurfacedFormerRebel);

        $ib39SurfacedFormerRebel->load([
            'municipality',
            'barangay',
            'creator',
            'cdrProcessing.currentFinalVersion',
            'feaProcessing.documents.currentDraftVersion',
            'feaProcessing.documents.currentSupportingPhotoVersion',
            'feaProcessing.documents.currentSurrenderedPhotoVersion',
            'japicCertificationProcessing.currentFinalVersion',
            'pswdoEnrollment.documents',
            'cancellation.cancelledBy',
        ]);

        return view('ib39.fr-profiles.show', [
            'record' => $ib39SurfacedFormerRebel,
            'recordedBy' => filled($ib39SurfacedFormerRebel->creator?->name)
                ? $ib39SurfacedFormerRebel->creator->name
                : 'Unknown user',
            'documentSummaries' => $documents->summaries($ib39SurfacedFormerRebel),
            'progressTimeline' => $progressTimeline->timeline($ib39SurfacedFormerRebel),
            'documentLinks' => [
                'cdr' => route('ib39.fr-profiles.records.cdr', $ib39SurfacedFormerRebel),
                'fea' => route('ib39.fr-profiles.records.fea', $ib39SurfacedFormerRebel),
                'assistance' => route('ib39.fr-profiles.records.assistance', $ib39SurfacedFormerRebel),
                'japic' => route('ib39.fr-profiles.records.certification', $ib39SurfacedFormerRebel),
            ],
        ]);
    }

    public function cdr(Ib39SurfacedFormerRebel $ib39SurfacedFormerRebel, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $ib39SurfacedFormerRebel);
        $ib39SurfacedFormerRebel->load('cdrProcessing.currentFinalVersion');
        $data = $documents->cdrRecord($ib39SurfacedFormerRebel);
        $final = $data['finalCdr'];

        return view('japic.certifications.records.cdr', $data + [
            'backUrl' => route('ib39.fr-profiles.show', $ib39SurfacedFormerRebel),
            'referenceNumber' => $ib39SurfacedFormerRebel->reference_number,
            'previewUrl' => $final ? route('ib39.cdr.documents.preview', [$data['cdr'], $final]) : null,
            'downloadUrl' => $final && $data['canDownload'] ? route('ib39.cdr.documents.download', [$data['cdr'], $final]) : null,
        ]);
    }

    public function fea(Ib39SurfacedFormerRebel $ib39SurfacedFormerRebel, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $ib39SurfacedFormerRebel);
        $ib39SurfacedFormerRebel->load([
            'feaProcessing.documents.currentDraftVersion',
            'feaProcessing.documents.currentSupportingPhotoVersion',
            'feaProcessing.documents.currentSurrenderedPhotoVersion',
        ]);

        return view('japic.certifications.records.fea', [
            'backUrl' => route('ib39.fr-profiles.show', $ib39SurfacedFormerRebel),
            'documents' => $documents->feaRecords(
                $ib39SurfacedFormerRebel,
                fn ($fea, $document, $version): string => route('ib39.fea.documents.versions.preview', [$fea, $document, $version]),
                fn ($fea, $document, $version): string => route('ib39.fea.documents.versions.download', [$fea, $document, $version]),
            ),
        ]);
    }

    public function assistance(Ib39SurfacedFormerRebel $ib39SurfacedFormerRebel): View
    {
        Gate::authorize('view', $ib39SurfacedFormerRebel);

        return view('japic.certifications.records.assistance', [
            'backUrl' => route('ib39.fr-profiles.show', $ib39SurfacedFormerRebel),
        ]);
    }

    public function certification(Ib39SurfacedFormerRebel $ib39SurfacedFormerRebel, SurfacedFrDocumentsRecordsService $documents): View
    {
        Gate::authorize('view', $ib39SurfacedFormerRebel);
        $ib39SurfacedFormerRebel->load('japicCertificationProcessing.currentFinalVersion');
        $data = $documents->certificationRecord($ib39SurfacedFormerRebel);
        $final = $data['finalCertification'];

        return view('japic.certifications.records.certification', $data + [
            'backUrl' => route('ib39.fr-profiles.show', $ib39SurfacedFormerRebel),
            'previewUrl' => $final ? route('ib39.japic-certifications.document-versions.preview', [$data['certification'], $final]) : null,
            'downloadUrl' => $final ? route('ib39.japic-certifications.document-versions.download', [$data['certification'], $final]) : null,
        ]);
    }

    public function store(
        StoreSurfacedFormerRebelRequest $request,
        Ib39SurfacedFormerRebelService $records,
    ): RedirectResponse {
        Gate::authorize('create', Ib39SurfacedFormerRebel::class);

        $record = $records->create(
            $request->validatedForCreation(),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('ib39.dashboard')
            ->with('success', "Surfaced FR {$record->reference_number} was recorded successfully.");
    }
}
