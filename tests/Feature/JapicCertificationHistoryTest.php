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

class JapicCertificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_lists_revisions_and_displays_only_safe_normalized_differences(): void
    {
        $japic = User::factory()->role('japic')->create(['name' => '<script>Reviewer</script>']);
        $processing = $this->processing();
        $schema = app(JapicCertificationDraftSchema::class);
        $source = ['gender' => 'Female', 'affiliation_period' => '1998', 'private_marker' => 'SOURCE-SNAPSHOT-SECRET'];
        $first = $schema->normalize(['certificate' => $this->certificate(null, 'Old residence', 'OLD PREPARER')], $source, null, null);
        $second = $schema->normalize(['certificate' => $this->certificate('CTRL-HISTORY', 'New residence', 'NEW PREPARER')], $source, 'CTRL-HISTORY', 42);
        $processing->draftHistories()->create(['revision' => 1, 'payload' => $first, 'saved_by' => $japic->id, 'saved_at' => now()->subMinute()]);
        $processing->draftHistories()->create(['revision' => 2, 'payload' => $second, 'saved_by' => $japic->id, 'saved_at' => now()]);
        $processing->forceFill(['control_number' => 'CTRL-HISTORY'])->save();

        $this->actingAs($japic)->get(route('japic.certifications.history', [$processing, 'revision' => 2]))
            ->assertOk()->assertSee('Workflow History')->assertSee('Final Certification Versions')
            ->assertSee('Revision 1')->assertSee('Revision 2')
            ->assertSee('Old residence')->assertSee('New residence')
            ->assertSee('Previous value')->assertSee('New value')
            ->assertSee('Replacement JAPIC certification photograph')
            ->assertSee('&lt;script&gt;Reviewer&lt;/script&gt;', false)
            ->assertDontSee('SOURCE-SNAPSHOT-SECRET')->assertDontSee('source_snapshot')
            ->assertDontSee('storage_path')->assertDontSee('payload_fingerprint');

        $this->actingAs($japic)->get(route('japic.certifications.history', [$processing, 'revision' => 1]))
            ->assertOk()->assertSee('Initial draft')->assertSee('Initial value')->assertDontSee('Previous value');
    }

    public function test_revision_is_resolved_only_through_its_own_processing(): void
    {
        $japic = User::factory()->role('japic')->create();
        $first = $this->processing();
        $other = $this->processing();
        $payload = app(JapicCertificationDraftSchema::class)->normalize(['certificate' => $this->certificate(null, 'Other', 'Other')], [], null, null);
        $other->draftHistories()->create(['revision' => 99, 'payload' => $payload, 'saved_by' => $japic->id, 'saved_at' => now()]);

        $this->actingAs($japic)->get(route('japic.certifications.history', [$first, 'revision' => 99]))->assertNotFound();
        $this->actingAs($japic)->get('/japic/certifications/'.$first->id.'/history/not-a-number')->assertNotFound();
    }

    private function certificate(?string $control, string $residence, string $preparer): array
    {
        return [
            'control_number' => $control,
            'date_issued' => '2026-09-11',
            'narrative_values' => [
                'fr_name' => 'History Subject', 'residence' => $residence,
                'former_organization_or_category' => 'Former member', 'areas_of_operation' => 'Area One',
                'affiliated_organization' => 'Test Organization', 'surrendered_to' => '39IB',
                'surrendered_on' => '2026-08-01', 'surrendered_at' => 'Test location',
            ],
            'prepared_by' => [['full_name' => $preparer, 'rank' => 'CPT']],
            'attested_by' => [['full_name' => 'TEST ATTESTER', 'rank' => 'LTC']],
        ];
    }

    private function processing(): JapicCertificationProcessing
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'History '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'FR-HISTORY-'.uniqid(), 'first_name' => 'History', 'last_name' => 'Subject', 'category' => 'Regular Member',
            'province' => 'Davao del Sur', 'municipality_id' => $municipality, 'surfaced_at' => '2026-08-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = Ib39CdrDocumentVersion::query()->forceCreate(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'generated/history', 'original_filename' => 'history.html',
            'mime_type' => 'text/html', 'size_bytes' => 1, 'sha256' => str_repeat('f', 64), 'content_schema_version' => 2, 'content_snapshot' => ['content' => []], 'created_by' => $actor->id, 'finalized_at' => now()]);

        return JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version->id,
            'status' => JapicCertificationStatus::Drafting, 'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]);
    }
}
