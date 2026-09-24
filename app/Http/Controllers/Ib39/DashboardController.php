<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Ib39CdrProcessing;
use App\Models\Ib39FeaProcessing;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\MapBarangay;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $areas = MapBarangay::query()
            ->with(['barangayRecord.municipality', 'latestColorHistory'])
            ->whereNotNull('status')
            ->whereHas('colorHistories')
            ->get();

        $statusCounts = $areas->countBy(fn (MapBarangay $area) => $area->latestColorHistory?->status)
            ->filter(fn (int $count, ?string $status) => $status !== null && $status !== '');

        $perMunicipality = $areas
            ->groupBy(fn (MapBarangay $area) => $area->barangayRecord?->municipality?->name ?? $area->municipality)
            ->map(fn ($municipalityAreas, $municipality) => (object) [
                'municipality' => $municipality,
                'frs' => $municipalityAreas->sum(fn (MapBarangay $area) => $area->latestColorHistory?->frs ?? 0),
            ])
            ->filter(fn ($row) => $row->frs > 0)
            ->sortByDesc('frs')
            ->values();

        $stats = [
            'mapped' => $areas->count(),
            'total_frs' => (int) $areas->sum(fn (MapBarangay $area) => $area->latestColorHistory?->frs ?? 0),
            'active_areas' => $areas->count(),
            'cleared_areas' => (int) ($statusCounts['Recovery'] ?? 0),
            'threat_areas' => (int) $statusCounts
                ->except('Recovery')
                ->sum(),
            'total_surfaced_frs' => Ib39SurfacedFormerRebel::count(),
        ];

        $recentSurfaced = Ib39SurfacedFormerRebel::with(['municipality', 'barangay'])
            ->latest('surfaced_at')
            ->take(5)
            ->get();

        $priorityAreas = $areas
            ->sortByDesc(fn (MapBarangay $area) => $area->latestColorHistory?->frs ?? 0)
            ->take(6)
            ->values();

        $threatBreakdown = $areas
            ->groupBy(fn (MapBarangay $area) => $area->latestColorHistory?->status);

        $needsAttention = [
            'pending_cdrs' => Ib39CdrProcessing::whereIn('status', [Ib39CdrStatus::Pending, Ib39CdrStatus::Ongoing])->count(),
            'pending_feas' => Ib39FeaProcessing::whereHas('documents', function ($query) {
                $query->whereIn('status', [Ib39FeaDocumentStatus::Pending, Ib39FeaDocumentStatus::Processing]);
            })->count(),
            'unclassified_areas' => MapBarangay::where(function ($query) {
                $query->whereNull('status')->orWhere('status', '');
            })->count(),
            'high_threat_areas' => (int) ($statusCounts['Konsolidado'] ?? 0),
        ];

        return view('ib39.dashboard', [
            'statusCounts' => $statusCounts,
            'perMunicipality' => $perMunicipality,
            'stats' => $stats,
            'classifications' => MapBarangay::CLASSIFICATIONS,
            'recentSurfaced' => $recentSurfaced,
            'priorityAreas' => $priorityAreas,
            'threatBreakdown' => $threatBreakdown,
            'needsAttention' => $needsAttention,
        ]);
    }
}
