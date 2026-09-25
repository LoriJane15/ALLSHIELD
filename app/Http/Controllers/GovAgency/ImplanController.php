<?php

namespace App\Http\Controllers\GovAgency;

use App\Http\Controllers\Controller;
use App\Models\AgencyImplanResponse;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\ImplementationFile;
use App\Models\ImplementationPhoto;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Government-agency side of the IMPLAN workflow: view assigned plans,
 * accept/reject them, and maintain agenda files + documentation photos.
 */
class ImplanController extends Controller
{
    public function index(): View
    {
        $agencyId = auth()->user()->gov_agency_id;

        $assigned = Implementation::whereJsonContains('agencies', $agencyId)
            ->where('status', '!=', 'not yet started')
            ->latest()->get();

        $responses = AgencyImplanResponse::where('gov_agency_id', $agencyId)
            ->pluck('response_status', 'implementation_id');

        $grouped = [
            'pending' => $assigned->filter(fn ($i) => ($responses[$i->id] ?? 'pending') === 'pending'),
            'accepted' => $assigned->filter(fn ($i) => ($responses[$i->id] ?? null) === 'accepted'),
            'rejected' => $assigned->filter(fn ($i) => ($responses[$i->id] ?? null) === 'rejected'),
        ];

        $eligibleMunicipalityIds = DB::table('implementations')
            ->join('users', 'users.id', '=', 'implementations.lgu_user_id')
            ->whereJsonContains('implementations.agencies', $agencyId)
            ->where('implementations.status', '!=', 'not yet started')
            ->whereNotNull('users.municipality_id')
            ->distinct()
            ->pluck('users.municipality_id');

        $monitoringCounts = DB::table('implementations')
            ->join('users', 'users.id', '=', 'implementations.lgu_user_id')
            ->whereIn('users.municipality_id', $eligibleMunicipalityIds)
            ->where('implementations.status', '!=', 'not yet started')
            ->groupBy('users.municipality_id')
            ->selectRaw('users.municipality_id, COUNT(*) as total')
            ->pluck('total', 'municipality_id');

        $monitoringMunicipalities = Municipality::query()
            ->whereIn('id', $eligibleMunicipalityIds)
            ->orderBy('name')
            ->get();

        return view('gov_agency.implan.index', [
            'grouped' => $grouped,
            'agency' => auth()->user()->govAgency,
            'monitoringMunicipalities' => $monitoringMunicipalities,
            'monitoringCounts' => $monitoringCounts,
        ]);
    }

