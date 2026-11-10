<?php

use App\Models\RcspActivity;
use App\Models\RcspPhase;
use App\Support\RcspActivityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rcsp_phases')
            || ! Schema::hasTable('rcsp_barangays')
            || ! Schema::hasTable('rcsp_activities')) {
            return;
        }

        $plan = $this->buildReconciliationPlan();

        if (! Schema::hasColumn('rcsp_activities', 'catalog_order')) {
            Schema::table('rcsp_activities', function (Blueprint $table): void {
                $table->unsignedTinyInteger('catalog_order')->default(0)->after('normalized_title');
            });
        }
        if (! Schema::hasColumn('rcsp_activities', 'expected_result')) {
            Schema::table('rcsp_activities', function (Blueprint $table): void {
                $table->text('expected_result')->default('')->after('catalog_order');
            });
        }

        DB::transaction(function () use ($plan): void {
            foreach ($plan['updates'] as $update) {
                DB::table('rcsp_activities')->where('id', $update['id'])->update([
                    'description' => $update['description'],
                    'normalized_title' => RcspActivity::normalizeTitle($update['description']),
                    'catalog_order' => $update['catalog_order'],
                    'expected_result' => $update['expected_result'],
                ]);
            }

            $now = now();
            foreach ($plan['inserts'] as $insert) {
                DB::table('rcsp_activities')->insert([
                    'rcsp_phase_id' => $insert['rcsp_phase_id'],
                    'rcsp_barangay_id' => $insert['rcsp_barangay_id'],
                    'created_by_user_id' => null,
                    'description' => $insert['description'],
                    'normalized_title' => RcspActivity::normalizeTitle($insert['description']),
                    'catalog_order' => $insert['catalog_order'],
                    'expected_result' => $insert['expected_result'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'The canonical RCSP activity catalog cannot be rolled back safely after operational records may reference it.'
        );
    }

    /**
     * @return array{
     *   updates: list<array{id: int, description: string, catalog_order: int, expected_result: string}>,
     *   inserts: list<array{rcsp_phase_id: int, rcsp_barangay_id: int, description: string, catalog_order: int, expected_result: string}>
     * }
     */
    private function buildReconciliationPlan(): array
    {
        $phases = DB::table('rcsp_phases')
            ->where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->orderBy('number')
            ->get(['id', 'number']);

        if ($phases->count() !== 6
            || $phases->pluck('number')->map(fn ($number): int => (int) $number)->all() !== range(0, 5)) {
            throw new RuntimeException(
                'Cannot reconcile the RCSP catalog: the configurable phase structure is not exactly phases 0 through 5.'
            );
        }

        $phaseIds = $phases->mapWithKeys(fn (object $phase): array => [(int) $phase->number => (int) $phase->id]);
        $barangayIds = DB::table('rcsp_barangays')
            ->where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $updates = [];
        $inserts = [];
        $matchedIds = [];

        foreach ($barangayIds as $barangayId) {
            foreach (RcspActivityCatalog::ACTIVITIES as $phaseNumber => $_descriptions) {
                $phaseId = $phaseIds->get($phaseNumber);
                $rows = DB::table('rcsp_activities')
                    ->where('rcsp_barangay_id', $barangayId)
                    ->where('rcsp_phase_id', $phaseId)
                    ->orderBy('id')
                    ->get(['id', 'description', 'normalized_title']);

                foreach (RcspActivityCatalog::entriesForPhase($phaseNumber) as $entry) {
                    $matches = $rows->filter(function (object $row) use ($entry): bool {
                        $normalized = filled($row->normalized_title)
                            ? RcspActivity::normalizeTitle((string) $row->normalized_title)
                            : RcspActivity::normalizeTitle((string) $row->description);

                        return in_array($normalized, $entry['accepted_titles'], true);
                    })->values();

                    if ($matches->count() > 1) {
                        throw new RuntimeException(
                            "Cannot reconcile RCSP barangay {$barangayId}, phase {$phaseNumber}: multiple activities match {$entry['key']}."
                        );
                    }

                    if ($matches->isEmpty()) {
                        $legacy = RcspActivityCatalog::LEGACY_ACTIVITIES[$phaseNumber][$entry['order'] - 1] ?? null;
                        if ($legacy !== null) {
                            throw new RuntimeException(
                                "Cannot reconcile RCSP barangay {$barangayId}, phase {$phaseNumber}: activity {$entry['key']} is missing."
                            );
                        }

                        $inserts[] = [
                            'rcsp_phase_id' => $phaseId,
                            'rcsp_barangay_id' => $barangayId,
                            'description' => $entry['description'],
                            'catalog_order' => $entry['order'],
                            'expected_result' => $entry['expected_result'],
                        ];

                        continue;
                    }

                    $row = $matches->first();
                    $id = (int) $row->id;
                    if (isset($matchedIds[$id])) {
                        throw new RuntimeException("Cannot reconcile RCSP activity {$id}: it matched more than one official entry.");
                    }
                    $matchedIds[$id] = true;
                    $updates[] = [
                        'id' => $id,
                        'description' => $entry['description'],
                        'catalog_order' => $entry['order'],
                        'expected_result' => $entry['expected_result'],
                    ];
                }

                if ($rows->pluck('id')->map(fn ($id): int => (int) $id)
                    ->contains(fn (int $id): bool => ! isset($matchedIds[$id]))) {
                    throw new RuntimeException(
                        "Cannot reconcile RCSP barangay {$barangayId}, phase {$phaseNumber}: an unknown or duplicate activity exists."
                    );
                }
            }
        }

        $allActivityIds = DB::table('rcsp_activities')->pluck('id')->map(fn ($id): int => (int) $id);
        if ($allActivityIds->contains(fn (int $id): bool => ! isset($matchedIds[$id]))) {
            throw new RuntimeException(
                'Cannot reconcile the RCSP catalog: activities outside the verified configurable catalog exist.'
            );
        }

        return ['updates' => $updates, 'inserts' => $inserts];
    }
};
