<?php

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Controller;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use App\Services\ImplanWorkbookExporter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImplanController extends Controller
{
    public function index(): View
    {
        $implans = Implementation::where('lgu_user_id', auth()->id())
            ->latest()->paginate(12);

        $counts = [
            'total' => Implementation::where('lgu_user_id', auth()->id())->count(),
            'not_started' => Implementation::where('lgu_user_id', auth()->id())->where('status', 'not yet started')->count(),
            'submitted' => Implementation::where('lgu_user_id', auth()->id())->where('status', 'submitted')->count(),
            'ongoing' => Implementation::where('lgu_user_id', auth()->id())->where('status', 'ongoing')->count(),
            'verified' => Implementation::where('lgu_user_id', auth()->id())->where('status', 'verified')->count(),
        ];

        return view('lgu.implan.index', [
            'implans' => $implans,
            'counts' => $counts,
            'targetAreas' => $this->targetAreas(),
            'agencies' => GovAgency::orderBy('acronym')->get(),
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
        $this->authorizeOwner($implan);
        $implan->load([
            'files.agencyResponse.govAgency',
            'photos.agencyResponse.govAgency',
            'responses.govAgency', 'responses.files', 'responses.photos',
        ]);

        return view('lgu.implan.show', [
            'implan' => $implan,
            'areaNames' => $this->areaNames($implan->target_areas ?? []),
            'agencyNames' => GovAgency::whereIn('id', $implan->agencies ?? [])->pluck('acronym'),
            'agenciesById' => GovAgency::all()->keyBy('id'),
        ]);
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

    public function uploadAgenda(Request $request, Implementation $implan): RedirectResponse
    {
        $this->authorizeOwner($implan);
        $request->validate([
            'file_name' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string'],
            'pdf' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
        ]);

        $path = $request->file('pdf')->store('implan/agenda', 'public');
        $implan->originalFiles()->create([
            'file_name' => $request->file_name,
            'description' => $request->description,
            'pdf' => $path,
        ]);

        return back()->with('success', 'Agenda file uploaded.');
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
            'target_areas.*' => ['integer', 'exists:rcsp_barangays,id'],
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
        return RcspBarangay::with('barangay')->whereIn('id', $ids)->get()
            ->map(fn ($r) => $r->barangay?->name ?? "Barangay #{$r->barangay_id}");
    }

    private function submittedMunicipalityImplans(Municipality $municipality): EloquentCollection
    {
        return Implementation::query()
            ->whereHas('lguUser', fn ($query) => $query->where('municipality_id', $municipality->id))
            ->where('status', '!=', 'not yet started')
            ->with([
                'lguUser.municipality',
                'responses.govAgency', 'responses.files', 'responses.photos',
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

}