    public function municipality(Municipality $municipality): View
    {
        abort_unless($this->canMonitorMunicipality($municipality), 403);

        $implans = Implementation::query()
            ->whereHas('lguUser', fn ($query) => $query->where('municipality_id', $municipality->id))
            ->where('status', '!=', 'not yet started')
            ->with(['lguUser.municipality', 'responses.govAgency'])
            ->oldest('uploaded_at')
            ->oldest('id')
            ->get();

        $areaIds = $implans->flatMap(fn (Implementation $implan) => $implan->target_areas ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $areaNamesById = RcspBarangay::with('barangay')
            ->whereIn('id', $areaIds)
            ->get()
            ->mapWithKeys(fn ($area) => [
                $area->id => $area->barangay?->name ?? "Barangay #{$area->barangay_id}",
            ]);
        $agencyIds = $implans->flatMap(fn (Implementation $implan) => $implan->agencies ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return view('gov_agency.implan.municipality', [
            'municipality' => $municipality,
            'implans' => $implans,
            'agenciesById' => GovAgency::whereIn('id', $agencyIds)->get()->keyBy('id'),
            'areaNamesByImplan' => $implans->mapWithKeys(fn (Implementation $implan) => [
                $implan->id => collect($implan->target_areas ?? [])->map(
                    fn ($id) => $areaNamesById->get((int) $id, "Barangay #{$id}")
                ),
            ]),
        ]);
    }

    public function show(Implementation $implan): View
    {
        $this->authorizeAssigned($implan);
        $agencyId = auth()->user()->gov_agency_id;
        $implan->load([
            'originalFiles', 'originalPhotos',
            'responses.govAgency', 'responses.files', 'responses.photos',
        ]);
        $response = $implan->responses->firstWhere('gov_agency_id', (int) $agencyId);

        return view('gov_agency.implan.show', [
            'implan' => $implan,
            'response' => $response,
            'areaNames' => RcspBarangay::with('barangay')->whereIn('id', $implan->target_areas ?? [])
                ->get()->map(fn ($row) => $row->barangay?->name ?? "Barangay #{$row->barangay_id}"),
            'agenciesById' => GovAgency::all()->keyBy('id'),
        ]);
    }

    /** Accept or reject an assigned IMPLAN. */
    public function respond(Request $request, Implementation $implan): RedirectResponse
    {
        $this->authorizeAssigned($implan);

        $data = $request->validate([
            'response_status' => ['required', Rule::in(['accepted', 'rejected'])],
            'rejection_reason' => ['required_if:response_status,rejected', 'nullable', 'string'],
        ]);

        $agencyId = auth()->user()->gov_agency_id;

        DB::transaction(function () use ($implan, $agencyId, $data) {
            AgencyImplanResponse::updateOrCreate(
                ['gov_agency_id' => $agencyId, 'implementation_id' => $implan->id],
                [
                    'response_status' => $data['response_status'],
                    'rejection_reason' => $data['rejection_reason'] ?? null,
                ]
            );

            $implan->taggings()->updateOrCreate(
                ['gov_agency_id' => $agencyId],
                [
                    'status' => $data['response_status'] === 'accepted' ? 'Accepted' : 'Rejected',
                    'reason' => $data['rejection_reason'] ?? null,
                ]
            );

            // First acceptance moves the plan into implementation.
            if ($data['response_status'] === 'accepted' && $implan->status === 'submitted') {
                $implan->update(['status' => 'ongoing']);
            }
        });

        return back()->with('success', 'Response recorded.');
    }

    /** Store only the signed-in agency's reply; the LGU baseline is immutable here. */
    public function update(Request $request, Implementation $implan): RedirectResponse
    {
        $this->authorizeAssigned($implan);

        $data = $request->validate([
            'action_taken' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        AgencyImplanResponse::updateOrCreate(
            [
                'gov_agency_id' => auth()->user()->gov_agency_id,
                'implementation_id' => $implan->id,
            ],
            $data
        );

        return back()->with('success', 'Your agency response was saved.');
    }

    public function uploadAgenda(Request $request, Implementation $implan): RedirectResponse
    {
        $this->authorizeAssigned($implan);

        $request->validate([
            'file_name' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string'],
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:pdf,doc,docx', 'max:25600'],
        ]);

        $response = $this->responseFor($implan);

        foreach ($request->file('files') as $file) {
            $response->files()->create([
                'implementation_id' => $implan->id,
                'file_name' => $request->file_name,
                'description' => $request->description,
                'pdf' => $file->store('implan/agenda', 'public'),
            ]);
        }

        return back()->with('success', 'Agenda file(s) uploaded.');
    }

    public function uploadPhoto(Request $request, Implementation $implan): RedirectResponse
    {
        $this->authorizeAssigned($implan);

        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['image', 'max:25600'],
        ]);

        $response = $this->responseFor($implan);

        foreach ($request->file('photos') as $photo) {
            $response->photos()->create([
                'implementation_id' => $implan->id,
                'image' => $photo->store('implan/photos', 'public'),
            ]);
        }

        return back()->with('success', 'Documentation photo(s) uploaded.');
    }

    public function viewFile(Implementation $implan, ImplementationFile $file): StreamedResponse
    {
        $this->authorizeAssigned($implan);
        $this->authorizeAttachment($implan, $file->implementation_id, $file->agencyResponse);
        abort_unless($file->pdf && Storage::disk('public')->exists($file->pdf), 404);

        return Storage::disk('public')->response($file->pdf, $file->file_name ?: basename($file->pdf));
    }

    public function viewPhoto(Implementation $implan, ImplementationPhoto $photo): StreamedResponse
    {
        $this->authorizeAssigned($implan);
        $this->authorizeAttachment($implan, $photo->implementation_id, $photo->agencyResponse);
        abort_unless($photo->image && Storage::disk('public')->exists($photo->image), 404);

        return Storage::disk('public')->response($photo->image, basename($photo->image));
    }

    private function authorizeAssigned(Implementation $implan): void
    {
        $agencyId = auth()->user()->gov_agency_id;
        abort_if($implan->status === 'not yet started', 403, 'This IMPLAN has not been submitted.');
        abort_unless(
            in_array($agencyId, $implan->agencies ?? [], true),
            403,
            'This plan is not assigned to your agency.'
        );
    }

    private function canMonitorMunicipality(Municipality $municipality): bool
    {
        $agencyId = auth()->user()->gov_agency_id;

        return $agencyId && Implementation::query()
            ->whereJsonContains('agencies', $agencyId)
            ->where('status', '!=', 'not yet started')
            ->whereHas('lguUser', fn ($query) => $query->where('municipality_id', $municipality->id))
            ->exists();
    }

    private function responseFor(Implementation $implan): AgencyImplanResponse
    {
        return AgencyImplanResponse::firstOrCreate([
            'gov_agency_id' => auth()->user()->gov_agency_id,
            'implementation_id' => $implan->id,
        ]);
    }

    private function authorizeAttachment(
        Implementation $implan,
        int $implementationId,
        ?AgencyImplanResponse $agencyResponse
    ): void {
        abort_unless($implementationId === $implan->id, 404);

        if ($agencyResponse) {
            abort_unless(
                $agencyResponse->implementation_id === $implan->id
                    && in_array($agencyResponse->gov_agency_id, $implan->agencies ?? [], true),
                404
            );
        }
    }

}
