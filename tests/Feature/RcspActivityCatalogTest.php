<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspFileComment;
use App\Models\RcspForm;
use App\Models\RcspFormReview;
use App\Models\RcspPhase;
use App\Models\RcspPhaseTransition;
use App\Models\User;
use App\Support\RcspActivityCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RcspActivityCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_resolves_official_and_legacy_activity_responsibilities(): void
    {
        $expectedByPhase = [
            0 => ['C/MLGOO', 'RCSP Team', 'PLEDS Team', 'RCSP Team', 'C/MPDC'],
            1 => ['C/MLGOO', 'Sanggunian Panlungsod/Bayan', 'C/MLGOO, C/MPDC', 'RCSP Team', 'C/MLGOO', 'RCSP Core Team'],
            2 => ['CCPT', 'RCSP Local Team (Headed)', 'CCPT, RCSP Core Team', 'C/MPDC', 'MPDC, NGAs', 'CCPT'],
            3 => ['Sanggunian Barangay', 'C/MLGOO, C/MPDC, CCPT', 'Sanggunian Barangay', 'LCE'],
            4 => ['CCPT', 'CCPT, PLEDS', 'PLEDS Team', 'PLEDS Team', 'C/M LGU, Province, Barangay'],
            5 => ['C/MPDC, C/MLGOO', 'C/M LGU', 'PLEDS Team', 'CCPT', 'City/Municipal Mayor, C/MLGOO', 'City/Municipal and Barangay RCSP Team', 'City/Municipal Mayor, Barangay RCSP Team'],
        ];

        foreach ($expectedByPhase as $phaseNumber => $expectedResponsibilities) {
            $entries = RcspActivityCatalog::entriesForPhase($phaseNumber);
            $this->assertSame($expectedResponsibilities, array_column($entries, 'responsible_office_person'));
            foreach ($entries as $entry) {
                $this->assertSame(
                    $entry['responsible_office_person'],
                    RcspActivityCatalog::responsibilityFor($phaseNumber, $entry['description'])
                );
                foreach ($entry['accepted_titles'] as $acceptedTitle) {
                    $this->assertSame(
                        $entry['responsible_office_person'],
                        RcspActivityCatalog::responsibilityFor($phaseNumber, strtoupper($acceptedTitle))
                    );
                }
            }
        }

        $this->assertSame(
            'CCPT, PLEDS',
            RcspActivityCatalog::responsibilityFor(
                4,
                '  Conduct an information drive on peace, security and development  '
            )
        );
        $this->assertSame(
            RcspActivityCatalog::UNSPECIFIED_RESPONSIBILITY,
            RcspActivityCatalog::responsibilityFor(2, 'Unmatched legacy activity title')
        );
    }

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
        $this->assertSame(33, $record->activities()->count());
        $this->assertSame([5, 6, 6, 4, 5, 7], array_values(RcspActivityCatalog::phaseCounts()));

        foreach (RcspActivityCatalog::ACTIVITIES as $phaseNumber => $expected) {
            $phase = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                ->where('number', $phaseNumber)->firstOrFail();
            $this->assertSame(
                $expected,
                RcspActivity::forBarangayPhase($record, $phase)
                    ->orderBy('catalog_order')->pluck('description')->all(),
            );
            $this->assertSame(
                array_column(RcspActivityCatalog::entriesForPhase($phaseNumber), 'expected_result'),
                RcspActivity::forBarangayPhase($record, $phase)
                    ->orderBy('catalog_order')->pluck('expected_result')->all(),
            );
        }

        $page = $this->actingAs($lgu)->get(route('lgu.monitoring.show', $record));
        $page->assertOk()
            ->assertDontSeeText('New activity title')
            ->assertDontSeeText('Add Activity')
            ->assertDontSee('>Correct</button>', false)
            ->assertDontSee('data-confirm="Delete this activity?"', false);
        foreach (RcspActivityCatalog::ACTIVITIES[0] as $officialTitle) {
            $page->assertSeeText($officialTitle);
        }

        foreach (range(0, 5) as $phaseNumber) {
            $record->update(['current_phase' => $phaseNumber]);
            $entry = RcspActivityCatalog::entriesForPhase($phaseNumber)[0];
            $activity = $record->activities()
                ->where('rcsp_phase_id', RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                    ->where('number', $phaseNumber)->value('id'))
                ->where('catalog_order', 1)->firstOrFail();
            $html = $this->actingAs($lgu)->get(route('lgu.monitoring.show', $record))
                ->assertOk()
                ->assertSeeText('Expected Result/Output')
                ->assertSeeText($entry['expected_result'])
                ->assertDontSeeText('Upload a document or photo that clearly proves this result was achieved.')
                ->assertDontSee('expected-result-panel', false)
                ->assertDontSee('border-left: 4px solid', false)
                ->assertDontSee('name="expected_result"', false)
                ->getContent();

            $this->assertLessThan(
                strpos($html, 'name="evidence"', strpos($html, 'expected-result-'.$activity->id)),
                strpos($html, 'expected-result-'.$activity->id),
            );
        }

        $record->update(['current_phase' => 0]);
        $activity = $record->activities()->where('catalog_order', 1)
            ->where('rcsp_phase_id', RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                ->where('number', 0)->value('id'))->firstOrFail();
        $originalResult = $activity->expected_result;
        $this->actingAs($lgu)
            ->from(route('lgu.monitoring.show', $record))
            ->post(route('lgu.monitoring.submit', [$record, $activity]), [
                'conduct' => 'n/a',
                'expected_result' => 'Tampered result',
            ])
            ->assertRedirect(route('lgu.monitoring.show', $record))
            ->assertSessionHasErrors('request');
        $this->assertSame($originalResult, $activity->fresh()->expected_result);
        $this->actingAs($lgu)->get(route('lgu.monitoring.show', $record))
            ->assertSeeText($originalResult)
            ->assertSeeText('Expected Result/Output')
            ->assertDontSeeText('Upload a document or photo that clearly proves this result was achieved.')
            ->assertDontSee('expected-result-panel', false);
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

        $this->assertSame(33, DB::table('rcsp_activities')->where('rcsp_barangay_id', $record->id)->count());
    }

    public function test_phase_indicators_select_one_phase_without_unlocking_non_current_controls(): void
    {
        $municipality = Municipality::create(['name' => 'Phase Selector Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Phase Selector Barangay',
        ]);
        $lgu = User::factory()->lgu($municipality->id)->create();
        $this->actingAs($lgu)->post(route('lgu.rcsp.store'), [
            'barangay_id' => $barangay->id,
        ])->assertSessionHasNoErrors();

        $record = RcspBarangay::where('barangay_id', $barangay->id)->firstOrFail();
        $record->update(['current_phase' => 2, 'status' => 'Ongoing']);

        $default = $this->actingAs($lgu)->get(route('lgu.monitoring.show', $record));
        $default->assertOk()
            ->assertSeeText('Phase 2: Access')
            ->assertSeeText(RcspActivityCatalog::ACTIVITIES[2][0])
            ->assertDontSeeText(RcspActivityCatalog::ACTIVITIES[0][0])
            ->assertSee('<input type="file" name="evidence"', false)
            ->assertSee('<input type="radio" name="conduct"', false)
            ->assertDontSeeText('Complete the previous phase to unlock evidence submission.');
        foreach (range(0, 5) as $phaseNumber) {
            $default->assertSee(
                route('lgu.monitoring.show', [$record, 'phase' => $phaseNumber]),
                false,
            );
        }

        $completed = $this->actingAs($lgu)->get(route('lgu.monitoring.show', [
            $record,
            'phase' => 0,
        ]));
        $completed->assertOk()
            ->assertSeeText('Phase 0: Pre-Shaping')
            ->assertSeeText(RcspActivityCatalog::ACTIVITIES[0][0])
            ->assertSeeText(RcspActivityCatalog::EXPECTED_RESULTS['orient_lce'])
            ->assertDontSeeText(RcspActivityCatalog::ACTIVITIES[2][0])
            ->assertDontSee('<input type="file" name="evidence"', false)
            ->assertDontSee('<input type="radio" name="conduct"', false)
            ->assertDontSeeText('Complete the previous phase to unlock evidence submission.');

        $future = $this->actingAs($lgu)->get(route('lgu.monitoring.show', [
            $record,
            'phase' => 3,
        ]));
        $future->assertOk()
            ->assertSeeText('Phase 3: Transform')
            ->assertSeeText(RcspActivityCatalog::ACTIVITIES[3][0])
            ->assertSeeText(RcspActivityCatalog::EXPECTED_RESULTS['enhance_local_plans'])
            ->assertDontSeeText(RcspActivityCatalog::ACTIVITIES[2][0])
            ->assertDontSee('<input type="file" name="evidence"', false)
            ->assertDontSee('<input type="radio" name="conduct"', false)
            ->assertDontSee('type="submit" form="activity-submission-', false);
        $this->assertSame(
            1,
            substr_count($future->getContent(), 'Complete the previous phase to unlock evidence submission.'),
        );

        $this->actingAs($lgu)
            ->get(route('lgu.monitoring.show', [$record, 'phase' => 'invalid']))
            ->assertOk()
            ->assertSeeText('Phase 2: Access')
            ->assertSeeText(RcspActivityCatalog::ACTIVITIES[2][0]);

        $phaseTwo = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->where('number', 2)->firstOrFail();
        $activity = $record->activities()->where('rcsp_phase_id', $phaseTwo->id)
            ->orderBy('catalog_order')->firstOrFail();
        $selectedUrl = route('lgu.monitoring.show', [$record, 'phase' => 2]);
        $this->actingAs($lgu)
            ->from($selectedUrl)
            ->post(route('lgu.monitoring.submit', [$record, $activity, 'phase' => 2]), [])
            ->assertRedirect($selectedUrl)
            ->assertSessionHasErrors('conduct');
        $this->actingAs($lgu)->get($selectedUrl)
            ->assertSeeText($activity->expected_result)
            ->assertSee('aria-current="page"', false);
    }

    public function test_reference_migration_reconciles_legacy_rows_in_place_and_preserves_relationships(): void
    {
        $municipality = Municipality::create(['name' => 'Legacy Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Legacy Barangay',
        ]);
        $lgu = User::factory()->lgu($municipality->id)->create();
        $reviewer = User::factory()->role('admin')->create();
        $record = RcspBarangay::create([
            'barangay_id' => $barangay->id,
            'municipality_id' => $municipality->id,
            'status' => 'Ongoing',
            'current_phase' => 1,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY,
        ]);

        $legacyIds = [];
        foreach (RcspActivityCatalog::LEGACY_ACTIVITIES as $phaseNumber => $titles) {
            $phase = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
                ->where('number', $phaseNumber)->firstOrFail();
            foreach ($titles as $title) {
                if ($title === null) {
                    continue;
                }
                $activity = RcspActivity::create([
                    'rcsp_phase_id' => $phase->id,
                    'rcsp_barangay_id' => $record->id,
                    'description' => $title,
                    'normalized_title' => RcspActivity::normalizeTitle($title),
                    'catalog_order' => 0,
                    'expected_result' => '',
                ]);
                $legacyIds[$phaseNumber][] = $activity->id;
            }
        }

        $phaseOne = RcspPhase::where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->where('number', 1)->firstOrFail();
        $preservedActivityId = $legacyIds[1][0];
        $form = RcspForm::create([
            'lgu_user_id' => $lgu->id,
            'rcsp_barangay_id' => $record->id,
            'rcsp_phase_id' => $phaseOne->id,
            'rcsp_activity_id' => $preservedActivityId,
            'submission_version' => 1,
            'conduct' => 'n/a',
            'status' => 'approved',
        ]);
        RcspFormReview::create([
            'rcsp_form_id' => $form->id,
            'reviewer_user_id' => $reviewer->id,
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        RcspFileComment::create([
            'rcsp_form_id' => $form->id,
            'rcsp_phase_id' => $phaseOne->id,
            'rcsp_activity_id' => $preservedActivityId,
            'user_id' => $lgu->id,
            'text' => 'Reference migration relationship check.',
        ]);
        RcspPhaseTransition::create([
            'rcsp_barangay_id' => $record->id,
            'from_phase' => 0,
            'to_phase' => 1,
            'advanced_by_user_id' => $lgu->id,
            'advanced_at' => now(),
        ]);
        $relatedCounts = [
            'forms' => RcspForm::count(),
            'reviews' => RcspFormReview::count(),
            'comments' => RcspFileComment::count(),
            'transitions' => RcspPhaseTransition::count(),
        ];

        $migration = require database_path('migrations/2026_11_10_000001_reconcile_rcsp_activity_catalog_and_expected_results.php');
        $migration->up();
        $migration->up();

        $this->assertSame(33, $record->activities()->count());
        $this->assertSame($preservedActivityId, $form->fresh()->rcsp_activity_id);
        $this->assertSame(
            'Orient the Provincial/Municipal/City RCSP Core Team',
            RcspActivity::findOrFail($preservedActivityId)->description,
        );
        $this->assertSame($relatedCounts, [
            'forms' => RcspForm::count(),
            'reviews' => RcspFormReview::count(),
            'comments' => RcspFileComment::count(),
            'transitions' => RcspPhaseTransition::count(),
        ]);
        $this->assertSame(0, $record->activities()->whereNull('expected_result')->count());
        $this->assertSame(0, $record->activities()->where('expected_result', '')->count());
    }
}
