<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspPhase;
use App\Models\User;
use App\Support\RcspActivityCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RcspActivityCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_rcsp_barangay_receives_the_complete_operational_catalog(): void
    {
        $municipality = Municipality::create(['name' => 'Catalog Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Catalog Barangay',
        ]);
        $lgu = User::factory()->lgu($municipality->id)->create();

        $this->actingAs($lgu)->post(route('lgu.rcsp.store'), [
            'barangay_id' => $barangay->id,
        ])->assertSessionHasNoErrors();

        $record = RcspBarangay::where('barangay_id', $barangay->id)->firstOrFail();
        $this->assertSame(31, $record->activities()->count());

        foreach (RcspActivityCatalog::ACTIVITIES as $phaseNumber => $expected) {
            $phase = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                ->where('number', $phaseNumber)->firstOrFail();
            $this->assertSame(
                $expected,
                RcspActivity::forBarangayPhase($record, $phase)->orderBy('id')->pluck('description')->all(),
            );
        }
    }

    public function test_catalog_migration_backfills_existing_rcsp_barangays_idempotently(): void
    {
        $municipality = Municipality::create(['name' => 'Backfill Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Backfill Barangay',
        ]);
        $record = RcspBarangay::create([
            'barangay_id' => $barangay->id,
            'municipality_id' => $municipality->id,
            'status' => 'Pending',
            'current_phase' => 0,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY,
        ]);

        $migration = require database_path('migrations/2026_09_24_000002_populate_rcsp_activities.php');
        $migration->up();
        $migration->up();

        $this->assertSame(31, DB::table('rcsp_activities')->where('rcsp_barangay_id', $record->id)->count());
    }
}
