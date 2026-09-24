<?php

use App\Models\RcspActivity;
use App\Models\RcspPhase;
use App\Support\RcspActivityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rcsp_phases')
            || ! Schema::hasTable('rcsp_barangays')
            || ! Schema::hasTable('rcsp_activities')
            || ! Schema::hasColumns('rcsp_activities', [
                'rcsp_phase_id', 'rcsp_barangay_id', 'created_by_user_id',
                'description', 'normalized_title',
            ])) {
            return;
        }

        DB::transaction(function (): void {
            $phases = DB::table('rcsp_phases')
                ->where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                ->pluck('id', 'number');
            $barangayIds = DB::table('rcsp_barangays')
                ->where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                ->pluck('id');
            $now = now();

            foreach ($barangayIds as $barangayId) {
                foreach (RcspActivityCatalog::ACTIVITIES as $phaseNumber => $activities) {
                    $phaseId = $phases->get($phaseNumber);
                    if (! $phaseId) {
                        continue;
                    }

                    foreach ($activities as $description) {
                        DB::table('rcsp_activities')->insertOrIgnore([
                            'rcsp_phase_id' => $phaseId,
                            'rcsp_barangay_id' => $barangayId,
                            'created_by_user_id' => null,
                            'description' => $description,
                            'normalized_title' => RcspActivity::normalizeTitle($description),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // The catalog can have evidence attached after deployment, so rollback
        // must not delete operational activity or form history.
    }
};
