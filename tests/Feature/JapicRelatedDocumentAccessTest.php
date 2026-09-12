<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaUploadSlot;
use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JapicRelatedDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_roles_can_read_only_current_final_cdr_and_missing_files_are_not_found(): void
    {
        Storage::fake('local');
        [$japic, $record, $processing, $current] = $this->context(false);
        $cdr = $record->cdrProcessing;
        $ib39 = User::query()->findOrFail($record->created_by);
        Storage::disk('local')->put($current->getRawOriginal('storage_path'), '%PDF-1.4 current');

        $this->actingAs($japic)->get(route('japic.certifications.records.cdr', $processing))
            ->assertOk()->assertSee('current authoritative final CDR')->assertSee('Secure preview')
            ->assertDontSee('CDR History')->assertDontSee('private/cdr');
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', [$cdr, $current]))->assertOk();
        $this->actingAs($japic)->get(route('japic.cdr.documents.download', [$cdr, $current]))->assertOk();
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.cdr', $record))
            ->assertOk()->assertSee('current authoritative final CDR')->assertSee('Secure preview')->assertSee('Secure download');
        $this->actingAs($ib39)->get(route('ib39.cdr.documents.preview', [$cdr, $current]))->assertOk();
        $this->actingAs($ib39)->get(route('ib39.cdr.documents.download', [$cdr, $current]))->assertOk();

        $oldId = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr->id, 'version_number' => 2,
            'source_type' => 'uploaded', 'storage_path' => 'private/cdr/old.pdf', 'original_filename' => 'old.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('d', 64), 'created_by' => $japic->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $old = $cdr->documentVersions()->findOrFail($oldId);
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', [$cdr, $old]))->assertForbidden();

        Storage::disk('local')->delete($current->getRawOriginal('storage_path'));
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', [$cdr, $current]))->assertNotFound();
        $this->assertNotNull($processing);
    }

    public function test_both_roles_have_read_only_fea_access_with_parent_validation(): void
    {
        Storage::fake('local');
        [$japic, $record, $processing] = $this->context(true);
        $fea = $record->feaProcessing;
        $ib39 = User::query()->findOrFail($record->created_by);
        $document = $fea->documents->first();
        $version = Ib39FeaDocumentVersion::query()->forceCreate(['fea_processing_id' => $fea->id, 'fea_document_id' => $document->id,
            'slot' => Ib39FeaUploadSlot::Primary, 'version_number' => 1, 'storage_path' => "ib39/fea/{$fea->id}/test.pdf",
            'original_filename' => 'evidence.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('e', 64), 'uploaded_by' => $japic->id]);
        $document->update(['current_draft_version_id' => $version->id]);
        Storage::disk('local')->put($version->getRawOriginal('storage_path'), '%PDF-1.4 fea');

        $this->actingAs($japic)->get(route('japic.certifications.records.fea', $processing))
            ->assertOk()->assertSee($document->document_type->label())->assertSee($version->slot->label())
            ->assertSee('Secure preview')->assertSee('Secure download')->assertDontSee('Upload');
        $this->actingAs($japic)->get(route('japic.fea.documents.versions.preview', [$fea, $document, $version]))->assertOk();
        $this->actingAs($japic)->get(route('japic.fea.documents.versions.download', [$fea, $document, $version]))->assertOk();
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.fea', $record))
            ->assertOk()->assertSee($document->document_type->label())->assertSee('Secure preview')->assertSee('Secure download');
        $this->actingAs($ib39)->get(route('ib39.fea.documents.versions.preview', [$fea, $document, $version]))->assertOk();
        $this->actingAs($ib39)->get(route('ib39.fea.documents.versions.download', [$fea, $document, $version]))->assertOk();
        $otherDocument = $fea->documents->skip(1)->first();
        $this->actingAs($japic)->get(route('japic.fea.documents.versions.preview', [$fea, $otherDocument, $version]))->assertNotFound();

        $this->actingAs($japic)->post(route('ib39.fea.documents.versions.store', [$fea, $document]))->assertForbidden();
    }

    public function test_record_pages_use_exact_empty_states_and_never_infer_assistance(): void
    {
        [$japic, $record, $processing] = $this->context(false);
        $record->cdrProcessing->update(['current_final_version_id' => null]);

        $this->actingAs($japic)->get(route('japic.certifications.records.cdr', $processing))
            ->assertOk()->assertSee('No completed CDR document is available yet.');
        $this->actingAs($japic)->get(route('japic.certifications.records.fea', $processing))
            ->assertOk()->assertSee('No FEA processing documents are available yet.');
        $this->actingAs($japic)->get(route('japic.certifications.records.assistance', $processing))
            ->assertOk()->assertSee('No assistance records are available yet.');
        $this->actingAs($japic)->get(route('japic.certifications.records.certification', $processing))
            ->assertOk()->assertSee('No final JAPIC certification document is available yet.');

        $ib39 = User::query()->findOrFail($record->created_by);
        foreach ([
            'ib39.fr-profiles.records.cdr' => 'No completed CDR document is available yet.',
            'ib39.fr-profiles.records.fea' => 'No FEA processing documents are available yet.',
            'ib39.fr-profiles.records.assistance' => 'No assistance records are available yet.',
            'ib39.fr-profiles.records.certification' => 'No final JAPIC certification document is available yet.',
        ] as $routeName => $message) {
            $this->actingAs($ib39)->get(route($routeName, $record))->assertOk()->assertSee($message);
        }
        $this->assertSame(0, DB::table('fr_government_assistances')->count());
    }

    public function test_both_roles_can_preview_and_download_only_the_current_final_japic_document(): void
    {
        Storage::fake('local');
        [$japic, $record, $processing] = $this->context(false);
        $ib39 = User::query()->findOrFail($record->created_by);
        $path = "japic/certifications/{$processing->id}/final-documents/current.pdf";
        $current = JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $processing->id, 'version_number' => 1, 'storage_path' => $path,
            'original_filename' => 'certification.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 16,
            'sha256' => str_repeat('a', 64), 'uploaded_by' => $japic->id, 'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $processing->forceFill(['status' => JapicCertificationStatus::Completed, 'current_final_version_id' => $current->id, 'completed_at' => now(), 'completed_by' => $japic->id])->save();
        Storage::disk('local')->put($path, '%PDF-1.4 secure');

        $this->actingAs($japic)->get(route('japic.certifications.records.certification', $processing))
            ->assertOk()->assertSee('Secure preview')->assertSee('Secure download')->assertDontSee($path)->assertDontSee(str_repeat('a', 64));
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.certification', $record))
            ->assertOk()->assertSee('Secure preview')->assertSee('Secure download')->assertDontSee($path)->assertDontSee(str_repeat('a', 64));

        foreach ([
            [$japic, 'japic.certifications.document-versions.preview'],
            [$japic, 'japic.certifications.document-versions.download'],
            [$ib39, 'ib39.japic-certifications.document-versions.preview'],
            [$ib39, 'ib39.japic-certifications.document-versions.download'],
        ] as [$user, $routeName]) {
            $response = $this->actingAs($user)->get(route($routeName, [$processing, $current]))->assertOk();
            $cacheControl = (string) $response->headers->get('Cache-Control');
            foreach (['private', 'no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
                $this->assertStringContainsString($directive, $cacheControl);
            }
        }

        $old = JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $processing->id, 'version_number' => 2, 'storage_path' => "japic/certifications/{$processing->id}/final-documents/old.pdf",
            'original_filename' => 'old.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64),
            'uploaded_by' => $japic->id, 'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $this->actingAs($japic)->get(route('japic.certifications.document-versions.preview', [$processing, $old]))->assertForbidden();

        [, , $otherProcessing] = $this->context(false);
        $this->actingAs($ib39)->get(route('ib39.japic-certifications.document-versions.preview', [$otherProcessing, $current]))->assertNotFound();

        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $unrelated = User::factory()->role('admin')->create();
        $this->actingAs($inactive)->get(route('ib39.japic-certifications.document-versions.preview', [$processing, $current]))->assertRedirect(route('login'));
        $this->actingAs($unrelated)->get(route('ib39.japic-certifications.document-versions.preview', [$processing, $current]))->assertForbidden();
    }

    public function test_all_three_profiles_share_authorized_private_pswdo_document_access(): void
    {
        Storage::fake('local');
        [$japic, $record, $processing] = $this->context(false);
        $ib39 = User::query()->findOrFail($record->created_by);
        $pswdo = User::factory()->role('pswdo')->create();

        $certificationPath = "japic/certifications/{$processing->id}/final-documents/current.pdf";
        $certification = JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $processing->id, 'version_number' => 1, 'storage_path' => $certificationPath,
            'original_filename' => 'certification.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 16,
            'sha256' => str_repeat('a', 64), 'uploaded_by' => $japic->id, 'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $processing->forceFill([
            'status' => JapicCertificationStatus::Completed,
            'current_final_version_id' => $certification->id,
            'completed_at' => now(),
            'completed_by' => $japic->id,
        ])->save();

        $enrollment = PswdoEnrollment::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id]);
        $path = "pswdo/enrollments/{$enrollment->id}/eclip_enrollment_form/final.pdf";
        $document = $enrollment->documents()->create([
            'document_type' => PswdoEnrollmentDocumentType::EclipEnrollmentForm,
            'storage_path' => $path,
            'original_filename' => 'eclip-final.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'sha256' => str_repeat('f', 64),
            'uploaded_by' => $pswdo->id,
            'correct_document_type_confirmed' => true,
            'belongs_to_fr_confirmed' => true,
            'final_signed_confirmed' => true,
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($path, '%PDF-1.4 private');

        foreach ([
            [$ib39, 'ib39.fr-profiles.show', $record, 'ib39.pswdo-enrollment-documents.preview', 'ib39.pswdo-enrollment-documents.download'],
            [$japic, 'japic.certifications.show', $processing, 'japic.pswdo-enrollment-documents.preview', 'japic.pswdo-enrollment-documents.download'],
            [$pswdo, 'pswdo.enrollments.show', $enrollment, 'pswdo.enrollments.documents.preview', 'pswdo.enrollments.documents.download'],
        ] as [$user, $profileRoute, $profileParent, $previewRoute, $downloadRoute]) {
            $profile = $this->actingAs($user)->get(route($profileRoute, $profileParent))->assertOk()
                ->assertSee('E-CLIP Enrollment Form')
                ->assertSee('href="'.route($previewRoute, [$enrollment, $document]).'"', false)
                ->assertSee('href="'.route($downloadRoute, [$enrollment, $document]).'"', false)
                ->assertDontSee($path)
                ->assertDontSee(str_repeat('f', 64));

            $this->assertStringNotContainsString('type="file"', $this->documentsSection($profile->getContent()));
            foreach ([$previewRoute, $downloadRoute] as $routeName) {
                $response = $this->actingAs($user)->get(route($routeName, [$enrollment, $document]))->assertOk();
                $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
                $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            }
        }

        $processing->forceFill(['assigned_to' => $japic->id])->save();
        $otherJapic = User::factory()->role('japic')->create();
        $this->actingAs($otherJapic)->get(route('japic.pswdo-enrollment-documents.preview', [$enrollment, $document]))->assertForbidden();
        auth()->logout();
        $this->get(route('ib39.pswdo-enrollment-documents.preview', [$enrollment, $document]))->assertRedirect(route('login'));
        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->actingAs($inactive)->get(route('ib39.pswdo-enrollment-documents.preview', [$enrollment, $document]))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role('admin')->create())
            ->get(route('ib39.pswdo-enrollment-documents.preview', [$enrollment, $document]))->assertForbidden();

        [, $otherRecord] = $this->context(false);
        $otherEnrollment = PswdoEnrollment::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $otherRecord->id]);
        $this->actingAs($ib39)->get(route('ib39.pswdo-enrollment-documents.preview', [$otherEnrollment, $document]))->assertNotFound();
        $this->actingAs($ib39)->get(route('ib39.pswdo-enrollment-documents.preview', [$enrollment, 999999999]))->assertNotFound();
    }

    private function documentsSection(string $html): string
    {
        $start = strpos($html, 'id="documents-records-heading"');
        $end = $start === false ? false : strpos($html, '</section>', $start);

        return $start === false || $end === false ? '' : substr($html, $start, $end - $start);
    }

    private function context(bool $firearms): array
    {
        $actor = User::factory()->role('39th_ib')->create();
        $japic = User::factory()->role('japic')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Access '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create(['first_name' => 'Secure', 'last_name' => 'Access',
            'category' => Ib39FrCategory::RegularMember->value, 'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => $firearms], $actor)->load('cdrProcessing', 'feaProcessing.documents');
        $cdr = $record->cdrProcessing;
        $versionId = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr->id, 'version_number' => 1,
            'source_type' => 'uploaded', 'storage_path' => "ib39/cdr/{$cdr->id}/final.pdf", 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('c', 64), 'created_by' => $actor->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'current_final_version_id' => $versionId]);
        $processing = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_document_version_id' => $versionId, 'status' => JapicCertificationStatus::Pending,
            'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]);

        return [$japic, $record, $processing, $cdr->documentVersions()->findOrFail($versionId)];
    }
}
