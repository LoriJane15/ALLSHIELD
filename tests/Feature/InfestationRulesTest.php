<?php

namespace Tests\Feature;

use App\Models\{InfestationRule, MapBarangay, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InfestationRulesTest extends TestCase
{
    use DatabaseTransactions;

    private function ib39(): User
    {
        return User::where('role', '39th_ib')->firstOrFail();
    }

    protected function tearDown(): void
    {
        MapBarangay::forgetRuleCache();
        parent::tearDown();
    }

    public function test_rules_page_lists_the_current_bands(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $html = $this->actingAs($this->ib39())->get('/39th-ib/rules')->assertOk()->getContent();
        $this->assertStringContainsString('Infestation Rules', $html);
        $this->assertStringContainsString('Konsolidado', $html);
        $this->assertStringContainsString('rules[0][min_frs]', $html);
    }

    public function test_classify_reads_from_the_editable_rules(): void
    {
        $this->skipUnlessLegacyDataPresent();
        MapBarangay::forgetRuleCache();

        $this->assertSame('Konsolidado', MapBarangay::classify(20)['status']);
        $this->assertSame('Recovery', MapBarangay::classify(3)['status']);
    }

    public function test_saving_rules_replaces_them_and_reclassifies_barangays(): void
    {
        $this->skipUnlessLegacyDataPresent();

        // A barangay currently classified Expansion (10-14).
        $bgy = MapBarangay::where('frs', '>=', 10)->where('frs', '<', 15)->first();
        if (! $bgy) {
            $this->markTestSkipped('No barangay in the 10-14 band to test with.');
        }

        // New rules: everything with >=1 FR is "Hotspot" (single band).
        $this->actingAs($this->ib39())->put('/39th-ib/rules', [
            'rules' => [
                ['min_frs' => 1, 'status' => 'Hotspot', 'color' => 'rgba(200,0,0,0.6)', 'label' => 'Any FRs'],
                ['min_frs' => 0, 'status' => 'Clear', 'color' => 'rgba(0,200,0,0.6)', 'label' => 'None'],
            ],
        ])->assertRedirect();

        $this->assertSame(2, InfestationRule::count());
        MapBarangay::forgetRuleCache();
        $this->assertSame('Hotspot', $bgy->fresh()->status, 'existing barangay re-classified by new rules');
        $this->assertSame('rgba(200,0,0,0.6)', $bgy->fresh()->infestation_color);
    }

    public function test_other_roles_cannot_edit_rules(): void
    {
        $this->skipUnlessLegacyDataPresent();
        $mblrc = User::where('role', 'mblrc')->firstOrFail();
        $this->actingAs($mblrc)->get('/39th-ib/rules')->assertForbidden();
    }
}
