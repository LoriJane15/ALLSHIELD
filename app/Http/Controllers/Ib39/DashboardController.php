<?php

namespace App\Http\Controllers\Ib39;

use App\Http\Controllers\Controller;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\MapBarangay;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $statusCounts = MapBarangay::select('status', DB::raw('COUNT(*) as count'))
            ->whereNotNull('status')->where('status', '!=', '')
            ->groupBy('status')->pluck('count', 'status');

        $perMunicipality = MapBarangay::select('municipality', DB::raw('SUM(frs) as frs'))
            ->groupBy('municipality')
            ->having('frs', '>', 0)
            ->orderByDesc('frs')->get();

        $stats = [
            'mapped' => MapBarangay::count(),
            'total_frs' => (int) MapBarangay::sum('frs'),
            'active_areas' => MapBarangay::where('frs', '>', 0)->count(),
            'cleared_areas' => (int) ($statusCounts['Recovery'] ?? 0),
            'threat_areas' => (int) (($statusCounts['Konsolidado'] ?? 0) + ($statusCounts['Rekonsilida'] ?? 0) + ($statusCounts['Expansion'] ?? 0)),
            'total_surfaced_frs' => Ib39SurfacedFormerRebel::count(),
        ];

        $recentSurfaced = Ib39SurfacedFormerRebel::with(['municipality', 'barangay'])
            ->latest('surfaced_at')
            ->take(5)
            ->get();

        $priorityAreas = MapBarangay::orderByDesc('frs')
            ->take(6)
            ->get();

        $needsAttention = [
            'pending_cdrs' => \App\Models\Ib39CdrProcessing::whereIn('status', [\App\Enums\Ib39CdrStatus::Pending, \App\Enums\Ib39CdrStatus::Ongoing])->count(),
            'pending_feas' => \App\Models\Ib39FeaProcessing::whereHas('documents', function ($query) {
                $query->whereIn('status', [\App\Enums\Ib39FeaDocumentStatus::Pending, \App\Enums\Ib39FeaDocumentStatus::Processing]);
            })->count(),
            'unclassified_areas' => MapBarangay::where(function ($q) {
                $q->whereNull('status')->orWhere('status', '');
            })->count(),
            'high_threat_areas' => (int) ($statusCounts['Konsolidado'] ?? 0),
        ];

        return view('ib39.dashboard', compact('statusCounts', 'perMunicipality', 'stats', 'recentSurfaced', 'priorityAreas', 'needsAttention'));
    }
}

