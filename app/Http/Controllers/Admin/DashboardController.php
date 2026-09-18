<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormerRebel;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\MapBarangay;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Municipality name => LGU seal image in public/assets/LGUS. */
    private array $seals = [
        'Digos City' => 'digos.png', 'Digos' => 'digos.png',
        'Kiblawan' => 'kiblawan.png', 'Magsaysay' => 'magsaysay.png',
        'Malalag' => 'malalag.png', 'Hagonoy' => 'hagonoy.png',
        'Bansalan' => 'bansalan.png', 'Matanao' => 'matanao.png',
        'Padada' => 'padada.jpg', 'Sulop' => 'sulop.png',
        'Santa Cruz' => 'sta cruz.png', 'Sta. Cruz' => 'sta cruz.png', 'Sta Cruz' => 'sta cruz.png',
    ];

    public function index(): View
    {
        $stats = [
            'former_rebels' => FormerRebel::count(),
            'rcsp_barangays' => RcspBarangay::count(),
            'pending_forms' => RcspForm::where('status', 'submitted')->distinct('rcsp_barangay_id')->count('rcsp_barangay_id'),
            'for_verification' => Implementation::where('status', 'for verification')->count(),
            'agencies' => GovAgency::count(),
            'municipalities' => Municipality::count(),
        ];

        // Per-municipality RCSP breakdown for the cards and the progress chart.
        // The legacy dashboard hardcoded these numbers; here they are real.
        $municipalities = Municipality::orderBy('name')->get()->map(function ($m) {
            $name = trim($m->name);
            $total = RcspBarangay::where('municipality_id', $m->id)->count();
            $completed = RcspBarangay::where('municipality_id', $m->id)->where('status', 'Completed')->count();

            return [
                'name' => $name,
                // Digos is the province's only component city; the rest are municipalities.
                'kind' => str_contains(strtolower($name), 'digos') ? 'Component City' : 'Municipality',
                'total' => $total,
                'recognized' => $completed,
                'in_progress' => $total - $completed,
                'seal' => $this->seals[$name] ?? null,
            ];
        });

        // Former-rebel points for the operational map.
        $frPoints = FormerRebel::whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['firstname', 'lastname', 'latitude', 'longitude', 'status', 'placement_address'])
            ->map(fn ($fr) => [
                'name' => trim("{$fr->firstname} {$fr->lastname}"),
                'lat' => (float) $fr->latitude,
                'lng' => (float) $fr->longitude,
                'status' => $fr->status,
                'address' => $fr->placement_address,
            ]);

        return view('admin.dashboard', compact('stats', 'municipalities', 'frPoints'));
    }

    /**
     * Barangay status for the dashboard hero map, keyed "Municipality|Barangay"
     * to match public/assets/mapping/barangays.geojson. Same shape as the
     * 39th-IB endpoint, exposed here so the Katuparan role need not be granted
     * access to that area.
     */
    public function areaData(): JsonResponse
    {
        $rows = MapBarangay::get(['id', 'municipality', 'barangay', 'frs', 'status', 'infestation_color'])
            ->mapWithKeys(fn ($a) => [
                "{$a->municipality}|{$a->barangay}" => [
                    'frs' => (int) $a->frs,
                    'status' => $a->status,
                    'color' => $a->infestation_color,
                ],
            ]);

        return response()->json($rows);
    }

    /** Barangay boundaries from the DB for the dashboard hero map (see Ib39\AreaController::boundaries). */
    public function boundaries(): JsonResponse
    {
        $features = MapBarangay::whereNotNull('geometry')
            ->get(['id', 'municipality', 'barangay', 'geometry'])
            ->map(fn ($a) => [
                'type' => 'Feature',
                'geometry' => $a->geometry,
                'properties' => ['id' => $a->id, 'municipality' => $a->municipality, 'barangay' => $a->barangay],
            ])->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
