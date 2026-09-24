<?php

namespace Tests\Feature;

use App\Models\{MapBarangay, MapSnapshot, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BoundaryDraftTest extends TestCase
{
    use DatabaseTransactions;

    private function ib39(): User
    {
        return User::where('role', '39th_ib')->firstOrFail();
    }

    private function fcFrom(MapBarangay $a, array $geometry): array
    {
        return ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'geometry' => $geometry,
            'properties' => ['id' => $a->id, 'municipality' => $a->municipality, 'barangay' => $a->barangay],
        ]]];
    }

    private function tri(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[125.3, 6.7], [125.31, 6.7], [125.3, 6.71], [125.3, 6.7]]]];
    }

    public function test_draft_falls_back_to_live_and_creates_the_original_snapshot(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $res = $this->actingAs($this->ib39())->get('/39th-ib/boundaries-draft')->assertOk()->json();

        $this->assertFalse($res['hasDraft']);
        $this->assertSame('FeatureCollection', $res['featureCollection']['type']);
        $this->assertSame(1, MapSnapshot::where('kind', 'original')->count(), 'original captured on first access');
    }

    public function test_editing_saves_to_a_draft_and_does_not_touch_the_live_map(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::whereNotNull('geometry')->firstOrFail();
        $liveBefore = $area->geometry;

        $this->actingAs($this->ib39())
            ->putJson('/39th-ib/boundaries-draft', ['featureCollection' => $this->fcFrom($area, $this->tri())])
            ->assertOk()->assertJson(['saved' => true]);

        $this->assertSame(1, MapSnapshot::where('kind', 'draft')->count());
        // Live geometry unchanged — the whole point of drafting.
        $this->assertEquals($liveBefore, $area->fresh()->geometry);
    }

    public function test_publishing_applies_the_draft_and_checkpoints_the_previous_map(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::whereNotNull('geometry')->firstOrFail();
        $checkpointsBefore = MapSnapshot::where('kind', 'checkpoint')->count();

        $this->actingAs($this->ib39())
            ->putJson('/39th-ib/boundaries-draft', ['featureCollection' => $this->fcFrom($area, $this->tri())]);

        $this->actingAs($this->ib39())->postJson('/39th-ib/boundaries-publish')
            ->assertOk()->assertJson(['published' => true]);

        $this->assertSame('MultiPolygon', $area->fresh()->geometry['type'], 'draft is now live');
        $this->assertSame(0, MapSnapshot::where('kind', 'draft')->count(), 'draft consumed');
        $this->assertSame($checkpointsBefore + 1, MapSnapshot::where('kind', 'checkpoint')->count(), 'previous map checkpointed');
    }

    public function test_discard_removes_the_draft(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::whereNotNull('geometry')->firstOrFail();
        $liveBefore = $area->geometry;

        $this->actingAs($this->ib39())
            ->putJson('/39th-ib/boundaries-draft', ['featureCollection' => $this->fcFrom($area, $this->tri())]);

        $this->actingAs($this->ib39())->deleteJson('/39th-ib/boundaries-draft')->assertOk();

        $this->assertSame(0, MapSnapshot::where('kind', 'draft')->count());
        // The live map was never touched by the draft, so it still equals the original.
        $this->assertEquals($liveBefore, $area->fresh()->geometry);
    }

    public function test_restore_rolls_the_live_map_back_to_a_snapshot(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::whereNotNull('geometry')->firstOrFail();

        // checkpoint the pristine map
        $snap = $this->actingAs($this->ib39())->postJson('/39th-ib/boundaries-snapshots', ['label' => 'Pristine'])
            ->assertCreated()->json();

        // publish an edit so the live map changes
        $this->actingAs($this->ib39())
            ->putJson('/39th-ib/boundaries-draft', ['featureCollection' => $this->fcFrom($area, $this->tri())]);
        $this->actingAs($this->ib39())->postJson('/39th-ib/boundaries-publish');
        $edited = $area->fresh()->geometry;

        // restore the pristine checkpoint
        $this->actingAs($this->ib39())->postJson("/39th-ib/boundaries-snapshots/{$snap['id']}/restore")
            ->assertOk()->assertJson(['restored' => true]);

        $this->assertNotEquals($edited, $area->fresh()->geometry, 'geometry rolled back');
    }

    public function test_other_roles_cannot_use_the_draft_endpoints(): void
    {
        $this->skipUnlessLegacyDataPresent();
        $mblrc = User::where('role', 'mblrc')->firstOrFail();
        $this->actingAs($mblrc)->get('/39th-ib/boundaries-draft')->assertForbidden();
    }
}
