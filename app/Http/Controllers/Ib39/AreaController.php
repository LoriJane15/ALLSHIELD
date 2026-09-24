<?php

namespace App\Http\Controllers\Ib39;

use App\Events\RcspAreaUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\SaveAreaHistoryRequest;
use App\Models\Barangay;
use App\Models\MapBarangay;
use App\Models\Municipality;
use App\Services\BoundaryImporter;
use App\Services\RcspAreaHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AreaController extends Controller
{
    /** List active RCSP areas, including zero-count Recovery histories. */
    public function index(Request $request): View
    {
        $areas = MapBarangay::query()
            ->with(['barangayRecord.municipality', 'latestColorHistory'])
            ->whereNotNull('status')
            ->whereHas('colorHistories')
            ->when($request->municipality, fn ($q, $m) => $q->where(function ($where) use ($m): void {
                $where->where('municipality', $m)
                    ->orWhereHas('barangayRecord.municipality', fn ($municipality) => $municipality->where('name', $m));
            }))
            ->when($request->status, fn ($q, $s) => $q->whereHas(
                'latestColorHistory',
                fn ($history) => $history->where('status', $s)
            ))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('barangay', 'like', "%{$s}%")
                ->orWhere('municipality', 'like', "%{$s}%")
                ->orWhereHas('barangayRecord', fn ($barangay) => $barangay
                    ->where('name', 'like', "%{$s}%")
                    ->orWhereHas('municipality', fn ($municipality) => $municipality->where('name', 'like', "%{$s}%")))))
            ->when($request->fr_range, function ($q, $range) {
                [$min, $max] = match ($range) {
                    '0-9' => [0, 9],
                    '10-14' => [10, 14],
                    '15-19' => [15, 19],
                    '20+' => [20, PHP_INT_MAX],
                    default => [0, PHP_INT_MAX],
                };

                return $q->whereHas('latestColorHistory', fn ($history) => $history
                    ->where('frs', '>=', $min)
                    ->where('frs', '<=', $max));
            })
            ->orderBy('municipality')->orderBy('barangay')
            ->paginate(20)->withQueryString();

        $municipalities = Municipality::query()->orderBy('name')->get(['id', 'name']);

        return view('ib39.areas.index', [
            'areas' => $areas,
            'municipalities' => $municipalities,
            'classifications' => MapBarangay::CLASSIFICATIONS,
        ]);
    }

    /** Barangays in a municipality, for the Add modal's cascading dropdown. */
    public function barangays(Request $request): JsonResponse
    {
        $data = $request->validate(['municipality_id' => ['required', 'integer', 'exists:municipalities,id']]);

        return response()->json(
            Barangay::query()
                ->where('municipality_id', $data['municipality_id'])
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    /** Append history for a canonically identified barangay. */
    public function store(SaveAreaHistoryRequest $request, RcspAreaHistoryService $historyService): RedirectResponse
    {
        $result = $historyService->record(
            $request->canonicalBarangay(),
            $request->validated('effective_date'),
            $request->integer('frs')
        );

        broadcast(new RcspAreaUpdated($result['area']));

        return redirect()->route('ib39.areas.index')->with(
            'success',
            "Area history for {$result['area']->barangay} was recorded successfully."
        );
    }

    public function update(
        SaveAreaHistoryRequest $request,
        MapBarangay $area,
        RcspAreaHistoryService $historyService
    ): RedirectResponse {
        $result = $historyService->record(
            $request->canonicalBarangay(),
            $request->validated('effective_date'),
            $request->integer('frs')
        );

        broadcast(new RcspAreaUpdated($result['area']));

        return back()->with(
            'success',
            "{$result['area']->barangay} is currently {$result['current']->status} ({$result['current']->frs} FRs)."
        );
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
            'infestation_color' => MapBarangay::NEUTRAL_COLOR,
        ]);

        broadcast(new RcspAreaUpdated($area));

        return back()->with('success', "Successfully removed RCSP data for barangay {$area->barangay}");
    }

    public function map(): View
    {
        return view('ib39.map');
    }

    public function editor(): View
    {
        $municipalities = Municipality::query()->orderBy('name')->pluck('name');

        return view('ib39.boundary-editor', compact('municipalities'));
    }

    public function updateBoundary(Request $request, MapBarangay $area): JsonResponse
    {
        $data = $request->validate(['geometry' => ['required', 'array']]);
        $area->update(['geometry' => $this->normalizeGeometry($data['geometry'])]);

        return response()->json(['id' => $area->id, 'saved' => true]);
    }

    public function storeBoundary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'geometry' => ['required', 'array'],
        ]);

        $municipality = Municipality::query()->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper(trim($data['municipality']))])->first();
        $barangay = $municipality?->barangays()
            ->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper(trim($data['barangay']))])
            ->first();

        $area = $barangay
            ? MapBarangay::firstOrNew(['barangay_id' => $barangay->id])
            : MapBarangay::firstOrNew([
                'municipality' => trim($data['municipality']),
                'barangay' => trim($data['barangay']),
            ]);

        $area->fill([
            'barangay_id' => $barangay?->id,
            'province' => $area->province ?: MapBarangay::DEFAULT_PROVINCE,
            'municipality' => $municipality?->name ?? trim($data['municipality']),
            'barangay' => $barangay?->name ?? trim($data['barangay']),
            'geometry' => $this->normalizeGeometry($data['geometry']),
        ])->save();

        return response()->json([
            'id' => $area->id,
            'municipality' => $area->municipality,
            'barangay' => $area->barangay,
            'created' => $area->wasRecentlyCreated,
        ], 201);
    }

    public function destroyBoundary(MapBarangay $area): JsonResponse
    {
        if ((int) $area->frs === 0 && $area->colorHistories()->doesntExist()) {
            $area->delete();

            return response()->json(['deleted' => true]);
        }

        $area->update(['geometry' => null]);

        return response()->json(['deleted' => false, 'cleared' => true]);
    }

    public function exportBoundaries(): Response
    {
        return response()->json($this->boundaries()->getData(true), 200, [
            'Content-Disposition' => 'attachment; filename="shield-boundaries-'.now()->format('Y-m-d').'.geojson"',
        ], JSON_UNESCAPED_SLASHES);
    }

    public function importBoundaries(Request $request, BoundaryImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:application/json,application/geo+json,text/plain,text/json'],
            'mode' => ['nullable', 'in:merge,replace'],
            'create' => ['nullable', 'boolean'],
        ]);

        $featureCollection = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($featureCollection)) {
            return back()->with('error', 'That file is not valid JSON.');
        }

        $result = $importer->import(
            $featureCollection,
            create: $request->boolean('create'),
            replace: $request->input('mode') === 'replace',
        );

        $message = "Imported — matched {$result['matched']}, created {$result['created']}, skipped {$result['skipped']}.";

        return back()->with($result['matched'] + $result['created'] > 0 ? 'success' : 'error', $message);
    }

    /** Return aggregate current state and complete history for one canonical barangay. */
    public function barangayData(Request $request): JsonResponse
    {
        $data = $request->validate(['barangay_id' => ['required', 'integer', 'exists:barangays,id']]);
        $barangay = Barangay::query()->with('municipality')->findOrFail($data['barangay_id']);
        $area = MapBarangay::query()->where('barangay_id', $barangay->id)->first();
        $current = $area?->status !== null
            ? $area->colorHistories()->orderByDesc('effective_date')->orderByDesc('id')->first()
            : null;

        $history = $area?->colorHistories()
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->get()
            ->map(fn ($h) => [
                'status' => $h->status,
                'color' => $h->color,
                'frs' => (int) $h->frs,
                'effective_date' => $h->effective_date?->toDateString(),
            ]) ?? collect();

        return response()->json([
            'province' => MapBarangay::DEFAULT_PROVINCE,
            'municipality' => $barangay->municipality->name,
            'barangay' => $barangay->name,
            'status' => $current?->status,
            'frs' => (int) ($current?->frs ?? 0),
            'color' => $current?->color ?? MapBarangay::NEUTRAL_COLOR,
            'color_history' => $history,
        ]);
    }

    /** Return active current states keyed by canonical barangay ID. */
    public function areaData(): JsonResponse
    {
        $rows = MapBarangay::query()
            ->with('latestColorHistory')
            ->whereNotNull('barangay_id')
            ->whereNotNull('status')
            ->get()
            ->mapWithKeys(fn ($a) => [
                (string) $a->barangay_id => [
                    'frs' => (int) ($a->latestColorHistory?->frs ?? 0),
                    'status' => $a->latestColorHistory?->status,
                    'color' => $a->latestColorHistory?->color ?? MapBarangay::NEUTRAL_COLOR,
                ],
            ]);

        return response()->json($rows);
    }

    public function boundaries(): JsonResponse
    {
        $features = MapBarangay::query()
            ->with('barangayRecord.municipality')
            ->whereNotNull('geometry')
            ->get(['id', 'barangay_id', 'municipality', 'barangay', 'geometry'])
            ->map(function (MapBarangay $area): array {
                $barangay = $area->barangayRecord;

                return [
                    'type' => 'Feature',
                    'geometry' => $area->geometry,
                    'properties' => [
                        'id' => $area->id,
                        'barangay_id' => $area->barangay_id,
                        'municipality' => $barangay?->municipality?->name ?? $area->municipality,
                        'barangay' => $barangay?->name ?? $area->barangay,
                    ],
                ];
            })->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    private function normalizeGeometry(array $geometry): array
    {
        if (($geometry['type'] ?? null) === 'Polygon') {
            return ['type' => 'MultiPolygon', 'coordinates' => [$geometry['coordinates']]];
        }

        return $geometry;
    }
}
