<?php

namespace App\Http\Controllers\Ib39;

use App\Http\Controllers\Controller;
use App\Models\MapBarangay;
use App\Models\MapSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The boundary editor's safety net: a draft workspace plus restorable snapshots,
 * so edits are never immediately permanent and the original map is never lost.
 *
 * The live map (map_barangays.geometry) is only ever changed by publish() or
 * restoreSnapshot() — the editor autosaves to a single 'draft' snapshot in
 * between, leaving what everyone else sees untouched.
 */
class BoundaryDraftController extends Controller
{
    /** Draft if one exists, otherwise the live boundaries (with a hasDraft flag). */
    public function draft(): JsonResponse
    {
        $this->ensureOriginal();
        $draft = MapSnapshot::where('kind', 'draft')->latest('id')->first();

        return response()->json([
            'hasDraft' => (bool) $draft,
            'updatedAt' => $draft?->updated_at?->diffForHumans(),
            'featureCollection' => $draft ? $draft->data : $this->liveFeatureCollection(),
        ]);
    }

    /** Autosave the whole working FeatureCollection as the single draft. */
    public function saveDraft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'featureCollection' => ['required', 'array'],
            'featureCollection.type' => ['required', 'in:FeatureCollection'],
            'featureCollection.features' => ['present', 'array'],
        ]);

        $fc = $data['featureCollection'];

        $draft = MapSnapshot::updateOrCreate(
            ['kind' => 'draft'],
            ['label' => 'Working draft', 'data' => $fc, 'feature_count' => count($fc['features'])],
        );

        return response()->json(['saved' => true, 'updatedAt' => $draft->updated_at->diffForHumans()]);
    }

    /** Throw the draft away; the editor reloads the untouched live map. */
    public function discardDraft(): JsonResponse
    {
        MapSnapshot::where('kind', 'draft')->delete();

        return response()->json(['discarded' => true]);
    }

    /** Make the draft live — checkpoint the current map first, then apply. */
    public function publish(): JsonResponse
    {
        $draft = MapSnapshot::where('kind', 'draft')->latest('id')->first();

        if (! $draft) {
            return response()->json(['published' => false, 'message' => 'No draft to publish.'], 422);
        }

        DB::transaction(function () use ($draft) {
            $this->snapshotLive('Before publish — '.now()->format('M j, Y g:i A'), 'checkpoint');
            $this->applyToLive($draft->data);
            $draft->delete();
        });

        return response()->json(['published' => true]);
    }

    /** List restore points (checkpoints + the protected original), newest first. */
    public function snapshots(): JsonResponse
    {
        $this->ensureOriginal();

        $rows = MapSnapshot::whereIn('kind', ['checkpoint', 'original'])
            ->orderByRaw("kind = 'original' desc")->orderByDesc('id')
            ->get(['id', 'label', 'kind', 'feature_count', 'created_at'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'kind' => $s->kind,
                'features' => $s->feature_count,
                'at' => $s->created_at->diffForHumans(),
            ]);

        return response()->json($rows);
    }

    /** Manually save the current live map as a named checkpoint. */
    public function createSnapshot(Request $request): JsonResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:255']]);
        $label = $data['label'] ?: 'Checkpoint '.now()->format('M j, Y g:i A');

        $snap = $this->snapshotLive($label, 'checkpoint');

        return response()->json(['id' => $snap->id, 'label' => $snap->label], 201);
    }

    /** Roll the live map back to a snapshot — checkpointing the current state first. */
    public function restoreSnapshot(MapSnapshot $snapshot): JsonResponse
    {
        if ($snapshot->kind === 'draft') {
            return response()->json(['restored' => false], 422);
        }

        DB::transaction(function () use ($snapshot) {
            $this->snapshotLive('Before restore — '.now()->format('M j, Y g:i A'), 'checkpoint');
            $this->applyToLive($snapshot->data);
            // A restore replaces the map; any open draft is now stale.
            MapSnapshot::where('kind', 'draft')->delete();
        });

        return response()->json(['restored' => true]);
    }

    public function destroySnapshot(MapSnapshot $snapshot): JsonResponse
    {
        if ($snapshot->kind !== 'checkpoint') {
            return response()->json(['deleted' => false, 'message' => 'Only checkpoints can be deleted.'], 422);
        }

        $snapshot->delete();

        return response()->json(['deleted' => true]);
    }

    // --- helpers ------------------------------------------------------------

    /** Assemble the live boundaries as a GeoJSON FeatureCollection. */
    private function liveFeatureCollection(): array
    {
        $features = MapBarangay::whereNotNull('geometry')
            ->get(['id', 'municipality', 'barangay', 'geometry'])
            ->map(fn ($a) => [
                'type' => 'Feature',
                'geometry' => $a->geometry,
                'properties' => ['id' => $a->id, 'municipality' => $a->municipality, 'barangay' => $a->barangay],
            ])->values()->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    private function snapshotLive(string $label, string $kind): MapSnapshot
    {
        $fc = $this->liveFeatureCollection();

        return MapSnapshot::create([
            'label' => $label,
            'kind' => $kind,
            'data' => $fc,
            'feature_count' => count($fc['features']),
        ]);
    }

    /** The pristine boundaries are captured once, the first time it's needed. */
    private function ensureOriginal(): void
    {
        if (! MapSnapshot::where('kind', 'original')->exists()) {
            $this->snapshotLive('Original map', 'original');
        }
    }

    /**
     * Replace the live geometry from a FeatureCollection. Features carry the
     * barangay id where known; unknown name pairs create rows. Any barangay with
     * geometry that the collection no longer covers is cleared (a deletion).
     */
    private function applyToLive(array $fc): void
    {
        $covered = [];

        foreach ($fc['features'] ?? [] as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry)) {
                continue;
            }

            $p = $feature['properties'] ?? [];
            $row = null;

            if (! empty($p['id'])) {
                $row = MapBarangay::find($p['id']);
            }
            if (! $row) {
                $municipality = trim($p['municipality'] ?? '');
                $barangay = trim($p['barangay'] ?? '');
                if ($municipality === '' || $barangay === '') {
                    continue;
                }
                $row = MapBarangay::firstOrNew(['municipality' => $municipality, 'barangay' => $barangay]);
                $row->province ??= 'Davao del Sur';
            }

            $row->geometry = $this->normalize($geometry);
            $row->save();
            $covered[] = $row->id;
        }

        // Anything that had geometry but is no longer in the collection was deleted.
        MapBarangay::whereNotNull('geometry')->whereNotIn('id', $covered ?: [0])->update(['geometry' => null]);
    }

    private function normalize(array $geometry): array
    {
        if (($geometry['type'] ?? null) === 'Polygon') {
            return ['type' => 'MultiPolygon', 'coordinates' => [$geometry['coordinates']]];
        }

        return $geometry;
    }
}
