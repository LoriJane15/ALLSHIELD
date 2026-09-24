<?php

namespace Tests\Feature;

use App\Models\{MapBarangay, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Ib39AreasTest extends TestCase
{
    // These write to the real imported dataset; roll back rather than leave state behind.
    use DatabaseTransactions;

    private function ib39(): User
    {
        return User::where('role', '39th_ib')->firstOrFail();
    }

    public function test_page_lists_only_barangays_already_in_rcsp(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $html = $this->actingAs($this->ib39())->get('/39th-ib/areas')->assertOk()->getContent();

        foreach (['RCSP Barangays', 'addRCSPButton', 'Add New RCSP', 'Update RCSP Data',
                  'municipality-select', 'barangay-select', 'frRangeFilter', 'resultsCount'] as $needle) {
            $this->assertStringContainsString($needle, $html, "missing: $needle");
        }

        // Legacy listed only `frs > 0`; barangays with no count must not appear.
        $withCount = MapBarangay::where('frs', '>', 0)->count();
        $this->assertGreaterThan(0, $withCount);
        $this->assertSame($withCount, MapBarangay::where('frs', '>', 0)->count());
    }

    public function test_barangay_cascade_returns_names_for_a_municipality(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $names = $this->actingAs($this->ib39())
            ->get('/39th-ib/barangays?municipality=Digos City')
            ->assertOk()->json();

        $this->assertNotEmpty($names);
        $this->assertContains('Aplaya', $names);
    }

    public function test_adding_an_area_sets_status_colour_and_logs_history(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::where('frs', 0)->firstOrFail();
        $before = $area->colorHistories()->count();

        $this->actingAs($this->ib39())->post('/39th-ib/areas', [
            'municipality' => $area->municipality,
            'barangay' => $area->barangay,
            'frs' => 22,
        ])->assertRedirect(route('ib39.areas.index'));

        $area->refresh();
        $this->assertSame(22, (int) $area->frs);
        $this->assertSame('Konsolidado', $area->status);
        $this->assertSame('rgba(255,0,0,0.5)', $area->infestation_color);
        $this->assertSame($before + 1, $area->colorHistories()->count());
    }

    public function test_removing_an_area_resets_it_without_deleting_the_row(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $area = MapBarangay::where('frs', '>', 0)->firstOrFail();
        $id = $area->id;

        $this->actingAs($this->ib39())->delete("/39th-ib/areas/{$id}")->assertRedirect();

        $area->refresh();
        $this->assertNotNull(MapBarangay::find($id), 'row must survive — legacy only reset it');
        $this->assertSame(0, (int) $area->frs);
        $this->assertNull($area->status);
        $this->assertSame('rgba(190,178,151,0.1)', $area->infestation_color);
    }

    public function test_adding_an_unknown_barangay_is_rejected(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $this->actingAs($this->ib39())
            ->post('/39th-ib/areas', ['municipality' => 'Nowhere', 'barangay' => 'Nothing', 'frs' => 5])
            ->assertRedirect();

        $this->assertNull(MapBarangay::where('barangay', 'Nothing')->first());
    }

    /**
     * Modals must be opened by Bootstrap's own data-bs-toggle. Opening them from
     * JS depended on a `window.bootstrap` global and silently did nothing.
     */
    public function test_edit_and_add_buttons_are_declaratively_wired(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $html = $this->actingAs($this->ib39())->get('/39th-ib/areas')->assertOk()->getContent();

        $this->assertStringContainsString('data-bs-target="#addModal"', $html);
        $this->assertStringContainsString('data-bs-target="#editModal"', $html);

        // Every Edit button carries the row data the modal prefills from.
        foreach (['data-action=', 'data-frs=', 'data-barangay=', 'data-municipality='] as $attr) {
            $this->assertStringContainsString($attr, $html, "Edit button missing $attr");
        }
    }

    /**
     * Modals use the legacy add_rcsp.php chrome, not Bootstrap's modal-header,
     * whose SkyDash styling is a solid purple bar that hid the dark title text.
     */
    public function test_modals_use_the_legacy_chrome(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $html = $this->actingAs($this->ib39())->get('/39th-ib/areas')->assertOk()->getContent();

        $this->assertStringContainsString('ib39-areas.css', $html);
        $this->assertStringContainsString('ib39-modal-title is-add', $html);
        $this->assertStringContainsString('ib39-modal-title is-edit', $html);
        $this->assertStringContainsString('ib39-submit-btn', $html);

        // No Bootstrap modal-header / modal-footer inside these two dialogs.
        $this->assertStringNotContainsString('<div class="modal-header">', $html);
        $this->assertStringNotContainsString('<div class="modal-footer">', $html);
    }

    /** Action buttons use the legacy icon-only edit/delete styling. */
    public function test_action_buttons_match_the_legacy_styling(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $html = $this->actingAs($this->ib39())->get('/39th-ib/areas')->assertOk()->getContent();

        $this->assertStringContainsString('ib39-action-buttons', $html);
        $this->assertStringContainsString('ib39-edit-btn', $html);
        $this->assertStringContainsString('ib39-delete-btn', $html);

        // SkyDash ships Font Awesome 4, so FA5-only class names would render nothing.
        $this->assertStringContainsString('fa fa-pencil-square-o', $html);
        $this->assertStringContainsString('fa fa-trash-o', $html);
        $this->assertStringNotContainsString('fa-trash-alt', $html);
        $this->assertStringNotContainsString('fas fa-', $html);
    }
}
