<?php

namespace App\Http\Controllers\Mblrc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mblrc\StoreFormerRebelRequest;
use App\Http\Requests\Mblrc\UpdateFormerRebelRequest;
use App\Models\Barangay;
use App\Models\FormerRebel;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FormerRebelController extends Controller
{
    /** Monitoring list with search + status filter. */
    public function index(Request $request): View
    {
        $frs = FormerRebel::query()
            ->with(['barangay', 'municipality', 'programStatus'])
            ->when($request->search, function ($q, $search) {
                $q->where(fn ($w) => $w
                    ->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('classified_id', 'like', "%{$search}%"));
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('mblrc.fr.index', [
            'frs' => $frs,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        return view('mblrc.fr.create', [
            'municipalities' => Municipality::orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreFormerRebelRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['classified_id'] = FormerRebel::nextClassifiedId();
        $data['province'] ??= 'Davao del Sur';
        $data['status'] ??= 'Active';
        $data['registered_at'] = now();
        $data['age'] = isset($data['birthdate'])
            ? Carbon::parse($data['birthdate'])->age
            : null;
        $data['contact_num'] = $this->cleanContact($data['contact_num'] ?? null);

        $fr = FormerRebel::create($data);

        return redirect()->route('mblrc.fr.show', $fr)
            ->with('success', "Former Rebel {$fr->classified_id} registered.");
    }

    public function show(FormerRebel $formerRebel): View
    {
        $formerRebel->load([
            'barangay', 'municipality', 'programStatus',
            'skills', 'assistances', 'educationWorks', 'locationHistories',
        ]);

        return view('mblrc.fr.show', [
            'fr' => $formerRebel,
            'education' => $formerRebel->educationWorks->sortByDesc('updated_at')->first(),
        ]);
    }

    public function edit(FormerRebel $formerRebel): View
    {
        return view('mblrc.fr.edit', [
            'fr' => $formerRebel,
            'municipalities' => Municipality::orderBy('name')->get(),
            'barangays' => Barangay::where('municipality_id', $formerRebel->municipality_id)->orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateFormerRebelRequest $request, FormerRebel $formerRebel): RedirectResponse
    {
        $data = $request->validated();
        $data['age'] = isset($data['birthdate'])
            ? Carbon::parse($data['birthdate'])->age
            : null;
        $data['contact_num'] = $this->cleanContact($data['contact_num'] ?? null);
        $formerRebel->update($data);

        return redirect()->route('mblrc.fr.show', $formerRebel)
            ->with('success', 'Profile updated.');
    }

    public function destroy(FormerRebel $formerRebel): RedirectResponse
    {
        $id = $formerRebel->classified_id;
        $formerRebel->delete(); // cascades to program status, skills, etc.

        return redirect()->route('mblrc.fr.index')
            ->with('success', "Former Rebel {$id} deleted.");
    }

    /** Map markers — all FRs with coordinates. */
    public function locations(): JsonResponse
    {
        $rows = FormerRebel::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['id', 'firstname', 'lastname', 'placement_address', 'latitude', 'longitude', 'status', 'batch_year', 'gender', 'occupation'])
            ->map(fn ($fr) => [
                'id' => $fr->id,
                'name' => trim("{$fr->firstname} {$fr->lastname}"),
                'address' => $fr->placement_address,
                'lat' => (float) $fr->latitude,
                'lng' => (float) $fr->longitude,
                'status' => $fr->status,
                'batch' => $fr->batch_year,
                'occupation' => $fr->occupation,
                'url' => route('mblrc.fr.show', $fr->id),
            ]);

        return response()->json($rows);
    }

    /** Cascade: barangays for a municipality. */
    public function barangays(Request $request): JsonResponse
    {
        $request->validate(['municipality_id' => 'required|exists:municipalities,id']);

        return response()->json(
            Barangay::where('municipality_id', $request->municipality_id)
                ->orderBy('name')->get(['id', 'name'])
        );
    }

    private function cleanContact(?string $v): ?string
    {
        return $v ? preg_replace('/[^0-9+]/', '', $v) : null;
    }

    private function statuses(): array
    {
        return [
            'Active', 'On hold', 'Reintegrated', 'Inactive', 'Under Review',
            'Disengaged', 'Pending', 'Suspended', 'Completed', 'Deceased', 'Relocated',
        ];
    }
}
