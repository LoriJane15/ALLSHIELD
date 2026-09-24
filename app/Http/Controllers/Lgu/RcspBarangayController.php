<?php

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\RcspBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RcspBarangayController extends Controller
{
    /** RCSP barangay evaluation list for the LGU's municipality. */
    public function index(): View
    {
        $muniId = auth()->user()->municipality_id;

        $rcspBarangays = RcspBarangay::query()
            ->with(['barangay', 'phaseStatus'])
            ->when($muniId, fn ($q) => $q->where('municipality_id', $muniId))
            ->when(request('search'), fn ($q, $s) => $q->whereHas('barangay', fn ($b) => $b->where('name', 'like', "%{$s}%")))
            ->orderByDesc('id')
            ->paginate(15)->withQueryString();

        // barangays in this municipality not yet added to RCSP
        $usedIds = RcspBarangay::when($muniId, fn ($q) => $q->where('municipality_id', $muniId))
            ->pluck('barangay_id');
        $available = Barangay::when($muniId, fn ($q) => $q->where('municipality_id', $muniId))
            ->whereNotIn('id', $usedIds)
            ->orderBy('name')->get();

        return view('lgu.rcsp.index', compact('rcspBarangays', 'available'));
    }

    public function store(): RedirectResponse
    {
        $muniId = auth()->user()->municipality_id;

        $data = request()->validate([
            'barangay_id' => [
                'required',
                // barangay must belong to this LGU's municipality
                function ($attr, $value, $fail) use ($muniId) {
                    if (! Barangay::where('id', $value)->where('municipality_id', $muniId)->exists()) {
                        $fail('Selected barangay is not in your municipality.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($data, $muniId) {
            $rcsp = RcspBarangay::create([
                'barangay_id' => $data['barangay_id'],
                'municipality_id' => $muniId,
                'status' => 'Pending',
                'current_phase' => 0,
            ]);
            $rcsp->phaseStatus()->create([]); // all phases default false
        });

        return back()->with('success', 'RCSP barangay added.');
    }

    public function destroy(RcspBarangay $rcspBarangay): RedirectResponse
    {
        $this->authorizeMunicipality($rcspBarangay);
        $rcspBarangay->delete();

        return back()->with('success', 'RCSP barangay removed.');
    }

    private function authorizeMunicipality(RcspBarangay $rcspBarangay): void
    {
        abort_unless(
            $rcspBarangay->municipality_id === auth()->user()->municipality_id,
            403,
            'This barangay is outside your municipality.'
        );
    }
}
