<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaDocumentHistoryEvent;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ib39FeaPreliminaryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_two_actions_reject_guests_inactive_users_and_every_other_role(): void
    {
        [, $processing, $document] = $this->context();
        $start = route('ib39.fea.documents.start', [$processing, $document]);
        $update = route('ib39.fea.documents.update', [$processing, $document]);
        $this->post($start)->assertRedirect(route('login'));
        $this->patch($update, $this->payload())->assertRedirect(route('login'));

        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->actingAs($inactive)->post($start)->assertRedirect(route('login'));
        $this->actingAs($inactive)->patch($update, $this->payload())->assertRedirect(route('login'));

        foreach ($this->otherRoles() as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->post($start)->assertForbidden();
            $this->actingAs($user)->patch($update, $this->payload())->assertForbidden();
        }

        $active = User::factory()->role('39th_ib')->create();
        $this->actingAs($active)->post($start)->assertForbidden();
        $this->actingAs($active)->patch($update, $this->payload())->assertForbidden();
        $this->assertDatabaseCount('ib39_fea_document_histories', 0);
    }

    public function test_cross_processing_substitution_and_soft_deleted_parent_are_denied(): void
    {
        [$actor, $processing, $document] = $this->context();
        [, $otherProcessing, $otherDocument] = $this->context();

        $this->actingAs($actor)->post(route('ib39.fea.documents.start', [$processing, $otherDocument]))->assertForbidden();
        $this->actingAs($actor)->patch(route('ib39.fea.documents.update', [$processing, $otherDocument]), $this->payload())->assertForbidden();

        $processing->surfacedFormerRebel->delete();
        $this->actingAs($actor)->post(route('ib39.fea.documents.start', [$processing, $document]))->assertForbidden();
        $this->actingAs($actor)->patch(route('ib39.fea.documents.update', [$processing, $document]), $this->payload())->assertForbidden();
        $this->actingAs($actor)->get(route('ib39.fea.show', $processing))->assertForbidden();
        $this->assertDatabaseCount('ib39_fea_document_histories', 0);
    }

    public function test_workspace_escapes_metadata_and_displays_safe_actor_names_and_history_fallback(): void
    {
        [$actor, $processing, $document] = $this->context();
        $script = '<script>alert("unsafe")</script>';
        $document->update([
            'remarks' => $script, 'compliance_status' => 'Has Issue',
            'compliance_reason' => $script, 'is_delayed' => true, 'delay_reason' => $script,
            'prepared_by' => $actor->id, 'last_updated_by' => $actor->id,
        ]);
        $document->histories()->create([
            'fea_processing_id' => $processing->id,
            'user_id' => null,
            'event' => Ib39FeaDocumentHistoryEvent::RemarksChanged,
            'previous_values' => [],
            'new_values' => [],
        ]);

        $response = $this->actingAs($actor)->get(route('ib39.fea.show', $processing))->assertOk();
        $response->assertSee('Safe Authorization Actor')
            ->assertSee('User unavailable')
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee($script, false)
            ->assertDontSee('type="file"', false)
            ->assertSee('View Upload History')
            ->assertDontSee('Upload Final CVIF')
            ->assertDontSee('Open Official Form Editor')
            ->assertSee(route('ib39.fea.documents.draft.preview', [$processing, $document]))
            ->assertDontSee('Final Copy');
    }

    private function context(): array
    {
        $actor = User::factory()->role('39th_ib')->create(['name' => 'Safe Authorization Actor']);
        $municipality = Municipality::query()->create(['name' => fake()->unique()->city()]);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => fake()->unique()->streetName()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Authorization',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30', 'possessed_firearms' => true,
        ], $actor);
        $processing = $record->feaProcessing()->firstOrFail();

        return [$actor, $processing, $processing->documents()->firstOrFail()];
    }

    private function payload(array $overrides = []): array
    {
        return ['document' => [
            'status' => 'Processing', 'compliance_status' => 'None',
            'remarks' => null, 'compliance_reason' => null,
            'is_delayed' => false, 'delay_reason' => null,
            ...$overrides,
        ]];
    }

    private function otherRoles(): array
    {
        return [
            'super_admin', 'admin', 'lgu', 'gov_agency', 'mblrc', 'afp',
        ];
    }
}
