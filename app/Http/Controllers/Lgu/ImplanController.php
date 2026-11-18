<?php

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Controller;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\ImplementationPhoto;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use App\Services\ImplanWorkbookExporter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImplanController extends Controller
{
    public function index(): View
    {
        $municipalityId = $this->municipalityId();
        $municipalImplans = Implementation::forMunicipality($municipalityId);
        $implans = (clone $municipalImplans)
            ->with(['lguUser', 'responses.govAgency', 'responses.photos'])
            ->latest()
            ->paginate(12);

        $implans->getCollection()->each(function (Implementation $implan): void {
            $assignedAgencyIds = collect($implan->agencies ?? [])->map(fn ($id) => (int) $id);
            $submittedAgencyCount = $implan->responses
                ->whereIn('gov_agency_id', $assignedAgencyIds)
                ->filter(fn ($response) => $response->photos->isNotEmpty())
                ->count();

            $implan->setAttribute('documentation_submitted_count', $submittedAgencyCount);
            $implan->setAttribute('responsible_agency_count', $assignedAgencyIds->count());
            $implan->setAttribute('can_manage', $implan->lgu_user_id === auth()->id());
        });

        $counts = [
            'total' => (clone $municipalImplans)->count(),
            'not_started' => (clone $municipalImplans)->where('status', 'not yet started')->count(),
            'submitted' => (clone $municipalImplans)->where('status', 'submitted')->count(),
            'ongoing' => (clone $municipalImplans)->where('status', 'ongoing')->count(),
            'verified' => (clone $municipalImplans)->where('status', 'verified')->count(),
        ];

        $areaIds = $implans->getCollection()->flatMap(fn (Implementation $implan) => $implan->target_areas ?? [])
            ->map(fn ($id) => (int) $id)->unique();
        $areaNamesById = RcspBarangay::with('barangay')
            ->where('municipality_id', $municipalityId)
            ->whereIn('id', $areaIds)
            ->get()
            ->mapWithKeys(fn ($area) => [$area->id => $area->barangay?->name ?? "Barangay #{$area->barangay_id}"]);

        return view('lgu.implan.index', [
            'implans' => $implans,
            'counts' => $counts,
            'targetAreas' => $this->targetAreas(),
            'agencies' => GovAgency::orderBy('acronym')->get(),
            'agenciesById' => GovAgency::orderBy('acronym')->get()->keyBy('id'),
            'areaNamesByImplan' => $implans->getCollection()->mapWithKeys(fn (Implementation $implan) => [
                $implan->id => collect($implan->target_areas ?? [])
                    ->map(fn ($id) => $areaNamesById->get((int) $id, "Barangay #{$id}")),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateImplan($request);

        Implementation::create([
            'lgu_user_id' => auth()->id(),
            'uploaded_at' => now()->toDateString(),
            'issues' => $data['issues'],
            'target_areas' => array_map('intval', $data['target_areas'] ?? []),
            'agencies' => array_map('intval', $data['agencies'] ?? []),
            'status' => 'not yet started',
        ]);

        return back()->with('success', 'Implementation plan created.');
    }

    public function show(Implementation $implan): View
    {
        $this->authorizeMunicipality($implan);
        $implan->load([
            'responses.govAgency', 'responses.photos',
        ]);

        return view('lgu.implan.show', [
            'implan' => $implan,
            'areaNames' => $this->areaNames($implan->target_areas ?? []),
            'agenciesById' => GovAgency::all()->keyBy('id'),
            'canManage' => $implan->lgu_user_id === auth()->id(),
        ]);
    }

    public function viewPhoto(Implementation $implan, ImplementationPhoto $photo): StreamedResponse
    {
        $this->authorizeMunicipality($implan);
        abort_unless($photo->implementation_id === $implan->id, 404);
        abort_unless($photo->image && Storage::disk('public')->exists($photo->image), 404);

        return Storage::disk('public')->response($photo->image, basename($photo->image));
    }

    public function official(Request $request): View
    {
        $municipality = auth()->user()->municipality;
        abort_unless($municipality, 404, 'No municipality is assigned to this LGU account.');

        if ($request->hasAny(['implan', 'agency'])) {
            $data = $request->validate([
                'implan' => ['required', 'integer'],
                'agency' => ['required', 'integer'],
                'print' => ['nullable', 'boolean'],
            ]);

            $implan = Implementation::findOrFail($data['implan']);
            $this->authorizeMunicipality($implan);
            abort_unless($implan->isAssignedToAgency((int) $data['agency']), 403);

            $agency = GovAgency::findOrFail($data['agency']);
            $implan->load([
                'lguUser.municipality',
                'responses' => fn ($query) => $query
                    ->where('gov_agency_id', $agency->id)
                    ->with('govAgency'),
            ]);

            return view('lgu.implan.official', [
                'municipality' => $municipality,
                'implans' => collect([$implan]),
                'agenciesById' => collect([$agency->id => $agency]),
                'areaNamesByImplan' => collect([$implan->id => $this->areaNames($implan->target_areas ?? [])]),
                'agencyScopeId' => $agency->id,
                'showAgencyResponseAttribution' => false,
                'emptyResponseValue' => 'Not yet submitted',
            ]);
        }

        $implans = $this->submittedMunicipalityImplans($municipality, includeResponses: false);

        return view('lgu.implan.official', $this->officialViewData($municipality, $implans));
    }

    public function download(Request $request, ImplanWorkbookExporter $exporter): StreamedResponse
    {
        $municipality = auth()->user()->municipality;
        abort_unless($municipality, 404, 'No municipality is assigned to this LGU account.');

        $data = $request->validate([
            'agency' => ['required', 'integer', 'exists:gov_agencies,id'],
        ]);
        $agency = GovAgency::findOrFail($data['agency']);
        $implans = $this->submittedMunicipalityImplans($municipality, $agency->id);

        abort_if($implans->isEmpty(), 403);

        return $exporter->download($municipality, $implans, $agency);
    }

    public function update(Request $request, Implementation $implan): RedirectResponse
    {
        $this->authorizeOwner($implan);
        $data = $this->validateImplan($request);

        $implan->update([
            'issues' => $data['issues'],
            'target_areas' => array_map('intval', $data['target_areas'] ?? []),
            'agencies' => array_map('intval', $data['agencies'] ?? []),
        ]);

        return back()->with('success', 'Implementation plan updated.');
    }

    /** Beneficiaries / outcome / resources / support / duration / program. */
    public function updateImplementation(Request $request, Implementation $implan): RedirectResponse
    {
        $this->authorizeOwner($implan);
        $implan->update($request->validate([
            'program' => ['nullable', 'string'],
            'beneficiaries' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
            'resources' => ['nullable', 'string'],
            'support' => ['nullable', 'string'],
            'duration' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Implementation details saved.');
    }

    public function submit(Implementation $implan): RedirectResponse
    {
        $this->authorizeOwner($implan);
        abort_if(empty($implan->agencies), 422, 'Select at least one responsible agency before submitting.');

        $implan->update(['status' => 'submitted']);

        return back()->with('success', 'IMPLAN submitted directly to the responsible agencies.');
    }

    public function destroy(Implementation $implan): RedirectResponse
    {
        $this->authorizeOwner($implan);
        $implan->delete();

        return redirect()->route('lgu.implan.index')->with('success', 'Implementation plan deleted.');
    }

    // Helpers -------------------------------------------------------------
    private function validateImplan(Request $request): array
    {
        return $request->validate([
            'issues' => ['required', 'string'],
            'target_areas' => ['nullable', 'array'],
            'target_areas.*' => [
                'integer',
                Rule::exists('rcsp_barangays', 'id')->where(
                    fn ($query) => $query->where('municipality_id', $this->municipalityId())
                ),
            ],
            'beneficiaries' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
            'agencies' => ['nullable', 'array'],
            'agencies.*' => ['integer', 'distinct', 'exists:gov_agencies,id'],
        ]);
    }

    private function targetAreas(): Collection
    {
        return RcspBarangay::with('barangay')
            ->when(auth()->user()->municipality_id, fn ($q) => $q->where('municipality_id', auth()->user()->municipality_id))
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->barangay?->name ?? "Barangay #{$r->barangay_id}"]);
    }

    private function areaNames(array $ids): Collection
    {
        return RcspBarangay::with('barangay')
            ->where('municipality_id', $this->municipalityId())
            ->whereIn('id', $ids)->get()
            ->map(fn ($r) => $r->barangay?->name ?? "Barangay #{$r->barangay_id}");
    }

    private function submittedMunicipalityImplans(
        Municipality $municipality,
        ?int $agencyId = null,
        bool $includeResponses = true
    ): EloquentCollection {
        $relations = ['lguUser.municipality'];
        if ($includeResponses) {
            $relations['responses'] = fn ($query) => $query
                ->when($agencyId !== null, fn ($responseQuery) => $responseQuery->where('gov_agency_id', $agencyId))
                ->with('govAgency');
        }

        return Implementation::query()
            ->whereHas('lguUser', fn ($query) => $query->where('municipality_id', $municipality->id))
            ->where('status', '!=', 'not yet started')
            ->when($agencyId !== null, fn ($query) => $query->assignedToAgency($agencyId))
            ->with($relations)
            ->oldest('uploaded_at')
            ->oldest('id')
            ->get();
    }

    private function officialViewData(Municipality $municipality, EloquentCollection $implans): array
    {
        $assignedAgencyIds = $implans
            ->flatMap(fn (Implementation $implan) => collect($implan->agencies ?? [])->map(fn ($id) => (int) $id))
            ->filter()
            ->unique()
            ->values();
        $agenciesById = GovAgency::query()->whereIn('id', $assignedAgencyIds)->get()->keyBy('id');
        $areaNamesByImplan = $implans->mapWithKeys(fn (Implementation $implan) => [
            $implan->id => $this->areaNames($implan->target_areas ?? []),
        ]);
        $agencyTabs = $assignedAgencyIds
            ->map(function (int $agencyId) use ($agenciesById, $areaNamesByImplan, $municipality): ?array {
                $agency = $agenciesById->get($agencyId);
                if (! $agency) {
                    return null;
                }

                return [
                    'agency' => $agency,
                    'implans' => $this->submittedMunicipalityImplans($municipality, $agencyId),
                    'areaNamesByImplan' => $areaNamesByImplan,
                ];
            })
            ->filter()
            ->values();

        return [
            'municipality' => $municipality,
            'agencyTabs' => $agencyTabs,
            'agenciesById' => $agenciesById,
        ];
    }

    private function authorizeOwner(Implementation $implan): void
    {
        abort_unless($implan->lgu_user_id === auth()->id(), 403);
    }

    private function authorizeMunicipality(Implementation $implan): void
    {
        $ownerMunicipalityId = (int) $implan->lguUser()->value('municipality_id');
        abort_unless($ownerMunicipalityId === $this->municipalityId(), 403);
    }

    private function municipalityId(): int
    {
        $municipalityId = auth()->user()->municipality_id;
        abort_unless($municipalityId, 403, 'No municipality is assigned to this LGU account.');

        return (int) $municipalityId;
    }
}
