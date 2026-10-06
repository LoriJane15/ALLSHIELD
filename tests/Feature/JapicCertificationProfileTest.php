<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\SurfacedFrDocumentsRecordsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class JapicCertificationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_read_only_shows_cancellation_and_never_guesses_assistance(): void
    {
        $japic = User::factory()->role('japic')->create();
        $processing = $this->processing();
        DB::table('ib39_fr_cancellations')->insert(['ib39_surfaced_former_rebel_id' => $processing->ib39_surfaced_former_rebel_id,
            'previous_overall_status' => 'CDR Completed', 'reason' => encrypt('Authoritative cancellation reason'), 'cancelled_by' => $japic->id,
            'cancelled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk()
            ->assertSee('Authoritative cancellation reason')->assertSee('Documents/Records')->assertSee('Assistance Records')
            ->assertDontSee('Start Certification')->assertDontSee('Upload Final')->assertDontSee('Complete Certification');
        $this->assertStringNotContainsString('private/cdr/secret.pdf', $response->getContent());
        $this->assertSame(0, DB::table('fr_government_assistances')->count());
    }

    public function test_assigned_processing_is_visible_only_to_its_assignee(): void
    {
        $assignee = User::factory()->role('japic')->create();
        $other = User::factory()->role('japic')->create();
        $processing = $this->processing();
        $processing->update(['assigned_to' => $assignee->id]);
        $this->actingAs($assignee)->get(route('japic.certifications.show', $processing))->assertOk();
        $this->actingAs($other)->get(route('japic.certifications.show', $processing))->assertForbidden();
        $this->actingAs($assignee)->get(route('japic.certifications.workspace', $processing))->assertOk();
        $this->actingAs($other)->get(route('japic.certifications.workspace', $processing))->assertForbidden();
    }

    public function test_profile_is_a_light_overview_with_a_link_to_the_separate_workspace(): void
    {
        $japic = User::factory()->role('japic')->create();
        $processing = $this->processing();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk()
            ->assertSee('JAP-PROFILE')->assertSee('Certification Information')
            ->assertSee(JapicCertificationStatus::Pending->value)
            ->assertSee('Open Certification Workspace')
            ->assertSee('href="'.route('japic.certifications.workspace', $processing).'"', false)
            ->assertDontSee('Comments &amp; Remarks', false)
            ->assertDontSee('Document History')
            ->assertDontSee('process-workspace-grid')
            ->assertDontSee('process-comment-form')
            ->assertDontSee('Save Draft')
            ->assertDontSee('Start Draft')
            ->assertDontSee('Upload Final Signed Certification')
            ->assertDontSee('<iframe', false);

        $queries = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        foreach (['japic_certification_comments', 'japic_certification_histories', 'japic_certification_drafts'] as $table) {
            $this->assertStringNotContainsString($table, $queries);
        }
    }

    public function test_workspace_contains_processing_and_shared_activity_with_profile_navigation(): void
    {
        $japic = User::factory()->role('japic')->create();
        $processing = $this->processing();
        $processing->comments()->create(['user_id' => $japic->id, 'author_role' => 'japic', 'text' => 'Workspace only note']);

        $this->actingAs($japic)->get(route('japic.certifications.workspace', $processing))->assertOk()
            ->assertSee('process-workspace-grid')
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertSee('Workspace only note')
            ->assertSee('JAPIC Certification')
            ->assertSee('name="certificate[narrative_values][fr_name]"', false)
            ->assertSee('Save Draft')
            ->assertSee('Upload Certification')
            ->assertSee('All changes saved')
            ->assertSee('Preview Draft')
            ->assertDontSee('No certification draft has been saved.')
            ->assertDontSee('Final certification version 1')
            ->assertSee('Back to Certification Profile')
            ->assertSee('href="'.route('japic.certifications.show', $processing).'"', false);

        $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk()
            ->assertDontSee('Workspace only note');
    }

    public function test_both_profiles_present_the_same_ordered_documents_and_monitoring_destinations(): void
    {
        $japic = User::factory()->role('japic')->create();
        $processing = $this->processing();
        $ib39 = User::query()->findOrFail($processing->surfacedFormerRebel()->value('created_by'));
        $fr = $processing->surfacedFormerRebel;

        $japicResponse = $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk();
        $ib39Response = $this->actingAs($ib39)->get(route('ib39.fr-profiles.show', $fr))->assertOk();
        foreach ([$japicResponse, $ib39Response] as $response) {
            $response->assertSee('CDR Completed')->assertSeeInOrder([
                'CDR', 'JAPIC Certification', 'PSWDO Enrollment Documents',
                'FEA Processing Documents', 'Assistance Records',
            ])->assertDontSee('E-CLIP Enrollment Form')
                ->assertDontSee('No documents available')
                ->assertDontSee('Secure preview')
                ->assertDontSee('Secure download');
        }
        foreach ([
            route('cdr.workspace', $fr->cdrProcessing),
            route('japic.certifications.records.pswdo', $processing),
            route('japic.certifications.records.fea', $processing),
            route('japic.certifications.records.assistance', $processing),
            route('japic.certifications.workspace', $processing),
        ] as $url) {
            $japicResponse->assertSee('href="'.$url.'"', false);
        }
        foreach ([
            route('cdr.workspace', $fr->cdrProcessing),
            route('ib39.fr-profiles.records.pswdo', $fr),
            route('ib39.fr-profiles.records.fea', $fr),
            route('ib39.fr-profiles.records.assistance', $fr),
            route('japic.certifications.workspace', $processing),
        ] as $url) {
            $ib39Response->assertSee('href="'.$url.'"', false);
        }
    }

    public function test_shared_profile_contains_only_common_read_only_content_and_japic_never_loads_or_renders_cdr_history(): void
    {
        $japic = User::factory()->role('japic')->create();
        $processing = $this->processing();
        $cdrId = DB::table('ib39_cdr_processings')->where('ib39_surfaced_former_rebel_id', $processing->ib39_surfaced_former_rebel_id)->value('id');
        DB::table('ib39_cdr_status_histories')->insert([
            'cdr_processing_id' => $cdrId, 'from_status' => 'Ongoing', 'to_status' => 'Completed',
            'user_id' => $japic->id, 'event' => 'completed', 'remarks' => encrypt('JAPIC-MUST-NOT-RECEIVE-CDR-HISTORY'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::enableQueryLog();

        $response = $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk()
            ->assertSee('FR Profile Information')->assertSee('Documents/Records')
            ->assertDontSee('JAPIC Certification Timeline')->assertSee('Open Certification Workspace')
            ->assertDontSee('Related Workflows')->assertDontSee('Current Final CDR')->assertDontSee('Secure preview')
            ->assertDontSee('Private certification photographs')->assertDontSee('Immutable draft revisions')
            ->assertDontSee('JAPIC-MUST-NOT-RECEIVE-CDR-HISTORY')->assertDontSee('CDR History');

        $response->assertDontSee('id="overall-status-heading"', false)
            ->assertSeeInOrder(['FR Profile Information', 'Documents/Records', 'Certification Information']);

        $this->assertFalse(collect(DB::getQueryLog())->contains(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'ib39_cdr_status_histories')
        ));
        $component = file_get_contents(resource_path('views/components/surfaced-fr-profile.blade.php'));
        foreach (['<form', 'statusHistories', 'draftHistories', 'japic.certifications', 'ib39.cdr'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $component);
        }
        $this->assertStringContainsString('statusHistories', file_get_contents(app_path('Http/Controllers/Ib39/CdrController.php')));
        $this->assertStringNotContainsString('statusHistories', $response->getContent());
        $monitoringComponent = file_get_contents(resource_path('views/components/surfaced-fr-documents-records.blade.php'));
        $this->assertStringNotContainsString('route(', $monitoringComponent);
        $this->assertStringNotContainsString('App\\Enums', $monitoringComponent);
    }

    public function test_documents_service_rejects_a_missing_eager_loaded_pswdo_relationship(): void
    {
        $processing = $this->processing();
        $processing->load([
            'surfacedFormerRebel.cdrProcessing.finalDocument',
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
            'currentFinalVersion',
        ]);
        $record = $processing->surfacedFormerRebel;
        $record->setRelation('japicCertificationProcessing', $processing);

        $this->expectException(LogicException::class);
        app(SurfacedFrDocumentsRecordsService::class)->summaries($record);
    }

    public function test_profile_shows_status_without_the_old_certification_timeline(): void
    {
        $japic = User::factory()->role('japic')->create();
        $processing = $this->processing();
        foreach ([
            JapicCertificationStatus::Pending->value,
            JapicCertificationStatus::Drafting->value,
            JapicCertificationStatus::ForSigning->value,
            JapicCertificationStatus::AwaitingFinalUpload->value,
            JapicCertificationStatus::Completed->value,
        ] as $status) {
            $processing->forceFill(['status' => $status])->save();
            $html = $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk()->getContent();
            $this->assertStringContainsString($status, $html);
            $this->assertStringNotContainsString('JAPIC Certification Timeline', $html);
            $this->assertStringNotContainsString('certification-step', $html);
        }

        $processing->histories()->create([
            'actor_id' => $japic->id,
            'from_status' => JapicCertificationStatus::Drafting,
            'to_status' => JapicCertificationStatus::Cancelled,
            'event' => JapicCertificationEvent::FrCancelled,
            'occurred_at' => now(),
        ]);
        DB::table('ib39_fr_cancellations')->insert([
            'ib39_surfaced_former_rebel_id' => $processing->ib39_surfaced_former_rebel_id,
            'previous_overall_status' => 'CDR Completed',
            'reason' => encrypt('Timeline cancelled'),
            'cancelled_by' => $japic->id,
            'cancelled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $processing->forceFill(['status' => JapicCertificationStatus::Cancelled])->save();
        $html = $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk()
            ->assertSee('Certification processing stopped because the FR was cancelled.')
            ->getContent();
        $this->assertStringNotContainsString('JAPIC Certification Timeline', $html);
    }

    private function processing(): JapicCertificationProcessing
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Profile Municipality', 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'JAP-PROFILE', 'first_name' => 'Profile', 'last_name' => 'Person', 'category' => Ib39FrCategory::RegularMember->value, 'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = DB::table('ib39_cdr_final_documents')->insertGetId(['cdr_processing_id' => $cdr, 'source_type' => 'uploaded', 'storage_path' => 'private/cdr/secret.pdf', 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('c', 64), 'created_by' => $actor->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_final_document_id' => $version, 'status' => JapicCertificationStatus::Pending, 'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]);
    }
}
