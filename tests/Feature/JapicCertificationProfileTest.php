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
                'E-CLIP Enrollment Form', 'Initial Interview Form', 'Profiling Interview Form', 'Endorsement Letter',
                'FEA Processing Documents', 'Assistance Records',
            ]);
            $response->assertSee('No PSWDO enrollment document is available yet.', false);
        }
        foreach ([
            'japic.certifications.records.cdr',
            'japic.certifications.records.fea',
            'japic.certifications.records.assistance',
            'japic.certifications.records.certification',
        ] as $routeName) {
            $japicResponse->assertSee('href="'.route($routeName, $processing).'"', false);
        }
        foreach ([
            'ib39.fr-profiles.records.cdr',
            'ib39.fr-profiles.records.fea',
            'ib39.fr-profiles.records.assistance',
            'ib39.fr-profiles.records.certification',
        ] as $routeName) {
            $ib39Response->assertSee('href="'.route($routeName, $fr).'"', false);
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
            ->assertSee('JAPIC Certification Timeline')->assertSee('Certification Workspace')
            ->assertDontSee('Related Workflows')->assertDontSee('Current Final CDR')->assertDontSee('Secure preview')
            ->assertDontSee('Private certification photographs')->assertDontSee('Immutable draft revisions')
            ->assertDontSee('JAPIC-MUST-NOT-RECEIVE-CDR-HISTORY')->assertDontSee('CDR History');

        $response->assertDontSee('id="overall-status-heading"', false)
            ->assertSeeInOrder(['FR Profile Information', 'JAPIC Certification Timeline', 'Documents/Records', 'Certification Workspace']);

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
            'surfacedFormerRebel.cdrProcessing.currentFinalVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentDraftVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSupportingPhotoVersion',
            'surfacedFormerRebel.feaProcessing.documents.currentSurrenderedPhotoVersion',
            'currentFinalVersion',
        ]);
        $record = $processing->surfacedFormerRebel;
        $record->setRelation('japicCertificationProcessing', $processing);

        $this->expectException(LogicException::class);
        app(SurfacedFrDocumentsRecordsService::class)->summaries(
            $record,
            fn (): string => '',
            fn (): string => '',
        );
    }

    public function test_static_timeline_maps_every_internal_status_without_clickable_steps(): void
    {
        $japic = User::factory()->role('japic')->create();
        $processing = $this->processing();
        foreach ([
            JapicCertificationStatus::Pending->value => 0,
            JapicCertificationStatus::Drafting->value => 1,
            JapicCertificationStatus::ForSigning->value => 2,
            JapicCertificationStatus::AwaitingFinalUpload->value => 2,
            JapicCertificationStatus::Completed->value => 3,
        ] as $status => $expectedCompleted) {
            $processing->forceFill(['status' => $status])->save();
            $html = $this->actingAs($japic)->get(route('japic.certifications.show', $processing))->assertOk()->getContent();
            preg_match_all('/<li class="certification-step ([^"]*)">/', $html, $steps);
            $this->assertCount(3, $steps[1]);
            $this->assertSame($expectedCompleted, collect($steps[1])->filter(fn (string $class): bool => str_contains($class, 'is-complete'))->count());
            $this->assertStringNotContainsString('<button class="certification-step', $html);
            $this->assertStringNotContainsString('<a class="certification-step', $html);
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
        preg_match_all('/<li class="certification-step ([^"]*)">/', $html, $steps);
        $this->assertSame(1, collect($steps[1])->filter(fn (string $class): bool => str_contains($class, 'is-complete'))->count());
    }

    private function processing(): JapicCertificationProcessing
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Profile Municipality', 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'JAP-PROFILE', 'first_name' => 'Profile', 'last_name' => 'Person', 'category' => Ib39FrCategory::RegularMember->value, 'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'uploaded', 'storage_path' => 'private/cdr/secret.pdf', 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('c', 64), 'created_by' => $actor->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $version]);

        return JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version, 'status' => JapicCertificationStatus::Pending, 'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]);
    }
}
