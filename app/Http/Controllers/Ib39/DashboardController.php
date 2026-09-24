<?php

namespace App\Http\Controllers\Ib39;

use App\Http\Controllers\Controller;
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
        ];

        return view('ib39.dashboard', compact('statusCounts', 'perMunicipality', 'stats'));
    }
}
