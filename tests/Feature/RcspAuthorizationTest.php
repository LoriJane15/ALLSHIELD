<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RcspAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lgu_requires_municipality_and_is_scoped(): void
    {
        $municipality = Municipality::create(['name' => 'DEMO Auth Municipality']);
        $barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'DEMO Auth Barangay']);
        $record = RcspBarangay::create(['municipality_id' => $municipality->id, 'barangay_id' => $barangay->id]);
        $phase = RcspPhase::create(['number' => 0, 'name' => 'DEMO Auth Phase']);
        RcspActivity::create(['rcsp_phase_id' => $phase->id, 'description' => 'DEMO: Auth activity']);
        $this->actingAs(User::factory()->lgu()->create())->get('/lgu/rcsp')->assertForbidden();
        $other = Municipality::create(['name' => 'DEMO Other Municipality']);
        $this->actingAs(User::factory()->lgu($other->id)->create())->get("/lgu/rcsp/{$record->id}/monitoring")->assertForbidden();
        $this->actingAs(User::factory()->lgu($municipality->id)->create())->get("/lgu/rcsp/{$record->id}/monitoring")->assertOk();
    }

    public function test_other_roles_cannot_mutate_rcsp(): void
    {
        foreach (['afp', 'super_admin', '39th_ib', 'gov_agency', 'mblrc'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->post('/lgu/rcsp', ['barangay_id' => 1])->assertForbidden();
        }
    }
}
