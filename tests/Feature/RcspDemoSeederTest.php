<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use App\Models\User;
use Database\Seeders\RcspDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RcspDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('RCSP_DEMO_PASSWORD');
        parent::tearDown();
    }

    public function test_seeder_refuses_production_before_transaction(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(RcspDemoSeeder::class)->run();
    }

    public function test_seeder_requires_a_strong_password(): void
    {
        putenv('RCSP_DEMO_PASSWORD');
        try {
            $this->app->make(RcspDemoSeeder::class)->run();
            $this->fail('Seeder accepted a missing password.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('RCSP_DEMO_PASSWORD', $exception->getMessage());
        }
        putenv('RCSP_DEMO_PASSWORD=weak');
        $this->expectException(RuntimeException::class);
        $this->seed(RcspDemoSeeder::class);
    }

    public function test_seeder_is_idempotent_preserves_unrelated_data_and_builds_consistent_states(): void
    {
        putenv('RCSP_DEMO_PASSWORD=StrongDemo12345');
        $unrelated = Municipality::create(['name' => 'Unrelated Municipality']);
        $this->seed(RcspDemoSeeder::class);
        $counts = [Municipality::count(), User::count(), RcspBarangay::count(), RcspForm::count()];
        $this->seed(RcspDemoSeeder::class);
        $this->assertSame($counts, [Municipality::count(), User::count(), RcspBarangay::count(), RcspForm::count()]);
        $this->assertSame('Unrelated Municipality', $unrelated->fresh()->name);

        $records = RcspBarangay::where('catalog_key', RcspDemoSeeder::CATALOG_KEY)->with('barangay', 'phaseStatus', 'forms')->get()->keyBy(fn ($r) => $r->barangay->name);
        $this->assertCount(4, $records);
        $this->assertSame('Pending', $records['DEMO Pending Barangay']->status);
        $this->assertCount(0, $records['DEMO Pending Barangay']->forms);
        $this->assertSame(['submitted'], $records['DEMO Submitted Barangay']->forms->pluck('status')->unique()->values()->all());
        $this->assertCount(3, $records['DEMO Submitted Barangay']->forms);
        $this->assertSame(3, $records['DEMO In-Progress Barangay']->current_phase);
        $this->assertCount(12, $records['DEMO In-Progress Barangay']->forms);
        $completed = $records['DEMO Completed Barangay'];
        $this->assertSame('Completed', $completed->status);
        $this->assertSame(5, $completed->current_phase);
        $this->assertCount(18, $completed->forms);
        $this->assertTrue($completed->forms->every(fn ($form) => $form->status === 'approved' && $form->reviewed_by_user_id && $form->reviewed_at));
        foreach (range(0, 5) as $phase) {
            $this->assertTrue($completed->phaseStatus->{"phase{$phase}_completed"});
        }
        $this->assertDatabaseMissing('rcsp_barangays', ['barangay_id' => $records->first()->barangay->municipality->barangays()->where('name', 'DEMO Manual Workflow Barangay')->value('id')]);
    }

    public function test_seeder_refuses_conflicting_demo_username(): void
    {
        User::factory()->role('afp')->create(['username' => 'katuparan_demo', 'name' => 'Not Demo Reviewer']);
        putenv('RCSP_DEMO_PASSWORD=StrongDemo12345');
        $this->expectException(RuntimeException::class);
        $this->seed(RcspDemoSeeder::class);
    }

    public function test_new_registration_never_infers_or_copies_a_demo_catalog(): void
    {
        $municipality = Municipality::create(['name' => 'DEMO Municipality']);
        $barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'DEMO New Barangay']);
        $lgu = User::factory()->lgu($municipality->id)->create();

        $this->actingAs($lgu)->post(route('lgu.rcsp.store'), [
            'barangay_id' => $barangay->id,
        ])->assertSessionHasNoErrors();

        $record = RcspBarangay::where('barangay_id', $barangay->id)->firstOrFail();
        $this->assertSame(RcspPhase::CONFIGURABLE_CATALOG_KEY, $record->catalog_key);
        $phase = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->where('number', 0)->firstOrFail();
        $this->assertSame(0, RcspActivity::forBarangayPhase($record, $phase)->count());
    }
}
