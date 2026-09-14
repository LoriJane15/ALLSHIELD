<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\FormerRebel;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class Ib39SurfacedFormerRebelListTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private Municipality $municipality;

    private Barangay $barangay;

    private int $nextReference = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->role('39th_ib')->create();
        $this->municipality = Municipality::query()->create(['name' => 'List Municipality']);
        $this->barangay = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'List Barangay',
        ]);
    }

    public function test_guest_is_redirected_and_only_active_39th_ib_users_are_authorized(): void
    {
        $this->get(route('ib39.fr-profiles.index'))->assertRedirect(route('login'));

        $this->assertTrue($this->creator->can('viewAny', Ib39SurfacedFormerRebel::class));
        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk();

        $inactiveUser = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->assertFalse($inactiveUser->can('viewAny', Ib39SurfacedFormerRebel::class));
        $this->actingAs($inactiveUser)
            ->get(route('ib39.fr-profiles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_every_other_role_receives_forbidden_and_fails_view_any_policy(): void
    {
        $roles = [
            'super_admin', 'admin', 'lgu', 'gov_agency', 'mblrc', 'afp',
        ];

        foreach ($roles as $role) {
            $user = User::factory()->role($role)->create();

            $this->assertFalse($user->can('viewAny', Ib39SurfacedFormerRebel::class));
            $this->actingAs($user)->get(route('ib39.fr-profiles.index'))->assertForbidden();
        }
    }

    public function test_list_uses_standalone_records_and_excludes_soft_deleted_records(): void
    {
        FormerRebel::query()->create([
            'classified_id' => 'FR-#OLDTABLE',
            'firstname' => 'OldTableSecretFirst',
            'lastname' => 'OldTableSecretLast',
            'municipality_id' => $this->municipality->id,
        ]);
        $visible = $this->record(['first_name' => 'Visible', 'last_name' => 'Standalone']);
        $deleted = $this->record(['first_name' => 'DeletedSecret', 'last_name' => 'Standalone']);
        $deleted->delete();

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk()
            ->assertSee($visible->display_name)
            ->assertDontSee('DeletedSecret')
            ->assertDontSee('OldTableSecretFirst')
            ->assertDontSee('OldTableSecretLast');
    }

    public function test_list_is_paginated_and_ordered_by_newest_created_then_descending_id(): void
    {
        $oldest = $this->record(['reference_number' => 'FR-OLDEST', 'created_at' => now()->subDay()]);
        $stableLowerId = $this->record(['reference_number' => 'FR-STABLE-LOW', 'created_at' => now()]);
        $stableHigherId = $this->record(['reference_number' => 'FR-STABLE-HIGH', 'created_at' => now()]);

        for ($index = 0; $index < 13; $index++) {
            $this->record(['created_at' => now()->subHours($index + 1)]);
        }

        $response = $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk()
            ->assertSee('Showing 1–15 of 16 records')
            ->assertSee('page=2', false)
            ->assertSeeInOrder([$stableHigherId->reference_number, $stableLowerId->reference_number])
            ->assertDontSee($oldest->reference_number);

        $response = $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index', ['page' => 2]))
            ->assertOk()
            ->assertSee($oldest->reference_number);
    }

    public function test_list_displays_approved_columns_and_computed_values_without_sensitive_extras(): void
    {
        $withBarangay = $this->record([
            'reference_number' => 'FR-DISPLAY',
            'first_name' => 'Approved First',
            'last_name' => 'Approved Last',
            'category' => Ib39FrCategory::RegularMember->value,
            'specific_location' => 'Sensitive Specific Site',
            'surfaced_at' => '2026-08-30',
            'possessed_firearms' => true,
        ]);
        $withoutBarangay = $this->record([
            'reference_number' => 'FR-NO-BARANGAY',
            'first_name' => 'No',
            'last_name' => 'Barangay',
            'barangay_id' => null,
            'possessed_firearms' => false,
        ]);

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk()
            ->assertSee($withBarangay->reference_number)
            ->assertSee($withBarangay->display_name)
            ->assertSee(Ib39FrCategory::RegularMember->value)
            ->assertSee('List Barangay, List Municipality, Davao del Sur')
            ->assertSee('List Municipality, Davao del Sur')
            ->assertSee('Aug 30, 2026')
            ->assertSee('Yes')
            ->assertSee('No')
            ->assertSee('Newly Recorded')
            ->assertDontSee('Sensitive Specific Site')
            ->assertDontSee('MiddleNameSecret')
            ->assertDontSee('AliasSecret')
            ->assertDontSee('NicknameSecret');
    }

    public function test_profile_and_create_actions_use_the_approved_routes(): void
    {
        $record = $this->record();

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk()
            ->assertSee('href="'.route('ib39.fr-profiles.create').'"', false)
            ->assertSee('href="'.route('ib39.fr-profiles.show', $record).'"', false)
            ->assertSee('View Profile');
    }

    public function test_reference_first_name_and_last_name_searches_work(): void
    {
        $target = $this->record([
            'reference_number' => 'FR-SEARCH-742',
            'first_name' => 'UniqueGiven',
            'last_name' => 'UniqueFamily',
        ]);
        $other = $this->record([
            'reference_number' => 'FR-OTHER',
            'first_name' => 'Different',
            'last_name' => 'Record',
        ]);

        foreach (['SEARCH-742', 'UniqueGiven', 'UniqueFamily'] as $search) {
            $this->actingAs($this->creator)
                ->get(route('ib39.fr-profiles.index', ['search' => "  {$search}  "]))
                ->assertOk()
                ->assertSee($target->reference_number)
                ->assertDontSee($other->reference_number);
        }
    }

    public function test_category_municipality_and_firearms_filters_work(): void
    {
        $otherMunicipality = Municipality::query()->create(['name' => 'Other List Municipality']);
        $target = $this->record([
            'reference_number' => 'FR-FILTER-TARGET',
            'category' => Ib39FrCategory::Other->value,
            'municipality_id' => $otherMunicipality->id,
            'barangay_id' => null,
            'possessed_firearms' => true,
        ]);
        $other = $this->record([
            'reference_number' => 'FR-FILTER-OTHER',
            'category' => Ib39FrCategory::RegularMember->value,
            'possessed_firearms' => false,
        ]);

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index', [
                'category' => Ib39FrCategory::Other->value,
                'municipality_id' => $otherMunicipality->id,
                'possessed_firearms' => '1',
            ]))
            ->assertOk()
            ->assertSee($target->reference_number)
            ->assertDontSee($other->reference_number);

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index', ['possessed_firearms' => '0']))
            ->assertOk()
            ->assertSee($other->reference_number)
            ->assertDontSee($target->reference_number);
    }

    public function test_invalid_filters_and_excessive_search_length_are_rejected(): void
    {
        foreach ([
            ['category' => 'Invented Category'],
            ['municipality_id' => 999999],
            ['possessed_firearms' => 'yes'],
            ['search' => str_repeat('x', 101)],
        ] as $query) {
            $this->actingAs($this->creator)
                ->get(route('ib39.fr-profiles.index', $query))
                ->assertRedirect()
                ->assertSessionHasErrors(array_key_first($query));
        }
    }

    public function test_array_filter_inputs_are_rejected_without_string_coercion(): void
    {
        foreach (['search', 'category', 'municipality_id', 'possessed_firearms'] as $field) {
            $this->actingAs($this->creator)
                ->get(route('ib39.fr-profiles.index', [$field => ['tampered']]))
                ->assertRedirect()
                ->assertSessionHasErrors($field);
        }
    }

    public function test_filters_persist_across_pagination_and_reset_is_available(): void
    {
        for ($index = 0; $index < 16; $index++) {
            $this->record([
                'reference_number' => 'FR-PERSIST-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'first_name' => 'Persisted',
            ]);
        }

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index', [
                'search' => 'Persisted',
                'category' => Ib39FrCategory::MilisyaNgBayan->value,
                'municipality_id' => $this->municipality->id,
                'possessed_firearms' => '0',
                'sort' => 'unapproved_sort_token',
                'direction' => 'unapproved_direction_token',
                'forwarded' => 'unapproved_forwarding_token',
            ]))
            ->assertOk()
            ->assertSee('search=Persisted', false)
            ->assertSee('category=Milisya%20ng%20Bayan', false)
            ->assertSee('municipality_id='.$this->municipality->id, false)
            ->assertSee('possessed_firearms=0', false)
            ->assertSee('page=2', false)
            ->assertDontSee('unapproved_sort_token', false)
            ->assertDontSee('unapproved_direction_token', false)
            ->assertDontSee('unapproved_forwarding_token', false)
            ->assertSee('Reset Filters')
            ->assertSee('href="'.route('ib39.fr-profiles.index').'"', false);
    }

    public function test_empty_database_and_empty_filtered_results_have_distinct_states(): void
    {
        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk()
            ->assertSee('No surfaced FR profiles recorded');

        $this->record();

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index', ['search' => 'NoMatchExpected']))
            ->assertOk()
            ->assertSee('No matching FR profiles found');
    }

    public function test_viewing_list_does_not_create_records_workflows_statuses_or_audits(): void
    {
        $this->record();
        $before = [
            'records' => Ib39SurfacedFormerRebel::withTrashed()->count(),
            'audits' => AuditLog::query()->count(),
        ];

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk();

        $this->assertSame($before['records'], Ib39SurfacedFormerRebel::withTrashed()->count());
        $this->assertSame($before['audits'], AuditLog::query()->count());
        $this->assertFalse(Ib39SurfacedFormerRebel::query()->getModel()->isFillable('overall_case_status'));
    }

    public function test_list_and_other_role_navigation_contain_no_forwarding_information(): void
    {
        $this->record();

        $this->actingAs($this->creator)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk()
            ->assertSee('FR Profiles')
            ->assertSee('Record Surfaced FR')
            ->assertDontSee('Forwarded')
            ->assertDontSee('Forwarding organization');

        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('FR Profiles')
            ->assertDontSee('Record Surfaced FR');
    }

    public function test_39th_ib_navigation_links_are_not_configured_for_other_roles(): void
    {
        $roles = collect(config('shield.roles'));
        $ib39Routes = $roles->get('39th_ib')['nav'];

        $this->assertContains('ib39.fr-profiles.index', array_column($ib39Routes, 'route'));
        $this->assertNotContains('ib39.fr-profiles.create', array_column($ib39Routes, 'route'));

        $roles->except('39th_ib')->each(function (array $role): void {
            $routes = array_column($role['nav'] ?? [], 'route');

            $this->assertNotContains('ib39.fr-profiles.index', $routes);
            $this->assertNotContains('ib39.fr-profiles.create', $routes);
        });
    }

    public function test_overall_status_precedence_and_valid_final_relationships_cover_every_state(): void
    {
        $new = $this->record(['reference_number' => 'STATUS-NEW']);
        $ongoing = $this->record(['reference_number' => 'STATUS-ONGOING']);
        DB::table('ib39_cdr_processings')->insert([
            'ib39_surfaced_former_rebel_id' => $ongoing->id, 'status' => 'Ongoing',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $completed = $this->record(['reference_number' => 'STATUS-CDR-COMPLETE']);
        DB::table('ib39_cdr_processings')->insert([
            'ib39_surfaced_former_rebel_id' => $completed->id, 'status' => 'Completed',
            'completed_at' => now(), 'completed_by' => $this->creator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $certified = $this->certifiedRecord('STATUS-JAPIC-CERTIFIED');
        $cancelled = $this->certifiedRecord('STATUS-CANCELLED');
        DB::table('ib39_fr_cancellations')->insert([
            'ib39_surfaced_former_rebel_id' => $cancelled->id,
            'previous_overall_status' => Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_JAPIC_CERTIFIED,
            'reason' => encrypt('Cancelled after completion'), 'cancelled_by' => $this->creator->id,
            'cancelled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            $new->id => Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS,
            $ongoing->id => Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_ONGOING,
            $completed->id => Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_COMPLETED,
            $certified->id => Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_JAPIC_CERTIFIED,
            $cancelled->id => Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CANCELLED,
        ] as $id => $expected) {
            $record = Ib39SurfacedFormerRebel::query()->findOrFail($id)->load($this->overallStatusRelations());
            $this->assertSame($expected, $record->overall_case_status);
        }

        $missingCdrFinal = $this->certifiedRecord('STATUS-MISSING-CDR-FINAL');
        $missingCdrFinal->cdrProcessing()->update(['current_final_version_id' => null]);
        $this->assertSame(
            Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_COMPLETED,
            $missingCdrFinal->fresh()->load($this->overallStatusRelations())->overall_case_status,
        );

        $missingCertificationFinal = $this->certifiedRecord('STATUS-MISSING-JAPIC-FINAL');
        $missingCertificationFinal->japicCertificationProcessing()->update(['current_final_version_id' => null]);
        $this->assertSame(
            Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS_CDR_COMPLETED,
            $missingCertificationFinal->fresh()->load($this->overallStatusRelations())->overall_case_status,
        );
    }

    public function test_overall_status_requires_intentional_eager_loading_instead_of_silently_downgrading(): void
    {
        $record = $this->certifiedRecord('STATUS-EAGER-LOAD');

        $this->expectException(LogicException::class);
        $record->fresh()->overall_case_status;
    }

    private function certifiedRecord(string $reference): Ib39SurfacedFormerRebel
    {
        $record = $this->record(['reference_number' => $reference]);
        $cdrId = DB::table('ib39_cdr_processings')->insertGetId([
            'ib39_surfaced_former_rebel_id' => $record->id, 'status' => 'Completed',
            'completed_at' => now(), 'completed_by' => $this->creator->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cdrVersionId = DB::table('ib39_cdr_document_versions')->insertGetId([
            'cdr_processing_id' => $cdrId, 'version_number' => 1, 'source_type' => 'generated',
            'storage_path' => "generated/status/{$record->id}", 'original_filename' => 'final.html',
            'mime_type' => 'text/html', 'size_bytes' => 1, 'sha256' => hash('sha256', 'cdr-'.$record->id),
            'content_schema_version' => 2, 'content_snapshot' => encrypt(['content' => []]),
            'created_by' => $this->creator->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ib39_cdr_processings')->where('id', $cdrId)->update(['current_final_version_id' => $cdrVersionId]);
        $processing = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_document_version_id' => $cdrVersionId,
            'status' => JapicCertificationStatus::Completed,
            'received_at' => now(), 'due_at' => now()->addDays(14), 'completed_at' => now(),
            'completed_by' => $this->creator->id, 'lock_version' => 1,
        ]);
        $certificationVersion = JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $processing->id, 'version_number' => 1,
            'storage_path' => "japic/certifications/{$processing->id}/final-documents/final.pdf",
            'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1,
            'sha256' => hash('sha256', 'japic-'.$record->id), 'uploaded_by' => $this->creator->id,
            'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $processing->forceFill(['current_final_version_id' => $certificationVersion->id])->save();

        return $record;
    }

    private function overallStatusRelations(): array
    {
        return ['cancellation', 'cdrProcessing.currentFinalVersion', 'japicCertificationProcessing.currentFinalVersion'];
    }

    private function record(array $overrides = []): Ib39SurfacedFormerRebel
    {
        $reference = 'FR-LIST-'.str_pad((string) $this->nextReference++, 3, '0', STR_PAD_LEFT);

        return Ib39SurfacedFormerRebel::query()->forceCreate([
            'reference_number' => $reference,
            'first_name' => 'List First',
            'last_name' => 'List Last',
            'category' => Ib39FrCategory::MilisyaNgBayan->value,
            'other_category_specification' => null,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->barangay->id,
            'specific_location' => null,
            'surfaced_at' => '2026-08-31',
            'possessed_firearms' => false,
            'initial_remarks' => null,
            'created_by' => $this->creator->id,
            ...$overrides,
        ]);
    }
}
