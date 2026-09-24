<?php

namespace Tests\Feature;

use App\Enums\JapicCertificationStatus;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Support\JapicCertificationDraftSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JapicCertificationDraftEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_changed_save_is_encrypted_snapshotted_and_starts_drafting_while_unchanged_save_is_a_noop(): void
    {
        [$processing, $japic] = $this->processing();
        $payload = $this->draftInput($processing);
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $payload)->assertRedirect();
        $processing->refresh();
        $this->assertSame(JapicCertificationStatus::Drafting, $processing->status);
        $this->assertSame(1, $processing->draft->revision);
        $this->assertSame(2, $processing->draft->schema_version);
        $this->assertSame($processing->ib39_surfaced_former_rebel_id, $processing->draft->payload['source_snapshot']['fr_id']);
        $raw = DB::table('japic_certification_drafts')->value('payload');
        $this->assertStringNotContainsString('CTRL-001', $raw);
        $this->assertStringNotContainsString('Subject Alias', $raw);
        $this->assertDatabaseCount('japic_certification_draft_histories', 1);
        $this->assertDatabaseCount('japic_certification_histories', 2); // intake + first save
        $payload['revision'] = 1;
        $payload['lock_version'] = 1;
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $payload)->assertRedirect();
        $this->assertDatabaseCount('japic_certification_draft_histories', 1);
    }

    public function test_server_owned_unknown_fields_and_stale_revisions_are_rejected(): void
    {
        [$processing, $japic] = $this->processing();
        $input = $this->draftInput($processing);
        $input['source_snapshot'] = ['subject_name' => 'Forged'];
        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $processing))->put(route('japic.certifications.draft.update', $processing), $input)
            ->assertSessionHasErrors('request');
        unset($input['source_snapshot']);
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $input)->assertRedirect();
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $input)->assertStatus(409);
        $input['revision'] = 1;
        $input['lock_version'] = 0;
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $input)->assertStatus(409);
    }

    public function test_control_number_is_case_insensitively_hmac_unique_without_disclosure(): void
    {
        [$first, $japic] = $this->processing('One');
        [$second] = $this->processing('Two');
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $first), $this->draftInput($first))->assertRedirect();
        $input = $this->draftInput($second);
        $input['control_number'] = ' ctrl-001 ';
        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $second))->put(route('japic.certifications.draft.update', $second), $input)
            ->assertSessionHasErrors('control_number')->assertSessionDoesntHaveErrors(['request']);
        $this->assertSame(app(JapicCertificationDraftSchema::class)->controlNumberHash('CTRL-001'), $first->fresh()->control_number_hash);
        $this->assertStringNotContainsString('CTRL-001', DB::table('japic_certification_processings')->where('id', $first->id)->value('control_number'));

        $changed = $this->draftInput($first);
        $changed['revision'] = 1;
        $changed['lock_version'] = 1;
        $changed['control_number'] = 'CTRL-CHANGED';
        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $first))
            ->put(route('japic.certifications.draft.update', $first), $changed)
            ->assertSessionHasErrors('control_number');
    }

    public function test_overdue_changed_save_requires_an_encrypted_delay_reason(): void
    {
        [$processing, $japic] = $this->processing();
        $processing->forceFill(['received_at' => now()->subDays(15), 'due_at' => now()->subDay()])->save();
        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $processing))->put(route('japic.certifications.draft.update', $processing), $this->draftInput($processing))
            ->assertSessionHasErrors('delay_reason');
        $input = $this->draftInput($processing) + ['delay_reason' => 'Operational coordination was required.'];
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $input)->assertRedirect();
        $raw = DB::table('japic_certification_histories')->whereNotNull('delay_reason')->value('delay_reason');
        $this->assertStringNotContainsString('Operational coordination', $raw);
    }

    public function test_v1_is_adapted_without_rewrite_and_converts_only_after_a_meaningful_save(): void
    {
        [$processing, $japic] = $this->processing();
        $schema = app(JapicCertificationDraftSchema::class);
        $source = $schema->sourceSnapshot($processing);
        $legacy = ['schema_version' => 1, 'source_snapshot' => $source,
            'certificate' => ['date_issued' => '2026-09-09', 'surrendering_unit' => '39IB', 'surrender_date' => '2026-08-01',
                'surrender_location' => 'Test location', 'operating_area_supplement' => 'Additional area'],
            'signatories' => [
                'provincial_afp' => ['rank' => 'CPT', 'name' => 'FIRST OFFICER', 'suffix' => 'JR'],
                'provincial_pnp' => ['rank' => 'PMAJ', 'name' => 'SECOND OFFICER', 'suffix' => null],
                'area_afp' => ['rank' => 'LTC', 'name' => 'THIRD OFFICER', 'suffix' => null],
                'area_pnp' => ['rank' => 'PLTCOL', 'name' => 'FOURTH OFFICER', 'suffix' => null],
            ]];
        $processing->draft()->forceCreate(['payload' => $legacy, 'schema_version' => 1, 'revision' => 1, 'last_saved_by' => $japic->id, 'last_saved_at' => now()]);
        $processing->draftHistories()->create(['revision' => 1, 'payload' => $legacy, 'saved_by' => $japic->id, 'saved_at' => now()]);
        $processing->forceFill(['status' => JapicCertificationStatus::Drafting, 'control_number' => 'CTRL-001',
            'control_number_hash' => $schema->controlNumberHash('CTRL-001'), 'lock_version' => 1])->save();
        $rawDraft = DB::table('japic_certification_drafts')->value('payload');
        $rawHistory = DB::table('japic_certification_draft_histories')->value('payload');

        $this->actingAs($japic)->get(route('japic.certifications.draft.edit', $processing))->assertOk()->assertSee('FIRST OFFICER JR');
        $adapted = $schema->forReading($legacy, 'CTRL-001');
        $same = ['revision' => 1, 'lock_version' => 1, 'control_number' => 'CTRL-001',
            'certificate' => $adapted['certificate']];
        unset($same['certificate']['control_number'], $same['certificate']['photo_version_id']);
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $same)->assertRedirect();
        $this->assertSame(1, $processing->draft()->value('schema_version'));
        $this->assertSame($rawDraft, DB::table('japic_certification_drafts')->value('payload'));
        $this->assertSame($rawHistory, DB::table('japic_certification_draft_histories')->value('payload'));

        $same['certificate']['narrative_values']['residence'] = 'Meaningfully changed residence';
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $same)->assertRedirect();
        $this->assertSame(2, $processing->draft()->value('schema_version'));
        $this->assertSame(2, $processing->draft()->value('revision'));
        $this->assertSame($rawHistory, DB::table('japic_certification_draft_histories')->where('revision', 1)->value('payload'));
    }

    public function test_fixed_wording_personnel_limits_lengths_and_order_are_server_enforced(): void
    {
        [$processing, $japic] = $this->processing();
        $input = $this->draftInput($processing);
        $input['fixed_narrative'] = 'FORGED WORDING';
        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $processing))
            ->put(route('japic.certifications.draft.update', $processing), $input)->assertSessionHasErrors('request');

        unset($input['fixed_narrative']);
        $input['certificate']['prepared_by'] = [];
        $input['certificate']['attested_by'] = array_fill(0, 9, ['full_name' => 'OFFICER', 'rank' => 'CPT']);
        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $processing))
            ->put(route('japic.certifications.draft.update', $processing), $input)
            ->assertSessionHasErrors(['certificate.prepared_by', 'certificate.attested_by']);

        $input = $this->draftInput($processing);
        $input['certificate']['prepared_by'] = [
            ['full_name' => 'FIRST PREPARER', 'rank' => 'CPT'],
            ['full_name' => 'SECOND PREPARER', 'rank' => 'LTC'],
        ];
        $input['certificate']['attested_by'][0]['rank'] = str_repeat('R', 101);
        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $processing))
            ->put(route('japic.certifications.draft.update', $processing), $input)
            ->assertSessionHasErrors('certificate.attested_by.0.rank');

        $input['certificate']['attested_by'][0]['rank'] = 'PMAJ';
        $this->actingAs($japic)->put(route('japic.certifications.draft.update', $processing), $input)->assertRedirect();
        $stored = $processing->draft()->firstOrFail()->payload['certificate']['prepared_by'];
        $this->assertSame(['FIRST PREPARER', 'SECOND PREPARER'], array_column($stored, 'full_name'));
        $this->assertStringNotContainsString('FIRST PREPARER', DB::table('japic_certification_drafts')->value('payload'));
    }

    public function test_editor_hides_source_snapshot_and_uses_frozen_gender_and_affiliation_period(): void
    {
        [$processing, $japic] = $this->processing();

        $this->actingAs($japic)->get(route('japic.certifications.draft.edit', $processing))->assertOk()
            ->assertDontSee('Authoritative source snapshot')
            ->assertSee('She started her affiliation')->assertSee('attest her legitimacy')
            ->assertSee('during 1998')->assertDontSee('name="source_snapshot', false);
    }

    private function draftInput(JapicCertificationProcessing $processing): array
    {
        return ['revision' => $processing->draft?->revision ?? 0, 'lock_version' => $processing->lock_version, 'control_number' => 'CTRL-001',
            'certificate' => ['date_issued' => '2026-09-09', 'narrative_values' => [
                'fr_name' => 'Test Subject @Subject Alias (NPSRL)', 'residence' => 'Test Address',
                'former_organization_or_category' => 'Team Leader of Test Organization', 'areas_of_operation' => 'Area One, Additional area',
                'affiliated_organization' => 'Communist Terrorist Group (CTG)', 'surrendered_to' => '39IB',
                'surrendered_on' => '2026-08-01', 'surrendered_at' => 'Test location'],
                'prepared_by' => [['full_name' => 'TEST OFFICER', 'rank' => 'CPT']],
                'attested_by' => [['full_name' => 'TEST ATTESTER', 'rank' => 'PMAJ']]]];
    }

    private function processing(string $suffix = 'Subject'): array
    {
        $japic = User::factory()->role('japic')->create();
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Test Municipality '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'FR-'.$suffix.'-'.uniqid(), 'first_name' => 'Test', 'last_name' => $suffix,
            'category' => 'Regular Member', 'province' => 'Davao del Sur', 'municipality_id' => $municipality, 'specific_location' => 'Village',
            'surfaced_at' => '2026-08-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = Ib39CdrDocumentVersion::query()->forceCreate(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'generated/test',
            'original_filename' => 'test.html', 'mime_type' => 'text/html', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'content_schema_version' => 2,
            'content_snapshot' => ['content' => ['alias' => 'Subject Alias', 'gender' => 'Female', 'classification' => 'NPSRL', 'present_address' => 'Test Address',
                'latest_position' => 'Team Leader', 'organization_affiliation' => 'Test Organization', 'recruitment_date' => '1998', 'posting_areas' => [['place' => 'Area One']]], 'fr_photo_version_id' => null],
            'created_by' => $actor->id, 'finalized_at' => now()]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $version->id]);
        $processing = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version->id,
            'status' => JapicCertificationStatus::Pending, 'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]);
        $processing->histories()->create(['actor_id' => $actor->id, 'to_status' => JapicCertificationStatus::Pending, 'event' => 'intake_created', 'occurred_at' => now()]);

        return [$processing, $japic];
    }
}
