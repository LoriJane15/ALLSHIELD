<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use App\Models\User;
use Database\Seeders\DavaoDelSurBarangaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LguRcspBarangayRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $demoMunicipality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->demoMunicipality = Municipality::create(['name' => 'DEMO Existing Municipality']);
        foreach (range(1, 5) as $number) {
            Barangay::create([
                'municipality_id' => $this->demoMunicipality->id,
                'name' => "DEMO Existing Barangay {$number}",
            ]);
        }
        foreach (config('shield.jurisdiction.municipalities') as $name) {
            Municipality::create(['name' => $name]);
        }

        $this->seed(DavaoDelSurBarangaySeeder::class);
    }

    public function test_checked_in_geography_populates_every_supported_municipality_idempotently(): void
    {
        $this->assertSame(25, $this->municipality('Sulop')->barangays()->count());
        $this->assertSame(33, $this->municipality('Matanao')->barangays()->count());
        $this->assertSame(232, Barangay::query()
            ->whereIn('municipality_id', Municipality::query()
                ->whereIn('name', config('shield.jurisdiction.municipalities'))->select('id'))
            ->count());

        $existing = $this->demoMunicipality->barangays()->firstOrFail();
        $record = RcspBarangay::create([
            'municipality_id' => $this->demoMunicipality->id,
            'barangay_id' => $existing->id,
        ]);

        $this->seed(DavaoDelSurBarangaySeeder::class);

        $this->assertSame(237, Barangay::count());
        $this->assertDatabaseHas('rcsp_barangays', ['id' => $record->id, 'barangay_id' => $existing->id]);
    }

    public function test_seeder_rejects_when_every_configured_municipality_is_only_partially_populated(): void
    {
        $municipalities = Municipality::query()
            ->whereIn('name', config('shield.jurisdiction.municipalities'))
            ->get();

        foreach ($municipalities as $municipality) {
            $firstBarangayId = $municipality->barangays()->orderBy('id')->value('id');
            $municipality->barangays()->whereKeyNot($firstBarangayId)->delete();
        }

        $this->assertSame(10, Barangay::query()
            ->whereIn('municipality_id', $municipalities->pluck('id'))
            ->count());
        $before = Barangay::query()->orderBy('id')->get(['id', 'municipality_id', 'name'])->toArray();

        try {
            $this->seed(DavaoDelSurBarangaySeeder::class);
            $this->fail('The seeder accepted incomplete barangay data for every configured municipality.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('partially populated or conflict', $exception->getMessage());
        }

        $this->assertSame($before, Barangay::query()->orderBy('id')->get(['id', 'municipality_id', 'name'])->toArray());
        $this->assertSame(5, $this->demoMunicipality->barangays()->count());
    }

    public function test_registration_dropdown_is_scoped_to_each_authenticated_lgu_municipality(): void
    {
        $sulop = $this->municipality('Sulop');
        $matanao = $this->municipality('Matanao');
        $sulopBarangay = $sulop->barangays()->where('name', 'Balasinon')->firstOrFail();
        $matanaoBarangay = $matanao->barangays()
            ->whereNotIn('name', $sulop->barangays()->pluck('name'))
            ->orderBy('name')->firstOrFail();

        $this->actingAs(User::factory()->lgu($sulop->id)->create())
            ->get(route('lgu.rcsp.index'))
            ->assertOk()
            ->assertSee('value="'.$sulopBarangay->id.'"', false)
            ->assertSee($sulopBarangay->name)
            ->assertDontSee('value="'.$matanaoBarangay->id.'"', false);

        $this->actingAs(User::factory()->lgu($matanao->id)->create())
            ->get(route('lgu.rcsp.index'))
            ->assertOk()
            ->assertSee('value="'.$matanaoBarangay->id.'"', false)
            ->assertSee($matanaoBarangay->name)
            ->assertDontSee('value="'.$sulopBarangay->id.'"', false);
    }

    public function test_registration_persists_an_in_scope_barangay_and_rejects_a_forged_cross_municipality_id(): void
    {
        $sulop = $this->municipality('Sulop');
        $matanao = $this->municipality('Matanao');
        $sulopBarangay = $sulop->barangays()->where('name', 'Balasinon')->firstOrFail();
        $matanaoBarangay = $matanao->barangays()->firstOrFail();
        $user = User::factory()->lgu($sulop->id)->create();

        $this->actingAs($user)->post(route('lgu.rcsp.store'), [
            'barangay_id' => $sulopBarangay->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('rcsp_barangays', [
            'barangay_id' => $sulopBarangay->id,
            'municipality_id' => $sulop->id,
        ]);
        $this->assertSame($sulop->id, $user->fresh()->municipality_id);

        $this->actingAs($user)->post(route('lgu.rcsp.store'), [
            'barangay_id' => $matanaoBarangay->id,
        ])->assertSessionHasErrors('barangay_id');

        $this->assertDatabaseMissing('rcsp_barangays', [
            'barangay_id' => $matanaoBarangay->id,
        ]);
        $this->assertSame(1, RcspBarangay::count());
    }

    private function municipality(string $name): Municipality
    {
        return Municipality::query()->where('name', $name)->firstOrFail();
    }
}
