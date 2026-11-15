<?php

namespace App\Http\Controllers\GovAgency;

use App\Http\Controllers\Controller;
use App\Models\AgencyImplanResponse;
use App\Models\GovAgency;
use App\Models\Implementation;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $agencyId = (int) auth()->user()->gov_agency_id;
        abort_unless($agencyId, 403, 'No government agency is linked to this account.');

        $assigned = Implementation::visibleToAgency($agencyId);
        $assignedIds = (clone $assigned)->pluck('id');

        $responseStatuses = AgencyImplanResponse::where('gov_agency_id', $agencyId)
            ->whereIn('implementation_id', $assignedIds)
            ->pluck('response_status', 'implementation_id');
        $acceptedIds = $responseStatuses->filter(fn ($status) => $status === 'accepted')->keys();
        $rejectedIds = $responseStatuses->filter(fn ($status) => $status === 'rejected')->keys();

        $stats = [
            'assigned' => $assignedIds->count(),
            'accepted' => $acceptedIds->count(),
            'pending' => $assignedIds->diff($acceptedIds)->diff($rejectedIds)->count(),
            'rejected' => $rejectedIds->count(),
        ];

        $accepted = Implementation::whereIn('id', $acceptedIds)
            ->latest()->take(10)->get();
        $responsibleAgencyIds = $accepted
            ->flatMap(fn (Implementation $implan) => $implan->agencies ?? [])
            ->map(fn ($responsibleAgencyId) => (int) $responsibleAgencyId)
            ->unique()
            ->values();

        return view('gov_agency.dashboard', [
            'stats' => $stats,
            'accepted' => $accepted,
            'agency' => auth()->user()->govAgency,
            'responseStatuses' => $responseStatuses,
            'agenciesById' => GovAgency::query()
                ->whereIn('id', $responsibleAgencyIds)
                ->get()
                ->keyBy('id'),
        ]);
    }
}
