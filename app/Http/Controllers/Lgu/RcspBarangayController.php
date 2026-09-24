<?php

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rcsp\StoreRcspBarangayRequest;
use App\Models\Barangay;
use App\Models\RcspBarangay;
use App\Services\RcspWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RcspBarangayController extends Controller
{
    /** RCSP barangay evaluation list for the LGU's municipality. */
    public function index(): View
    {
        Gate::authorize('viewAny', RcspBarangay::class);
        $muniId = auth()->user()->municipality_id;

        $rcspBarangays = RcspBarangay::query()
            ->with(['barangay', 'municipality', 'phaseStatus'])
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

    public function store(StoreRcspBarangayRequest $request, RcspWorkflowService $workflow): RedirectResponse
    {
        $workflow->createBarangay($request->integer('barangay_id'), $request->user());

        return back()->with('success', 'RCSP barangay registered.');
    }

    public function destroy(RcspBarangay $rcspBarangay): RedirectResponse
    {
        Gate::authorize('delete', $rcspBarangay);
        $rcspBarangay->delete();

        return back()->with('success', 'RCSP barangay removed.');
    }
}
