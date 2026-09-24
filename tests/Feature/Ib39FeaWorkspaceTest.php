<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ib39FeaWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_and_workspace_are_limited_to_active_39th_ib_users(): void
    {
        $processing = $this->processing();
        $routes = [route('ib39.fea.index'), route('ib39.fea.show', $processing)];

        foreach ($routes as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        foreach ($routes as $url) {
            $this->actingAs($inactive)->get($url)->assertRedirect(route('login'));
        }

        foreach ($this->otherRoles() as $role) {
            $user = User::factory()->role($role)->create();
            foreach ($routes as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }

        $actor = User::factory()->role('39th_ib')->create();
        foreach ($routes as $url) {
            $this->actingAs($actor)->get($url)->assertOk();
        }
    }

    public function test_queue_displays_approved_columns_status_and_navigation_entry(): void
    {
        $processing = $this->processing();
        $actor = User::factory()->role('39th_ib')->create();

        $this->actingAs($actor)->get(route('ib39.fea.index'))
            ->assertOk()
            ->assertSee('FEA Processing')
            ->assertSee('FR Reference')
            ->assertSee('Pending')
            ->assertSee('View FEA Record')
            ->assertSee('FEA processing is unavailable until the required PSWDO enrollment forms are completed.')
            ->assertSee(route('ib39.fea.show', $processing));
    }

    public function test_workspace_lists_six_requirements_with_read_only_controls_while_locked(): void
    {
        $processing = $this->processing();
        $actor = User::factory()->role('39th_ib')->create();
        $response = $this->actingAs($actor)->get(route('ib39.fea.show', $processing))->assertOk();

        $response->assertSee('Pending')
            ->assertSee('Technical Inspection Report')
            ->assertSee('Cost Valuation of Inventoried Firearms')
            ->assertSee('Property Turn-In Slip')
            ->assertSee('Justification on the TIR and CVC/CVIF')
            ->assertSee('Photograph of the firearm')
            ->assertSee('Photograph of the FR with the firearm')
            ->assertSee('FEA processing is unavailable until the required PSWDO enrollment forms are completed.')
            ->assertSee('Preview Saved Draft')
            ->assertSee('View Upload History')
            ->assertDontSee('Open Official Form Editor')
            ->assertDontSee('Start Preliminary Work')
            ->assertDontSee('Update Preliminary Work')
            ->assertDontSee('Upload Final TIR')
            ->assertDontSee('Upload Final CVIF')
            ->assertDontSee('Upload Final PTIS')
            ->assertDontSee('Upload Final Justification Form')
            ->assertDontSee('Upload Photo — Photograph of the firearm')
            ->assertDontSee('Upload Photo — Photograph of the FR with the firearm')
            ->assertDontSee('Existing Private Draft Versions — DRAFT — NOT FINAL')
            ->assertDontSee('Existing draft uploads and their immutable histories remain available.')
            ->assertDontSee('No private draft file uploaded.')
            ->assertDontSee('This photograph requirement does not have a text-form editor.')
            ->assertDontSee('Private Photo Upload — DRAFT — NOT FINAL')
            ->assertDontSee('JPEG or PNG only. Maximum 20 MiB. Files are stored privately as immutable versions.')
            ->assertDontSee('No private photo uploaded.')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('type="button" disabled', false);
        $this->assertSame(6, $processing->documents()->count());
    }

    public function test_soft_deleted_parent_is_absent_from_queue_and_workspace_is_denied(): void
    {
        $processing = $this->processing();
        $processing->surfacedFormerRebel->delete();
        $actor = User::factory()->role('39th_ib')->create();

        $this->actingAs($actor)->get(route('ib39.fea.index'))
            ->assertOk()
            ->assertDontSee($processing->surfacedFormerRebel->reference_number);
        $this->actingAs($actor)->get(route('ib39.fea.show', $processing))->assertForbidden();
    }

    private function processing()
    {
        $municipality = Municipality::query()->create(['name' => 'FEA Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'FEA Barangay']);
        $actor = User::factory()->role('39th_ib')->create();
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic',
            'last_name' => 'Workspace',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30',
            'possessed_firearms' => true,
        ], $actor);

        return $record->feaProcessing()->firstOrFail();
    }

    private function otherRoles(): array
    {
        return [
            'super_admin', 'admin', 'lgu', 'gov_agency', 'mblrc', 'afp',
        ];
    }
}
