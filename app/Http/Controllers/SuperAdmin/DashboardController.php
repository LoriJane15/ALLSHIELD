<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $rcsp = [
            'total' => RcspBarangay::count(),
            'not_started' => RcspBarangay::whereIn('status', ['Pending', 'Not Started'])->count(),
            'ongoing' => RcspBarangay::where('status', 'Ongoing')->count(),
            'completed' => RcspBarangay::where('status', 'Completed')->count(),
        ];

        // RCSP barangays per municipality — analytics bar chart.
        $rcspByMunicipality = RcspBarangay::with('municipality')->get()
            ->groupBy(fn ($b) => $b->municipality?->name ?? '—')
            ->map->count();

        // Recent form activity — document overview table.
        $recentDocuments = RcspForm::with(['rcspBarangay.barangay', 'rcspBarangay.municipality', 'phase'])
            ->latest()->take(10)->get();

        $systemUsersCount = \App\Models\User::count();
        $govAgenciesCount = \App\Models\GovAgency::count();
        $totalMunicipalitiesCount = $rcspByMunicipality->count();

        $userFullname = auth()->user()->name;

        return view('super_admin.dashboard', compact(
            'rcsp',
            'rcspByMunicipality',
            'recentDocuments',
            'userFullname',
            'systemUsersCount',
            'govAgenciesCount',
            'totalMunicipalitiesCount'
        ));
    }
}
