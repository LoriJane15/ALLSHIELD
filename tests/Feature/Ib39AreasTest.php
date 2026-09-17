<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\MapBarangay;
use App\Models\Municipality;
use App\Models\User;
use App\Services\RcspAreaHistoryService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Ib39AreasTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private Barangay $barangay;

    private User $ib39;

    protected function setUp(): void
    {
        parent::setUp();
        $this->municipality = Municipality::query()->create(['name' => 'Verified Municipality']);
        $this->barangay = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Verified Barangay',
        ]);
        $this->ib39 = User::factory()->role('39th_ib')->create();
    }

    public function test_only_active_39th_ib_accounts_can_access_area_routes(): void
    {
        $this->get(route('ib39.areas.index'))->assertRedirect(route('login'));
        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->actingAs($inactive)->get(route('ib39.areas.index'))->assertRedirect(route('login'));

        foreach (['admin', 'lgu', 'afp', 'mblrc', 'gov_agency', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('ib39.areas.index'))->assertForbidden();
        }

        $this->actingAs($this->ib39)->get(route('ib39.areas.index'))->assertOk();
    }

    public function test_form_request_rejects_invalid_counts_geography_and_authority_bearing_input(): void
    {
        $this->actingAs($this->ib39)->post(route('ib39.areas.store'), [
            'barangay_id' => 999999,
            'effective_date' => 'not-a-date',
            'frs' => -1,
            'status' => 'Konsolidado',
            'color' => 'red',
            'role' => '39th_ib',
            'municipality' => $this->municipality->name,
            'province' => 'Forged Province',
        ])->assertSessionHasErrors([
            'barangay_id', 'effective_date', 'frs', 'status', 'color', 'role', 'municipality', 'province',
        ]);

        $this->actingAs($this->ib39)->post(route('ib39.areas.store'), [
            'barangay_id' => $this->barangay->id,
            'effective_date' => '2026-09-13',
            'frs' => '2.5',
        ])->assertSessionHasErrors('frs');

        $this->assertDatabaseCount('map_barangays', 0);
        $this->assertDatabaseCount('color_histories', 0);
    }

    public function test_history_creation_derives_geography_and_keeps_zero_count_visible(): void
    {
        $this->postHistory($this->barangay, 0, '2026-09-13')->assertRedirect(route('ib39.areas.index'));

        $area = MapBarangay::query()->with('latestColorHistory')->firstOrFail();
        $this->assertSame($this->barangay->id, $area->barangay_id);
        $this->assertSame(MapBarangay::DEFAULT_PROVINCE, $area->province);
        $this->assertSame($this->municipality->name, $area->municipality);
        $this->assertSame($this->barangay->name, $area->barangay);
        $this->assertSame('Recovery', $area->status);
        $this->assertSame('rgba(0,255,0,0.5)', $area->infestation_color);
        $this->assertSame('2026-09-13', $area->latestColorHistory->effective_date->toDateString());

        $this->actingAs($this->ib39)->get(route('ib39.areas.index'))
            ->assertOk()->assertSee($this->barangay->name)->assertSee('Recovery');
    }

    public function test_all_classification_boundaries_use_the_model_owned_names_and_colors(): void
    {
        $cases = [
            0 => ['Recovery', 'rgba(0,255,0,0.5)'],
            9 => ['Recovery', 'rgba(0,255,0,0.5)'],
            10 => ['Expansion', 'rgba(255,255,0,0.5)'],
            14 => ['Expansion', 'rgba(255,255,0,0.5)'],
            15 => ['Rekonsilido', 'rgba(255,165,0,0.5)'],
            19 => ['Rekonsilido', 'rgba(255,165,0,0.5)'],
            20 => ['Konsolidado', 'rgba(255,0,0,0.5)'],
        ];

        foreach ($cases as $count => [$status, $color]) {
            $this->assertSame(['status' => $status, 'color' => $color], MapBarangay::classify($count));
        }

        $this->assertSame(
            ['Konsolidado', 'Rekonsilido', 'Expansion', 'Recovery'],
            array_column(MapBarangay::CLASSIFICATIONS, 'status')
        );
    }

    public function test_exact_duplicates_are_rejected_but_distinct_same_date_updates_are_current_by_id(): void
    {
        $this->postHistory($this->barangay, 12, '2026-09-13')->assertSessionHasNoErrors();
        $this->postHistory($this->barangay, 12, '2026-09-13')->assertSessionHasErrors('effective_date');
        $this->postHistory($this->barangay, 15, '2026-09-13')->assertSessionHasNoErrors();

        $area = MapBarangay::query()->with('latestColorHistory')->firstOrFail();
        $this->assertSame(2, $area->colorHistories()->count());
        $this->assertSame(15, $area->frs);
        $this->assertSame('Rekonsilido', $area->status);
        $this->assertSame(15, $area->latestColorHistory->frs);
    }

    public function test_backdated_history_is_preserved_without_replacing_later_current_state(): void
    {
        $this->postHistory($this->barangay, 20, '2026-09-13')->assertSessionHasNoErrors();
        $this->postHistory($this->barangay, 5, '2026-09-01')->assertSessionHasNoErrors();

        $area = MapBarangay::query()->with('latestColorHistory')->firstOrFail();
        $this->assertSame(2, $area->colorHistories()->count());
        $this->assertSame(20, $area->frs);
        $this->assertSame('Konsolidado', $area->status);
        $this->assertSame('2026-09-13', $area->latestColorHistory->effective_date->toDateString());
    }

    public function test_parent_cache_failure_rolls_back_the_appended_history(): void
    {
        $service = app(RcspAreaHistoryService::class);
        $service->record($this->barangay, '2026-09-01', 5);
        $area = MapBarangay::query()->firstOrFail();

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER rcsp_area_cache_failure
            BEFORE UPDATE OF frs ON map_barangays
            BEGIN
                SELECT RAISE(ABORT, 'simulated cache failure');
            END;
        SQL);

        try {
            $service->record($this->barangay, '2026-09-02', 12);
            $this->fail('The simulated cache failure did not abort the transaction.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated cache failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS rcsp_area_cache_failure');
        }

        $this->assertSame(1, $area->colorHistories()->count());
        $this->assertSame(5, $area->fresh()->frs);
    }

    public function test_update_route_rejects_a_different_canonical_barangay(): void
    {
        app(RcspAreaHistoryService::class)->record($this->barangay, '2026-09-01', 5);
        $area = MapBarangay::query()->firstOrFail();
        $other = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Other Barangay',
        ]);

        $this->actingAs($this->ib39)->put(route('ib39.areas.update', $area), [
            'barangay_id' => $other->id,
            'effective_date' => '2026-09-02',
            'frs' => 10,
        ])->assertSessionHasErrors('barangay_id');

        $this->assertSame(1, $area->colorHistories()->count());
    }

    public function test_unresolved_legacy_area_cannot_be_claimed_through_the_update_route(): void
    {
        $legacy = MapBarangay::query()->create([
            'municipality' => $this->municipality->name,
            'barangay' => $this->barangay->name,
            'frs' => 5,
            'rebels' => 5,
            'status' => 'Recovery',
            'infestation_color' => 'rgba(0,255,0,0.5)',
        ]);

        $this->actingAs($this->ib39)->put(route('ib39.areas.update', $legacy), [
            'barangay_id' => $this->barangay->id,
            'effective_date' => '2026-09-02',
            'frs' => 10,
        ])->assertSessionHasErrors('barangay_id');

        $this->assertNull($legacy->fresh()->barangay_id);
        $this->assertDatabaseCount('color_histories', 0);
    }

    public function test_remove_preserves_history_and_a_later_entry_reactivates_the_area(): void
    {
        $this->postHistory($this->barangay, 5, '2026-09-01')->assertSessionHasNoErrors();
        $area = MapBarangay::query()->firstOrFail();

        $this->actingAs($this->ib39)
            ->delete(route('ib39.areas.destroy', $area))
            ->assertSessionHasNoErrors();

        $area->refresh();
        $this->assertNull($area->status);
        $this->assertSame(0, $area->frs);
        $this->assertSame(MapBarangay::NEUTRAL_COLOR, $area->infestation_color);
        $this->assertSame(1, $area->colorHistories()->count());
        $inactiveResponse = $this->actingAs($this->ib39)->get(route('ib39.areas.index'))->assertOk();
        $this->assertSame(0, $inactiveResponse->viewData('areas')->total());

        $this->postHistory($this->barangay, 10, '2026-09-02')->assertSessionHasNoErrors();

        $area->refresh();
        $this->assertSame('Expansion', $area->status);
        $this->assertSame(10, $area->frs);
        $this->assertSame(2, $area->colorHistories()->count());
        $activeResponse = $this->actingAs($this->ib39)->get(route('ib39.areas.index'))->assertOk();
        $this->assertSame(1, $activeResponse->viewData('areas')->total());
    }

    public function test_dashboard_uses_one_latest_history_per_area_for_counts_and_totals(): void
    {
        app(RcspAreaHistoryService::class)->record($this->barangay, '2026-09-01', 5);
        app(RcspAreaHistoryService::class)->record($this->barangay, '2026-09-02', 12);
        $second = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Second Barangay',
        ]);
        app(RcspAreaHistoryService::class)->record($second, '2026-09-02', 20);

        $response = $this->actingAs($this->ib39)->get(route('ib39.dashboard'))->assertOk();
        $this->assertSame(32, $response->viewData('stats')['total_frs']);
        $this->assertSame(1, $response->viewData('statusCounts')['Expansion']);
        $this->assertSame(1, $response->viewData('statusCounts')['Konsolidado']);
    }

    public function test_forward_migration_backfills_verified_geography_and_preserves_unresolved_rows(): void
    {
        $this->withLegacyMigrationDatabase(function ($migration): void {
            $municipalityId = DB::table('municipalities')->insertGetId(['name' => 'Exact Municipality']);
            $barangayId = DB::table('barangays')->insertGetId([
                'municipality_id' => $municipalityId, 'name' => 'Exact Barangay',
            ]);
            $time = '2026-09-01 10:30:00';
            DB::table('map_barangays')->insert([
                ['id' => 1, 'municipality' => 'Exact Municipality', 'barangay' => 'Exact Barangay', 'frs' => 15,
                    'status' => 'Rekonsilida', 'infestation_color' => 'rgba(255,165,0,0.5)', 'rebels' => 15,
                    'created_at' => $time, 'updated_at' => $time],
                ['id' => 2, 'municipality' => 'Unknown Municipality', 'barangay' => 'Unknown Barangay', 'frs' => 0,
                    'status' => null, 'infestation_color' => MapBarangay::NEUTRAL_COLOR, 'rebels' => 0,
                    'created_at' => $time, 'updated_at' => $time],
            ]);
            DB::table('color_histories')->insert([
                'id' => 10, 'map_barangay_id' => 1, 'status' => 'Rekonsilida', 'color' => 'rgba(255,165,0,0.5)',
                'frs' => 15, 'created_at' => $time, 'updated_at' => $time,
            ]);
            DB::statement('CREATE INDEX legacy_map_location_index ON map_barangays (municipality, barangay)');
            DB::statement('CREATE INDEX legacy_history_status_index ON color_histories (status)');
            DB::statement('CREATE TRIGGER legacy_map_trigger AFTER UPDATE OF fid ON map_barangays BEGIN SELECT 1; END');

            $migration->up();

            $this->assertSame([
                'id', 'fid', 'province', 'municipality', 'barangay', 'frs', 'status',
                'infestation_color', 'rebels', 'created_at', 'updated_at', 'barangay_id',
            ], Schema::getColumnListing('map_barangays'));
            $this->assertSame([
                'id', 'map_barangay_id', 'status', 'color', 'frs', 'created_at', 'updated_at', 'effective_date',
            ], Schema::getColumnListing('color_histories'));
            $this->assertSame($barangayId, DB::table('map_barangays')->where('id', 1)->value('barangay_id'));
            $this->assertNull(DB::table('map_barangays')->where('id', 2)->value('barangay_id'));
            $this->assertSame('Rekonsilido', DB::table('map_barangays')->where('id', 1)->value('status'));
            $this->assertSame('Rekonsilido', DB::table('color_histories')->value('status'));
            $this->assertSame('2026-09-01', DB::table('color_histories')->value('effective_date'));
            $this->assertSame([1, 2], DB::table('map_barangays')->orderBy('id')->pluck('id')->all());
            $this->assertSame([10], DB::table('color_histories')->pluck('id')->all());
            $this->assertSame(2, DB::table('map_barangays')->count());
            $this->assertSame(1, DB::table('color_histories')->count());
            $this->assertSame('ok', DB::scalar('PRAGMA quick_check'));
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

            $mapIndexes = collect(DB::select('PRAGMA index_list("map_barangays")'))->pluck('name')->all();
            $historyIndexes = collect(DB::select('PRAGMA index_list("color_histories")'))->pluck('name')->all();
            $this->assertContains('legacy_map_location_index', $mapIndexes);
            $this->assertContains('map_barangays_barangay_id_unique', $mapIndexes);
            $this->assertContains('legacy_history_status_index', $historyIndexes);
            $this->assertContains('color_histories_current_index', $historyIndexes);
            $this->assertSame(1, DB::table('sqlite_master')->where('type', 'trigger')
                ->where('name', 'legacy_map_trigger')->count());
            $this->assertSame(0, DB::table('sqlite_master')->where('type', 'table')
                ->whereIn('name', ['__temp__map_barangays', '__temp__color_histories'])->count());

            try {
                $migration->down();
                $this->fail('Rollback should refuse to discard canonical and effective-date data.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('rollback refused', $exception->getMessage());
            }
        });
    }

    public function test_migration_rejects_ambiguous_canonical_geography_before_schema_mutation(): void
    {
        $this->withLegacyMigrationDatabase(function ($migration): void {
            $municipalityId = DB::table('municipalities')->insertGetId(['name' => 'Ambiguous Municipality']);
            DB::table('barangays')->insert([
                ['municipality_id' => $municipalityId, 'name' => 'Same Barangay'],
                ['municipality_id' => $municipalityId, 'name' => ' same   barangay '],
            ]);

            try {
                $migration->up();
                $this->fail('Ambiguous canonical geography should abort the migration.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('ambiguous', $exception->getMessage());
            }

            $this->assertFalse(Schema::hasColumn('map_barangays', 'barangay_id'));
            $this->assertFalse(Schema::hasColumn('color_histories', 'effective_date'));
        });
    }

    public function test_migration_rejects_duplicate_map_assignment_before_schema_mutation(): void
    {
        $this->withLegacyMigrationDatabase(function ($migration): void {
            $municipalityId = DB::table('municipalities')->insertGetId(['name' => 'Duplicate Municipality']);
            DB::table('barangays')->insert([
                'municipality_id' => $municipalityId, 'name' => 'Duplicate Barangay',
            ]);
            DB::table('map_barangays')->insert([
                ['municipality' => 'Duplicate Municipality', 'barangay' => 'Duplicate Barangay'],
                ['municipality' => ' duplicate municipality ', 'barangay' => ' duplicate barangay '],
            ]);

            try {
                $migration->up();
                $this->fail('Duplicate map assignments should abort the migration.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('match the same canonical barangay', $exception->getMessage());
            }

            $this->assertFalse(Schema::hasColumn('map_barangays', 'barangay_id'));
            $this->assertFalse(Schema::hasColumn('color_histories', 'effective_date'));
        });
    }

    public function test_migration_rejects_missing_history_date_before_schema_mutation(): void
    {
        $this->withLegacyMigrationDatabase(function ($migration): void {
            $areaId = DB::table('map_barangays')->insertGetId(['municipality' => 'Unknown', 'barangay' => 'Unknown']);
            DB::table('color_histories')->insert(['map_barangay_id' => $areaId, 'created_at' => null]);

            try {
                $migration->up();
                $this->fail('A missing history timestamp should abort the migration.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('no created_at', $exception->getMessage());
            }

            $this->assertFalse(Schema::hasColumn('map_barangays', 'barangay_id'));
            $this->assertFalse(Schema::hasColumn('color_histories', 'effective_date'));
        });
    }

    public function test_migration_rejects_active_area_without_history_before_schema_mutation(): void
    {
        $this->withLegacyMigrationDatabase(function ($migration): void {
            DB::table('map_barangays')->insert([
                'municipality' => 'Unknown', 'barangay' => 'Unknown', 'status' => 'Recovery',
            ]);

            try {
                $migration->up();
                $this->fail('An active area without history should abort the migration.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('has no history', $exception->getMessage());
            }

            $this->assertFalse(Schema::hasColumn('map_barangays', 'barangay_id'));
            $this->assertFalse(Schema::hasColumn('color_histories', 'effective_date'));
        });
    }

    public function test_sqlite_failure_after_schema_changes_rolls_back_every_change(): void
    {
        $this->withLegacyMigrationDatabase(function ($migration): void {
            $time = '2026-09-01 10:30:00';
            $areaId = DB::table('map_barangays')->insertGetId([
                'municipality' => 'Unknown', 'barangay' => 'Unknown', 'frs' => 15, 'rebels' => 15,
                'status' => 'Rekonsilida', 'infestation_color' => 'rgba(255,165,0,0.5)',
                'created_at' => $time, 'updated_at' => $time,
            ]);
            DB::table('color_histories')->insert([
                'map_barangay_id' => $areaId, 'status' => 'Rekonsilida', 'color' => 'rgba(255,165,0,0.5)',
                'frs' => 15, 'created_at' => $time, 'updated_at' => $time,
            ]);
            DB::statement(<<<'SQL'
                CREATE TRIGGER force_rcsp_migration_failure
                BEFORE UPDATE OF status ON color_histories
                WHEN NEW.status = 'Rekonsilido'
                BEGIN
                    SELECT RAISE(ABORT, 'forced migration failure');
                END
            SQL);

            try {
                $migration->up();
                $this->fail('The deliberate post-schema failure should abort the migration.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('forced migration failure', $exception->getMessage());
            }

            $this->assertFalse(Schema::hasColumn('map_barangays', 'barangay_id'));
            $this->assertFalse(Schema::hasColumn('color_histories', 'effective_date'));
            $this->assertSame('Rekonsilida', DB::table('map_barangays')->value('status'));
            $this->assertSame('Rekonsilida', DB::table('color_histories')->value('status'));
            $this->assertSame(1, DB::table('sqlite_master')->where('name', 'force_rcsp_migration_failure')->count());
        });
    }

    public function test_sqlite_schema_mismatch_fails_before_mutation(): void
    {
        $this->withLegacyMigrationDatabase(function ($migration): void {
            Schema::table('map_barangays', fn (Blueprint $table) => $table->string('unexpected_column')->nullable());

            try {
                $migration->up();
                $this->fail('An unknown production column should abort the migration.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('schema differs', $exception->getMessage());
            }

            $this->assertFalse(Schema::hasColumn('map_barangays', 'barangay_id'));
            $this->assertFalse(Schema::hasColumn('color_histories', 'effective_date'));
            $this->assertTrue(Schema::hasColumn('map_barangays', 'unexpected_column'));
        });
    }

    private function postHistory(Barangay $barangay, int|string $frs, string $effectiveDate)
    {
        return $this->actingAs($this->ib39)->post(route('ib39.areas.store'), [
            'barangay_id' => $barangay->id,
            'effective_date' => $effectiveDate,
            'frs' => $frs,
        ]);
    }

    private function withLegacyMigrationDatabase(\Closure $test): void
    {
        $original = config('database.default');
        config(['database.default' => 'rcsp_migration_test']);
        config(['database.connections.rcsp_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('rcsp_migration_test');

        try {
            Schema::create('municipalities', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
            Schema::create('barangays', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('municipality_id')->constrained();
                $table->string('name');
            });
            Schema::create('map_barangays', function (Blueprint $table): void {
                $table->id();
                $table->string('fid')->nullable();
                $table->string('province')->nullable();
                $table->string('municipality')->nullable();
                $table->string('barangay')->nullable();
                $table->integer('frs')->default(0);
                $table->string('status')->nullable();
                $table->string('infestation_color')->nullable();
                $table->integer('rebels')->default(0);
                $table->timestamps();
            });
            Schema::create('color_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('map_barangay_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('status')->nullable();
                $table->string('color')->nullable();
                $table->integer('frs')->nullable();
                $table->timestamps();
            });

            $migration = require database_path('migrations/2026_09_13_000001_harden_ib39_rcsp_area_history.php');
            $test($migration);
        } finally {
            DB::disconnect('rcsp_migration_test');
            config(['database.default' => $original]);
        }
    }
}
