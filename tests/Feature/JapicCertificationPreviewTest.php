<?php

namespace Tests\Feature;

use App\Enums\JapicCertificationStatus;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\JapicCertificationDraftService;
use App\Services\JapicCertificationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JapicCertificationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_print_share_official_draft_renderer_without_private_paths_or_scanned_artifacts(): void
    {
        Storage::fake('local');
        [$processing, $japic] = $this->processingWithPhoto();
        $this->save($processing, $japic, 'First Place');

        foreach (['japic.certifications.preview', 'japic.certifications.print'] as $route) {
            $response = $this->actingAs($japic)->get(route($route, $processing))->assertOk()
                ->assertHeader('Cache-Control')->assertSee('DRAFT — NOT FINAL')->assertSee('JOINT AFP-PNP')
                ->assertSee('Emblem')->assertSee('pending')->assertSee('PREPARED BY:')->assertSee('ATTESTED BY:')
                ->assertSee('Enhanced Comprehensive Local Integration Program(E-CLIP)')->assertSee('First Place')
                ->assertSee('She started her affiliation')->assertSee('during 1998')->assertSee('attest her legitimacy')
                ->assertSee('TEST PREPARER')->assertSee('TEST ATTESTER')->assertSee('CPT')
                ->assertSee('Task Force Balik Loob (TFBL);')->assertSee('DILG Provincial/HUC/ICC Office;')
                ->assertSee('E-CLIP and Amnesty Program Cluster of NTF-ELCAC.')
                ->assertDontSee('CamScanner')->assertDontSee('private/japic')->assertDontSee('storage_path');
            $this->assertStringContainsString('@page{size:A4 portrait', $response->getContent());
            $this->assertStringContainsString('border-bottom:1px solid #111', $response->getContent());
        }
    }

    public function test_fixed_wording_uses_male_and_gender_neutral_frozen_source_values(): void
    {
        Storage::fake('local');
        foreach ([['Male', 'He started his affiliation', 'attest his legitimacy'], ['Unsupported', 'The former rebel started their affiliation', 'attest their legitimacy']] as [$gender, $affiliation, $purpose]) {
            [$processing, $japic] = $this->processingWithPhoto();
            $version = $processing->triggeringCdrDocumentVersion;
            $snapshot = $version->content_snapshot;
            $snapshot['content']['gender'] = $gender;
            $version->forceFill(['content_snapshot' => $snapshot])->saveQuietly();
            $this->save($processing, $japic, 'Gender Test');

            $this->actingAs($japic)->get(route('japic.certifications.preview', $processing))
                ->assertOk()->assertSee($affiliation)->assertSee($purpose);
        }
    }

    public function test_for_signing_preview_uses_the_frozen_history_revision(): void
    {
        Storage::fake('local');
        [$processing, $japic] = $this->processingWithPhoto();
        $this->save($processing, $japic, 'Frozen Place');
        app(JapicCertificationWorkflowService::class)->submitForSigning($processing->fresh(), 1, 1, null, $japic);
        $draft = $processing->draft()->firstOrFail();
        $draft->payload = ['schema_version' => 2, 'certificate' => ['narrative_values' => ['surrendered_at' => 'Tampered']]];
        $draft->save();

        $this->actingAs($japic)->get(route('japic.certifications.preview', $processing))
            ->assertOk()->assertSee('Frozen Place')->assertDontSee('Tampered');
    }

    private function save(JapicCertificationProcessing $processing, User $japic, string $place): void
    {
        app(JapicCertificationDraftService::class)->save($processing, ['certificate' => [
            'date_issued' => '2026-09-09',
            'narrative_values' => [
                'fr_name' => 'Preview Subject',
                'residence' => 'Preview Address',
                'former_organization_or_category' => 'former member',
                'areas_of_operation' => 'First Area',
                'affiliated_organization' => 'Test Organization',
                'surrendered_to' => '39IB',
                'surrendered_on' => '2026-08-01',
                'surrendered_at' => $place,
            ],
            'prepared_by' => [['full_name' => 'TEST PREPARER', 'rank' => 'CPT']],
            'attested_by' => [['full_name' => 'TEST ATTESTER', 'rank' => 'CPT']],
        ]], 'CTRL-PREVIEW-'.$processing->id, 0, 0, null, $japic);
    }

    private function processingWithPhoto(): array
    {
        $japic = User::factory()->role('japic')->create();
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Preview City', 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'FR-PREVIEW-'.uniqid(), 'first_name' => 'Preview', 'last_name' => 'Subject', 'category' => 'Regular Member',
            'province' => 'Davao del Sur', 'municipality_id' => $municipality, 'surfaced_at' => '2026-08-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $photo = DB::table('ib39_cdr_photos')->insertGetId(['cdr_processing_id' => $cdr, 'photo_type' => 'fr_photo', 'created_at' => now(), 'updated_at' => now()]);
        $photoVersion = DB::table('ib39_cdr_photo_versions')->insertGetId(['cdr_photo_id' => $photo, 'version_number' => 1, 'storage_path' => 'ib39/test/photo.png', 'original_filename' => 'photo.png',
            'mime_type' => 'image/png', 'size_bytes' => 3, 'sha256' => str_repeat('c', 64), 'uploaded_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('local')->put('ib39/test/photo.png', 'png');
        DB::table('ib39_cdr_photos')->where('id', $photo)->update(['current_photo_version_id' => $photoVersion]);
        $version = Ib39CdrDocumentVersion::query()->forceCreate(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'generated/preview', 'original_filename' => 'preview.html',
            'mime_type' => 'text/html', 'size_bytes' => 1, 'sha256' => str_repeat('d', 64), 'content_schema_version' => 2, 'content_snapshot' => ['content' => ['alias' => 'Alias', 'gender' => 'Female',
                'classification' => 'NPSRL', 'present_address' => 'Address', 'latest_position' => 'Leader', 'organization_affiliation' => 'Organization', 'recruitment_date' => '1998'], 'fr_photo_version_id' => $photoVersion], 'created_by' => $actor->id, 'finalized_at' => now()]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $version->id]);

        return [JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version->id,
            'status' => JapicCertificationStatus::Pending, 'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]), $japic];
    }
}
