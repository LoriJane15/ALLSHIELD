<?php

namespace App\Http\Controllers\Ib39;

use App\Events\RcspAreaUpdated;
use App\Http\Controllers\Controller;
use App\Models\MapBarangay;
use App\Models\RcspBarangay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AreaController extends Controller
{
    /** Colour the legacy page reset a barangay to when its RCSP data was removed. */
    private const CLEARED_COLOR = 'rgba(190,178,151,0.1)';

    /**
     * Add Area — the port of accounts/39th-IB/add_rcsp.php.
     *
     * Like the legacy page, this lists only barangays that have been given an
     * FR count (`frs > 0`); the other ~200 exist in the map table but are not
     * yet part of RCSP, and appear in the "Add" dropdown instead.
     */
    public function index(Request $request): View
    {
        $areas = MapBarangay::query()
            ->where('frs', '>', 0)
            ->when($request->municipality, fn ($q, $m) => $q->where('municipality', $m))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('barangay', 'like', "%{$s}%")
                ->orWhere('municipality', 'like', "%{$s}%")))
            // Legacy "FR range" filter: 0-9, 10-14, 15-19, 20+
            ->when($request->fr_range, function ($q, $range) {
                [$min, $max] = match ($range) {
                    '0-9' => [0, 9],
                    '10-14' => [10, 14],
                    '15-19' => [15, 19],
                    '20+' => [20, PHP_INT_MAX],
                    default => [0, PHP_INT_MAX],
                };

                return $q->where('frs', '>=', $min)->where('frs', '<=', $max);
            })
            ->orderBy('municipality')->orderBy('barangay')
            ->paginate(20)->withQueryString();

        $municipalities = MapBarangay::select('municipality')->distinct()
            ->whereNotNull('municipality')->orderBy('municipality')->pluck('municipality');

        return view('ib39.areas.index', compact('areas', 'municipalities'));
    }

    /** Barangays in a municipality, for the Add modal's cascading dropdown. */
    public function barangays(Request $request): JsonResponse
    {
        $data = $request->validate(['municipality' => ['required', 'string']]);

        return response()->json(
            MapBarangay::where('municipality', $data['municipality'])
                ->orderBy('barangay')
                ->pluck('barangay')
        );
    }

    /**
     * Add an existing barangay to RCSP by giving it an FR count. The legacy page
     * never inserted rows here — all 232 barangays already exist in the map
     * table, so "adding" sets the count, status and colour, and logs history.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'municipality' => ['required', 'string'],
            'barangay' => ['required', 'string'],
            'frs' => ['required', 'integer', 'min:0'],
        ]);

        $area = MapBarangay::where('municipality', $data['municipality'])
            ->where('barangay', $data['barangay'])
            ->first();

        if (! $area) {
            return back()->with(
                'error',
                "Barangay '{$data['barangay']}' in municipality '{$data['municipality']}' not found"
            );
        }

        $this->applyCount($area, $data['frs']);

        return redirect()->route('ib39.areas.index')->with(
            'success',
            "RCSP Barangay '{$area->barangay}' in {$area->municipality} added successfully"
        );
    }

    /** Set an area's FR count → recompute status + infestation colour, log history. */
    public function update(Request $request, MapBarangay $area): RedirectResponse
    {
        $data = $request->validate([
            'frs' => ['required', 'integer', 'min:0'],
        ]);

        $class = $this->applyCount($area, $data['frs']);

        return back()->with('success', "{$area->barangay} set to {$class['status']} ({$data['frs']} FRs).");
    }

    /**
     * Remove a barangay's RCSP data. The legacy page did not delete the row — it
     * reset the count, cleared the status and restored the neutral map colour,
     * which drops it out of this listing. Colour history is kept.
     */
    public function destroy(MapBarangay $area): RedirectResponse
    {
        $area->update([
            'status' => null,
            'frs' => 0,
            'rebels' => 0,
            'infestation_color' => self::CLEARED_COLOR,
        ]);

        broadcast(new RcspAreaUpdated($area));

        return back()->with('success', "Successfully removed RCSP data for barangay {$area->barangay}");
    }

    /** Shared by add + update: classify the count, save it, and log the change. */
    private function applyCount(MapBarangay $area, int $frs): array
    {
        $class = MapBarangay::classify($frs);

        DB::transaction(function () use ($area, $frs, $class) {
            $area->update([
                'frs' => $frs,
                'rebels' => $frs,
                'status' => $class['status'],
                'infestation_color' => $class['color'],
            ]);

            $area->colorHistories()->create([
                'status' => $class['status'],
                'color' => $class['color'],
                'frs' => $frs,
            ]);
        });

        // Legacy pushed an SSE event so open maps repainted; broadcast instead.
        broadcast(new RcspAreaUpdated($area->refresh()));

        return $class;
    }

    public function map(): View
    {
        return view('ib39.map', ['legend' => MapBarangay::LEGEND]);
    }

    /** The interactive boundary editor (draw / reshape / add / delete polygons). */
    public function editor(): View
    {
        $municipalities = MapBarangay::select('municipality')->distinct()
            ->whereNotNull('municipality')->orderBy('municipality')->pluck('municipality');

        return view('ib39.boundary-editor', compact('municipalities'));
    }

    /** Reshape an existing barangay's polygon. */
    public function updateBoundary(Request $request, MapBarangay $area): JsonResponse
    {
        $data = $request->validate(['geometry' => ['required', 'array']]);

        $area->geometry = $this->normalizeGeometry($data['geometry']);
        $area->save();

        return response()->json(['id' => $area->id, 'saved' => true]);
    }

    /** Add a new area from a freshly drawn polygon. */
    public function storeBoundary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'geometry' => ['required', 'array'],
        ]);

        // Update the matching row if the name pair already exists, else create one.
        $area = MapBarangay::firstOrNew([
            'municipality' => trim($data['municipality']),
            'barangay' => trim($data['barangay']),
        ]);

        $area->province ??= 'Davao del Sur';
        $area->geometry = $this->normalizeGeometry($data['geometry']);
        $area->save();

        return response()->json([
            'id' => $area->id,
            'municipality' => $area->municipality,
            'barangay' => $area->barangay,
            'created' => $area->wasRecentlyCreated,
        ], 201);
    }

    /**
     * Remove an area's polygon. If the barangay carries no RCSP data the whole
     * row is deleted; otherwise the geometry is cleared but the record (and its
     * FR counts / colour history) is kept.
     */
    public function destroyBoundary(MapBarangay $area): JsonResponse
    {
        if ((int) $area->frs === 0 && $area->colorHistories()->doesntExist()) {
            $area->delete();

            return response()->json(['deleted' => true]);
        }

        $area->geometry = null;
        $area->save();

        return response()->json(['deleted' => false, 'cleared' => true]);
    }

    /** Download the current boundaries as a GeoJSON file. */
    public function exportBoundaries(): \Symfony\Component\HttpFoundation\Response
    {
        $fc = $this->boundaries()->getData(true);

        return response()->json($fc, 200, [
            'Content-Disposition' => 'attachment; filename="shield-boundaries-'.now()->format('Y-m-d').'.geojson"',
        ], JSON_UNESCAPED_SLASHES);
    }

    /** Replace or merge boundaries from an uploaded GeoJSON file. */
    public function importBoundaries(Request $request, \App\Services\BoundaryImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:application/json,application/geo+json,text/plain,text/json'],
            'mode' => ['nullable', 'in:merge,replace'],
            'create' => ['nullable', 'boolean'],
        ]);

        $fc = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($fc)) {
            return back()->with('error', 'That file is not valid JSON.');
        }

        $result = $importer->import(
            $fc,
            create: $request->boolean('create'),
            replace: $request->input('mode') === 'replace',
        );

        $msg = "Imported — matched {$result['matched']}, created {$result['created']}, skipped {$result['skipped']}.";

        return back()->with($result['matched'] + $result['created'] > 0 ? 'success' : 'error', $msg);
    }

    /** Store polygons as MultiPolygon for a single consistent geometry type. */
    private function normalizeGeometry(array $geometry): array
    {
        if (($geometry['type'] ?? null) === 'Polygon') {
            return ['type' => 'MultiPolygon', 'coordinates' => [$geometry['coordinates']]];
        }

        return $geometry;
    }

    /**
     * Detail panel for one barangay — the port of the legacy
     * final_mapping/fetch_barangay_data.php: the frmap_barangays row, the last
     * five colour-history entries, and whether the barangay is under RCSP.
     */
    public function barangayData(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality' => ['required', 'string'],
            'barangay' => ['required', 'string'],
        ]);

        $area = MapBarangay::where('municipality', $data['municipality'])
            ->where('barangay', $data['barangay'])
            ->first();

        if (! $area) {
            return response()->json(['error' => 'No data found for this location'], 404);
        }

        $history = $area->colorHistories()
            ->orderByDesc('created_at')->orderByDesc('id')
            ->take(5)
            ->get()
            ->map(fn ($h) => [
                'status' => $h->status,
                'color' => $h->color,
                'frs' => (int) $h->frs,
                // Matches the legacy DATE_FORMAT('%M %d, %Y - %h:%i %p').
                'formatted_timestamp' => $h->created_at?->format('F d, Y - h:i A'),
            ]);

        return response()->json([
            'province' => $area->province ?: 'Davao del Sur',
            'municipality' => $area->municipality,
            'barangay' => $area->barangay,
            'status' => $area->status,
            'frs' => (int) $area->frs,
            'infestation_color' => $area->infestation_color,
            'is_rcsp' => RcspBarangay::whereHas(
                'barangay',
                fn ($q) => $q->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper(trim($data['barangay']))])
            )->exists(),
            'color_history' => $history,
        ]);
    }

    /**
     * Infestation status per barangay, keyed "Municipality|Barangay" to match the
     * NAME_2/NAME_3 properties in public/assets/mapping/barangays.geojson.
     */
    public function areaData(): JsonResponse
    {
        $rows = MapBarangay::get(['id', 'municipality', 'barangay', 'frs', 'status', 'infestation_color'])
            ->mapWithKeys(fn ($a) => [
                "{$a->municipality}|{$a->barangay}" => [
                    'id' => $a->id,
                    'frs' => (int) $a->frs,
                    'status' => $a->status,
                    'color' => $a->infestation_color,
                ],
            ]);

        return response()->json($rows);
    }

    /**
     * Barangay boundaries as a GeoJSON FeatureCollection, assembled from the
     * database rather than the static file — so edits to the geometry show up
     * everywhere the map is drawn. Falls back to the committed file for any row
     * whose geometry has not been imported yet.
     */
    public function boundaries(): JsonResponse
    {
        $features = MapBarangay::whereNotNull('geometry')
            ->get(['id', 'municipality', 'barangay', 'geometry'])
            ->map(fn ($a) => [
                'type' => 'Feature',
                'geometry' => $a->geometry,
                'properties' => [
                    'id' => $a->id,
                    'municipality' => $a->municipality,
                    'barangay' => $a->barangay,
                ],
            ])->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /** Former-rebel points for the Leaflet operational map. */
    public function mapData(): JsonResponse
    {
        $rows = \App\Models\FormerRebel::whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['id', 'firstname', 'lastname', 'placement_address', 'latitude', 'longitude', 'status', 'batch_year'])
            ->map(fn ($fr) => [
                'name' => trim("{$fr->firstname} {$fr->lastname}"),
                'address' => $fr->placement_address,
                'lat' => (float) $fr->latitude,
                'lng' => (float) $fr->longitude,
                'status' => $fr->status,
                'batch' => $fr->batch_year,
            ]);

        return response()->json($rows);
    }
}
