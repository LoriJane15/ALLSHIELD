<?php

namespace App\Http\Controllers\GovAgency;

use App\Http\Controllers\Controller;
use App\Models\AgencyImplanResponse;
use App\Models\Implementation;
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
 * accept/reject them, and maintain documentation photos.
 */
class ImplanController extends Controller
{
    public function index(): View
    {
        $agencyId = $this->agencyId();

        $assigned = Implementation::visibleToAgency($agencyId)
            ->with('lguUser.municipality')
            ->latest()->get();

        $responses = AgencyImplanResponse::where('gov_agency_id', $agencyId)
            ->pluck('response_status', 'implementation_id');

        $grouped = [
            'pending' => $assigned->filter(fn ($i) => ($responses[$i->id] ?? 'pending') === 'pending'),
            'accepted' => $assigned->filter(fn ($i) => ($responses[$i->id] ?? null) === 'accepted'),
            'rejected' => $assigned->filter(fn ($i) => ($responses[$i->id] ?? null) === 'rejected'),
        ];

        $monitoringCounts = $assigned
            ->filter(fn (Implementation $implan) => $implan->lguUser?->municipality_id)
            ->countBy(fn (Implementation $implan) => $implan->lguUser->municipality_id);

        $monitoringMunicipalities = Municipality::query()
            ->whereIn('id', $monitoringCounts->keys())
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
        $agencyId = $this->agencyId();
        abort_unless($this->canMonitorMunicipality($municipality, $agencyId), 403);

        $implans = Implementation::visibleToAgency($agencyId)
            ->forMunicipality($municipality->id)
            ->with([
                'lguUser.municipality',
                'responses' => fn ($query) => $query->where('gov_agency_id', $agencyId)->with('govAgency'),
            ])
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

        return view('gov_agency.implan.municipality', [
            'municipality' => $municipality,
            'implans' => $implans,
            'agencyScopeId' => $agencyId,
            'agenciesById' => collect([$agencyId => auth()->user()->govAgency]),
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
        $agencyId = $this->agencyId();
        $implan->load([
            'lguUser.municipality',
            'responses' => fn ($query) => $query->where('gov_agency_id', $agencyId)
                ->with(['govAgency', 'photos']),
        ]);
        $response = $implan->responses->first();

        return view('gov_agency.implan.show', [
            'implan' => $implan,
            'response' => $response,
            'areaNames' => RcspBarangay::with('barangay')->whereIn('id', $implan->target_areas ?? [])
                ->get()->map(fn ($row) => $row->barangay?->name ?? "Barangay #{$row->barangay_id}"),
            'agencyScopeId' => $agencyId,
            'currentAgency' => auth()->user()->govAgency,
            'agenciesById' => collect([$agencyId => auth()->user()->govAgency]),
            'isSharedAction' => count($implan->agencies ?? []) > 1,
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

    public function viewPhoto(Implementation $implan, ImplementationPhoto $photo): StreamedResponse
    {
        $this->authorizeAssigned($implan);
        $this->authorizeAttachment($implan, $photo->implementation_id, $photo->agencyResponse);
        abort_unless($photo->image && Storage::disk('public')->exists($photo->image), 404);

        return Storage::disk('public')->response($photo->image, basename($photo->image));
    }

    private function authorizeAssigned(Implementation $implan): void
    {
        $agencyId = $this->agencyId();
        abort_if($implan->status === 'not yet started', 403, 'This IMPLAN has not been submitted.');
        abort_unless(
            $implan->isAssignedToAgency($agencyId),
            403,
            'This plan is not assigned to your agency.'
        );
    }

    private function canMonitorMunicipality(Municipality $municipality, int $agencyId): bool
    {
        return Implementation::visibleToAgency($agencyId)
            ->forMunicipality($municipality->id)
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
        abort_unless(
            $agencyResponse
                && $agencyResponse->implementation_id === $implan->id
                && $agencyResponse->gov_agency_id === $this->agencyId(),
            404
        );
    }

    private function agencyId(): int
    {
        $agencyId = auth()->user()->gov_agency_id;
        abort_unless($agencyId, 403, 'No government agency is linked to this account.');

        return (int) $agencyId;
    }
}
