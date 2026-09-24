<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\ColorHistory;
use App\Models\MapBarangay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RcspAreaHistoryService
{
    /** @return array{area: MapBarangay, history: ColorHistory, current: ColorHistory} */
    public function record(Barangay $barangay, string $effectiveDate, int $frs): array
    {
        return DB::transaction(function () use ($barangay, $effectiveDate, $frs): array {
            $canonical = Barangay::query()->with('municipality')->lockForUpdate()->findOrFail($barangay->id);

            $area = MapBarangay::query()
                ->where('barangay_id', $canonical->id)
                ->lockForUpdate()
                ->first();

            if (! $area) {
                $area = MapBarangay::query()->create([
                    'barangay_id' => $canonical->id,
                    'province' => MapBarangay::DEFAULT_PROVINCE,
                    'municipality' => $canonical->municipality->name,
                    'barangay' => $canonical->name,
                    'frs' => 0,
                    'rebels' => 0,
                    'status' => null,
                    'infestation_color' => MapBarangay::NEUTRAL_COLOR,
                ]);

                $area = MapBarangay::query()->whereKey($area->id)->lockForUpdate()->firstOrFail();
            }

            $classification = MapBarangay::classify($frs);

            $duplicate = $area->colorHistories()
                ->whereDate('effective_date', $effectiveDate)
                ->where('frs', $frs)
                ->where('status', $classification['status'])
                ->where('color', $classification['color'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'effective_date' => 'An identical history entry already exists for this barangay and effective date.',
                ]);
            }

            $history = $area->colorHistories()->create([
                'effective_date' => $effectiveDate,
                'frs' => $frs,
                'status' => $classification['status'],
                'color' => $classification['color'],
            ]);

            $current = $area->colorHistories()
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->firstOrFail();

            $area->update([
                'barangay_id' => $canonical->id,
                'province' => MapBarangay::DEFAULT_PROVINCE,
                'municipality' => $canonical->municipality->name,
                'barangay' => $canonical->name,
                'frs' => $current->frs,
                'rebels' => $current->frs,
                'status' => $current->status,
                'infestation_color' => $current->color,
            ]);

            return [
                'area' => $area->refresh(),
                'history' => $history,
                'current' => $current,
            ];
        }, 3);
    }
}
