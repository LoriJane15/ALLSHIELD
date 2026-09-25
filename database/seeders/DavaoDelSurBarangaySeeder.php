<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DavaoDelSurBarangaySeeder extends Seeder
{
    public function run(): void
    {
        $municipalityNames = collect(config('shield.jurisdiction.municipalities'))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values();
        $source = $this->sourceRows();

        $configuredKeys = $municipalityNames->map($this->normalize(...))->sort()->values();
        $sourceKeys = $source->pluck('municipality_key')->unique()->sort()->values();
        if ($sourceKeys->all() !== $configuredKeys->all()) {
            throw new RuntimeException('The checked-in barangay source does not match the configured Davao del Sur municipalities.');
        }

        DB::transaction(function () use ($municipalityNames, $source): void {
            $municipalities = Municipality::query()->whereIn('name', $municipalityNames)->get()
                ->keyBy(fn (Municipality $municipality): string => $this->normalize($municipality->name));
            $missingMunicipalities = $municipalityNames
                ->reject(fn (string $name): bool => $municipalities->has($this->normalize($name)));
            if ($missingMunicipalities->isNotEmpty()) {
                throw new RuntimeException(
                    'Missing configured municipalities: '.$missingMunicipalities->implode(', ').'. Seed municipalities first.'
                );
            }

            $existing = Barangay::query()
                ->whereIn('municipality_id', $municipalities->pluck('id'))
                ->get(['id', 'municipality_id', 'name'])
                ->groupBy('municipality_id');

            $municipalityIdsToPopulate = collect();
            foreach ($municipalities as $municipalityKey => $municipality) {
                $existingNames = $existing->get($municipality->id, collect())
                    ->pluck('name')->map($this->normalize(...))->sort()->values();
                if ($existingNames->isEmpty()) {
                    $municipalityIdsToPopulate->push($municipality->id);

                    continue;
                }

                $sourceNames = $source->where('municipality_key', $municipalityKey)
                    ->pluck('barangay')->map($this->normalize(...))->sort()->values();
                if ($existingNames->all() !== $sourceNames->all()) {
                    throw new RuntimeException(
                        "Barangays for {$municipality->name} are partially populated or conflict with the checked-in source; no rows were changed."
                    );
                }
            }

            if ($municipalityIdsToPopulate->isEmpty()) {
                return;
            }

            $occupiedIds = Barangay::query()->pluck('id')->mapWithKeys(
                fn (int|string $id): array => [(int) $id => true]
            );
            $nextId = max(
                (int) ($occupiedIds->keys()->max() ?? 0),
                (int) ($source->pluck('preferred_id')->filter()->max() ?? 0)
            ) + 1;
            $timestamp = now();
            $inserts = [];

            foreach ($source as $row) {
                $municipality = $municipalities->get($row['municipality_key']);
                if (! $municipalityIdsToPopulate->contains($municipality->id)) {
                    continue;
                }

                $id = $row['preferred_id'];
                if (! $id || $occupiedIds->has($id)) {
                    while ($occupiedIds->has($nextId)) {
                        $nextId++;
                    }
                    $id = $nextId++;
                }
                $occupiedIds->put($id, true);
                $inserts[] = [
                    'id' => $id,
                    'municipality_id' => $municipality->id,
                    'name' => $row['barangay'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            foreach (array_chunk($inserts, 100) as $chunk) {
                DB::table('barangays')->insert($chunk);
            }

            $this->synchronizePostgresSequence();
        });
    }

    /** @return Collection<int, array{municipality_key: string, barangay: string, preferred_id: ?int}> */
    private function sourceRows(): Collection
    {
        $path = public_path('assets/mapping/barangays.geojson');
        if (! is_file($path)) {
            throw new RuntimeException("The checked-in barangay source is missing: {$path}");
        }

        $geojson = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $features = collect($geojson['features'] ?? []);
        if ($features->isEmpty()) {
            throw new RuntimeException('The checked-in barangay source contains no features.');
        }

        $rows = $features->map(function (array $feature): array {
            $properties = $feature['properties'] ?? [];
            $municipality = trim((string) ($properties['municipality'] ?? ''));
            $barangay = trim((string) ($properties['barangay'] ?? ''));
            if ($municipality === '' || $barangay === '') {
                throw new RuntimeException('The checked-in barangay source contains an unnamed location.');
            }

            $preferredId = $properties['barangay_id'] ?? null;
            if ($preferredId !== null && (! is_int($preferredId) || $preferredId < 1)) {
                throw new RuntimeException("The checked-in barangay source contains an invalid ID for {$municipality}|{$barangay}.");
            }

            return [
                'municipality_key' => $this->normalize($municipality),
                'barangay' => $barangay,
                'preferred_id' => $preferredId,
            ];
        });

        $duplicates = $rows->duplicates(fn (array $row): string => $row['municipality_key'].'|'.$this->normalize($row['barangay']));
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('The checked-in barangay source contains duplicate municipality/barangay entries.');
        }

        return $rows;
    }

    private function synchronizePostgresSequence(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $sequence = DB::scalar("SELECT pg_get_serial_sequence('barangays', 'id')");
        if ($sequence) {
            DB::statement('SELECT setval(CAST(? AS regclass), ?, true)', [
                $sequence,
                (int) DB::table('barangays')->max('id'),
            ]);
        }
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }
}
