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
            'photos.agencyResponse.govAgency',
            'responses.govAgency', 'responses.photos',
        ]);

        return view('lgu.implan.show', [
            'implan' => $implan,
            'areaNames' => $this->areaNames($implan->target_areas ?? []),
            'agencyNames' => GovAgency::whereIn('id', $implan->agencies ?? [])->pluck('acronym'),
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

    public function official(): View
    {
        $municipality = auth()->user()->municipality;
        abort_unless($municipality, 404, 'No municipality is assigned to this LGU account.');

        $implans = $this->submittedMunicipalityImplans($municipality);

        return view('lgu.implan.official', $this->officialViewData($municipality, $implans));
    }

    public function download(ImplanWorkbookExporter $exporter): StreamedResponse
    {
        $municipality = auth()->user()->municipality;
        abort_unless($municipality, 404, 'No municipality is assigned to this LGU account.');

        return $exporter->download($municipality, $this->submittedMunicipalityImplans($municipality));
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

    private function submittedMunicipalityImplans(Municipality $municipality): EloquentCollection
    {
        return Implementation::query()
            ->whereHas('lguUser', fn ($query) => $query->where('municipality_id', $municipality->id))
            ->where('status', '!=', 'not yet started')
            ->with([
                'lguUser.municipality',
                'responses.govAgency', 'responses.photos',
            ])
            ->oldest('uploaded_at')
            ->oldest('id')
            ->get();
    }

    private function officialViewData(Municipality $municipality, EloquentCollection $implans): array
    {
        return [
            'municipality' => $municipality,
            'implans' => $implans,
            'agenciesById' => GovAgency::all()->keyBy('id'),
            'areaNamesByImplan' => $implans->mapWithKeys(fn (Implementation $implan) => [
                $implan->id => $this->areaNames($implan->target_areas ?? []),
            ]),
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
