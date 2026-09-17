<?php

namespace App\Http\Controllers\Ib39;

use App\Events\RcspAreaUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\SaveAreaHistoryRequest;
use App\Models\Barangay;
use App\Models\MapBarangay;
use App\Models\Municipality;
use App\Services\RcspAreaHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
