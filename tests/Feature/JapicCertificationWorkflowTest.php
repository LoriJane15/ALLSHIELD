<?php

namespace Tests\Feature;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\JapicCertificationDraftService;
use App\Services\JapicCertificationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JapicCertificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_signing_transitions_freeze_revision_and_never_complete(): void
    {
        [$processing, $japic] = $this->processing();
        $this->saveComplete($processing, $japic);
        app(JapicCertificationWorkflowService::class)->submitForSigning($processing->fresh(), 1, 1, null, $japic);
        $processing->refresh();
        $this->assertSame(JapicCertificationStatus::ForSigning, $processing->status);
        $frozen = $processing->histories()->where('event', JapicCertificationEvent::MarkedForSigning->value)->firstOrFail();
        $this->assertSame(1, data_get($frozen->metadata, 'revision'));
        app(JapicCertificationWorkflowService::class)->confirmSigningComplete($processing, 1, 2, null, $japic);
        $this->assertSame(JapicCertificationStatus::AwaitingFinalUpload, $processing->fresh()->status);
        $this->assertDatabaseCount('japic_certification_document_versions', 0);
        $this->assertNull($processing->fresh()->completed_at);
    }

    public function test_incomplete_draft_cannot_be_submitted_and_transitions_cannot_be_retried_or_skipped(): void
    {
        [$processing, $japic] = $this->processing();
        app(JapicCertificationDraftService::class)->save($processing, ['certificate' => [], 'signatories' => []], 'CTRL-WF', 0, 0, null, $japic);
        try {
            app(JapicCertificationWorkflowService::class)->submitForSigning($processing->fresh(), 1, 1, null, $japic);
            $this->fail('Incomplete draft was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('draft', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        app(JapicCertificationWorkflowService::class)->confirmSigningComplete($processing->fresh(), 1, 1, null, $japic);
    }

    public function test_overdue_transitions_require_delay_reason_and_cancelled_cases_are_blocked(): void
    {
        [$processing, $japic] = $this->processing();
        $this->saveComplete($processing, $japic);
        $processing->forceFill(['received_at' => now()->subDays(15), 'due_at' => now()->subDay()])->save();
        try {
            app(JapicCertificationWorkflowService::class)->submitForSigning($processing->fresh(), 1, 1, null, $japic);
            $this->fail('Missing delay reason was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delay_reason', $exception->errors());
        }
        app(JapicCertificationWorkflowService::class)->submitForSigning($processing->fresh(), 1, 1, 'Delayed for coordination.', $japic);
        $this->assertDatabaseCount('japic_certification_histories', 2);
        DB::table('ib39_fr_cancellations')->insert(['ib39_surfaced_former_rebel_id' => $processing->ib39_surfaced_former_rebel_id,
            'previous_overall_status' => 'CDR Completed', 'reason' => encrypt('Cancelled'), 'cancelled_by' => $japic->id, 'cancelled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($japic)->post(route('japic.certifications.signing-complete', $processing), ['revision' => 1, 'lock_version' => 2, 'signing_complete' => 1, 'delay_reason' => 'Still delayed'])->assertForbidden();
    }

    private function saveComplete(JapicCertificationProcessing $processing, User $japic): void
    {
        app(JapicCertificationDraftService::class)->save($processing, ['certificate' => ['date_issued' => '2026-09-09',
            'narrative_values' => ['fr_name' => 'Workflow Subject', 'residence' => 'Address',
                'former_organization_or_category' => 'Leader of Organization', 'areas_of_operation' => 'Area One',
                'affiliated_organization' => 'Organization', 'surrendered_to' => '39IB',
                'surrendered_on' => '2026-08-01', 'surrendered_at' => 'Test Place'],
            'prepared_by' => [['full_name' => 'TEST OFFICER', 'rank' => 'CPT']],
            'attested_by' => [['full_name' => 'TEST ATTESTER', 'rank' => 'PMAJ']]],
        ], 'CTRL-WF', 0, 0, null, $japic);
    }

    private function processing(): array
    {
        $japic = User::factory()->role('japic')->create();
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Workflow City', 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'FR-WF-'.uniqid(), 'first_name' => 'Workflow', 'last_name' => 'Subject', 'category' => 'Regular Member',
            'province' => 'Davao del Sur', 'municipality_id' => $municipality, 'surfaced_at' => '2026-08-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = Ib39CdrDocumentVersion::query()->forceCreate(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'generated/wf', 'original_filename' => 'wf.html',
            'mime_type' => 'text/html', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64), 'content_schema_version' => 2, 'content_snapshot' => ['content' => ['alias' => 'Alias', 'gender' => 'Male',
                'classification' => 'NPSRL', 'present_address' => 'Address', 'latest_position' => 'Leader', 'organization_affiliation' => 'Organization', 'recruitment_date' => '1998']], 'created_by' => $actor->id, 'finalized_at' => now()]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $version->id]);

        return [JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version->id,
            'status' => JapicCertificationStatus::Pending, 'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]), $japic];
    }
}
