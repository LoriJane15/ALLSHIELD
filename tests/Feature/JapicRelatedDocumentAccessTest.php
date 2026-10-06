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
use App\Services\Ib39FeaDraftSchema;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\SurfacedFrDocumentsRecordsService;
use App\Support\JapicCertificationDraftSchema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JapicRelatedDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ib39_viewer_is_gated_until_japic_completion_then_sees_saved_official_draft(): void
    {
        [$japic, $record, $processing] = $this->context(false);
        $ib39 = User::query()->findOrFail($record->created_by);
        $recordsUrl = route('ib39.fr-profiles.records.certification', $record);
        $profileUrl = route('ib39.fr-profiles.show', $record);

        $this->actingAs($ib39)->get(route('japic.certifications.workspace', $processing))
            ->assertRedirect($profileUrl)->assertSessionHas('error', 'Document is not available yet.');
        $this->actingAs($ib39)->get(route('japic.certifications.preview', $processing))
            ->assertRedirect($profileUrl)->assertSessionHas('error', 'Document is not available yet.');

        $schema = app(JapicCertificationDraftSchema::class);
        $processing->draft()->forceCreate([
            'payload' => $schema->initial($schema->sourceSnapshot($processing), null, null),
            'schema_version' => JapicCertificationDraftSchema::VERSION,
            'revision' => 1,
            'last_saved_by' => $japic->id,
            'last_saved_at' => now(),
        ]);
        $processing->forceFill(['status' => JapicCertificationStatus::Completed, 'completed_at' => now(), 'completed_by' => $japic->id])->save();

        $this->actingAs($ib39)->get(route('japic.certifications.workspace', $processing))
            ->assertOk()->assertSee('Official JAPIC certification draft preview')
            ->assertSee('Document History')->assertDontSee('Save Draft')->assertDontSee('Upload Certification');
        $this->actingAs($ib39)->get(route('japic.certifications.preview', $processing))
            ->assertOk()->assertSee('JOINT AFP-PNP');
        $this->actingAs($ib39)->get($recordsUrl)
            ->assertRedirect(route('japic.certifications.workspace', $processing));
    }

    public function test_both_roles_can_read_only_current_final_cdr_and_missing_files_are_not_found(): void
    {
        Storage::fake('local');
        [$japic, $record, $processing, $current] = $this->context(false);
        $cdr = $record->cdrProcessing;
        $ib39 = User::query()->findOrFail($record->created_by);
        Storage::disk('local')->put($current->getRawOriginal('storage_path'), '%PDF-1.4 current');

        $cdr->update(['completed_at' => '2026-09-12 22:33:00']);
        DB::table('ib39_cdr_final_documents')->where('id', $current->id)->update(['finalized_at' => '2026-09-11 09:15:00']);

        $this->actingAs($japic)->get(route('japic.certifications.records.cdr', $processing))
            ->assertRedirect(route('cdr.workspace', $cdr));
        $this->actingAs($japic)->get(route('cdr.workspace', $cdr))
            ->assertOk()->assertSee('Final CDR')->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')->assertSee(route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]), false);
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', $cdr))->assertOk();
        $this->actingAs($japic)->get(route('japic.cdr.documents.download', $cdr))->assertForbidden();
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.cdr', $record))
            ->assertRedirect(route('cdr.workspace', $cdr));
        $this->actingAs($ib39)->get(route('cdr.workspace', $cdr))
            ->assertOk()->assertSee('Final CDR')->assertSee('Comments &amp; Remarks', false)->assertSee('Document History');
        $this->actingAs($ib39)->get(route('ib39.cdr.documents.preview', $cdr))->assertOk();
        $this->actingAs($ib39)->get(route('ib39.cdr.documents.download', $cdr))->assertOk();

        try {
            DB::table('ib39_cdr_final_documents')->insert(['cdr_processing_id' => $cdr->id,
                'source_type' => 'uploaded', 'storage_path' => 'private/cdr/second.pdf', 'original_filename' => 'second.pdf', 'mime_type' => 'application/pdf',
                'size_bytes' => 1, 'sha256' => str_repeat('d', 64), 'created_by' => $japic->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->fail('A second final CDR was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        Storage::disk('local')->delete($current->getRawOriginal('storage_path'));
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', $cdr))->assertNotFound();

        $cdr->update(['completed_at' => null]);
        $this->actingAs($japic)->get(route('japic.certifications.records.cdr', $processing))
            ->assertRedirect(route('cdr.workspace', $cdr));
        DB::table('ib39_cdr_final_documents')->where('id', $current->id)->update(['source_type' => 'generated']);
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', $cdr))
            ->assertOk()->assertDontSee('Print Document');
        $this->actingAs($japic)->get(route('japic.certifications.records.cdr', $processing))
            ->assertRedirect(route('cdr.workspace', $cdr));
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
            'slot' => Ib39FeaUploadSlot::FinalPrimary, 'version_number' => 1, 'storage_path' => "ib39/fea/{$fea->id}/test.pdf",
            'original_filename' => 'evidence.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('e', 64),
            'uploaded_by' => $japic->id, 'created_at' => '2026-09-12 22:33:00', 'updated_at' => '2026-09-12 22:33:00']);
        Storage::disk('local')->put($version->getRawOriginal('storage_path'), '%PDF-1.4 fea');

        $this->actingAs($japic)->get(route('japic.certifications.records.fea', $processing))
            ->assertOk()->assertSee('No documents available');
        $this->actingAs($japic)->get(route('fea.documents.view', [$fea, $document]))
            ->assertRedirect(route('japic.certifications.show', $processing))
            ->assertSessionHas('error', 'Document is not available yet.');
        $this->actingAs($japic)->get(route('japic.fea.documents.versions.preview', [$fea, $document, $version]))
            ->assertRedirect(route('japic.certifications.show', $processing));

        $document->update(['current_final_version_id' => $version->id, 'status' => 'Completed', 'completed_at' => now()]);

        $this->actingAs($japic)->get(route('japic.certifications.records.fea', $processing))
            ->assertOk()->assertSee($document->document_type->label())->assertSee($version->slot->label())
            ->assertSee('Uploaded:')->assertSee('September 12, 2026 · 10:33 PM')
            ->assertSee('View')->assertSee('Download')->assertDontSee('Upload document')
            ->assertSee('href="'.route('fea.documents.view', [$fea, $document]).'"', false);
        $this->actingAs($japic)->get(route('fea.documents.view', [$fea, $document]))
            ->assertOk()->assertSee($document->document_type->label())
            ->assertSee('Comments &amp; Remarks', false)->assertSee('Document History')
            ->assertSee(route('japic.fea.documents.versions.preview', [$fea, $document, $version]), false);
        $this->actingAs($japic)->get(route('japic.fea.documents.versions.preview', [$fea, $document, $version]))->assertOk();
        $this->actingAs($japic)->get(route('japic.fea.documents.versions.download', [$fea, $document, $version]))->assertOk();
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.fea', $record))
            ->assertOk()->assertSee($document->document_type->label())->assertSee('View')->assertSee('Download')
            ->assertSee('href="'.route('fea.documents.view', [$fea, $document]).'"', false);
        $this->actingAs($ib39)->get(route('ib39.fea.documents.versions.preview', [$fea, $document, $version]))->assertOk();
        $this->actingAs($ib39)->get(route('ib39.fea.documents.versions.download', [$fea, $document, $version]))->assertOk();
        $otherDocument = $fea->documents->skip(1)->first();
        $this->actingAs($japic)->get(route('japic.fea.documents.versions.preview', [$fea, $otherDocument, $version]))->assertNotFound();

        $draftDocument = $fea->documents->first(fn ($candidate) => $candidate->id !== $document->id && $candidate->document_type->hasDraftEditor());
        $draftDocument->update([
            'status' => 'Completed',
            'draft_data' => app(Ib39FeaDraftSchema::class)->initial($draftDocument->document_type, $record->display_name),
            'draft_schema_version' => Ib39FeaDraftSchema::VERSION,
            'draft_revision' => 1,
            'draft_saved_at' => now(),
            'draft_saved_by' => $ib39->id,
            'completed_at' => now(),
        ]);
        $this->actingAs($japic)->get(route('fea.documents.view', [$fea, $draftDocument]))
            ->assertOk()->assertSee(route('fea.documents.draft.preview', [$fea, $draftDocument]), false)
            ->assertSee('Comments &amp; Remarks', false)->assertSee('Document History');
        $this->actingAs($japic)->get(route('fea.documents.draft.preview', [$fea, $draftDocument]))
            ->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee($record->display_name);
        $this->actingAs($japic)->get(route('japic.certifications.records.fea', $processing))
            ->assertOk()->assertSee('href="'.route('fea.documents.view', [$fea, $draftDocument]).'"', false);

        $this->actingAs($japic)->post(route('ib39.fea.documents.versions.store', [$fea, $document]))->assertForbidden();

        DB::table('ib39_fea_document_versions')->where('id', $version->id)->update(['created_at' => null]);
        $this->actingAs($japic)->get(route('japic.certifications.records.fea', $processing))
            ->assertOk()->assertSee('Date unavailable');
    }

    public function test_record_pages_use_exact_empty_states_and_never_infer_assistance(): void
    {
        [$japic, $record, $processing] = $this->context(false);
        $record->cdrProcessing->update(['status' => 'Pending', 'completed_at' => null, 'completed_by' => null]);

        $this->actingAs($japic)->get(route('cdr.workspace', $record->cdrProcessing))
            ->assertRedirect(route('japic.certifications.show', $processing))
            ->assertSessionHas('error', 'Document is not available yet.');
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', $record->cdrProcessing))
            ->assertRedirect(route('japic.certifications.show', $processing))
            ->assertSessionHas('error', 'Document is not available yet.');

        $this->actingAs($japic)->get(route('japic.certifications.records.cdr', $processing))
            ->assertRedirect(route('cdr.workspace', $record->cdrProcessing));
        $this->actingAs($japic)->get(route('japic.certifications.records.fea', $processing))
            ->assertOk()->assertSee('No documents available');
        $this->actingAs($japic)->get(route('japic.certifications.records.pswdo', $processing))
            ->assertOk()->assertSee('No documents available');
        $this->actingAs($japic)->get(route('japic.certifications.records.assistance', $processing))
            ->assertOk()->assertSee('No documents available')->assertDontSee('Secure preview')->assertDontSee('Secure download');
        $this->actingAs($japic)->get(route('japic.certifications.records.certification', $processing))
            ->assertRedirect(route('japic.certifications.workspace', $processing));

        $ib39 = User::query()->findOrFail($record->created_by);
        foreach ([
            'ib39.fr-profiles.records.fea',
            'ib39.fr-profiles.records.pswdo',
            'ib39.fr-profiles.records.assistance',
        ] as $routeName) {
            $this->actingAs($ib39)->get(route($routeName, $record))->assertOk()->assertSee('No documents available');
        }
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.cdr', $record))
            ->assertRedirect(route('cdr.workspace', $record->cdrProcessing));
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.certification', $record))
            ->assertRedirect(route('japic.certifications.workspace', $processing));
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
            'correct_final_confirmed' => true, 'uploaded_at' => '2026-09-11 09:15:00',
        ]);
        $processing->forceFill(['status' => JapicCertificationStatus::Completed, 'current_final_version_id' => $current->id,
            'completed_at' => '2026-09-12 22:33:00', 'completed_by' => $japic->id])->save();
        Storage::disk('local')->put($path, '%PDF-1.4 secure');

        $this->actingAs($japic)->get(route('japic.certifications.records.certification', $processing))
            ->assertRedirect(route('japic.certifications.workspace', $processing));
        $this->actingAs($ib39)->get(route('ib39.fr-profiles.records.certification', $record))
            ->assertRedirect(route('japic.certifications.workspace', $processing));
        $this->actingAs($ib39)->get(route('japic.certifications.workspace', $processing))
            ->assertOk()
            ->assertSee('Uploaded final JAPIC certification')
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertSee(route('japic.certifications.document-versions.preview', [$processing, $current, 'file' => 1]), false)
            ->assertDontSee($path)
            ->assertDontSee(str_repeat('a', 64));

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

        $processing->update(['completed_at' => null]);
        $this->actingAs($japic)->get(route('japic.certifications.records.certification', $processing))
            ->assertRedirect(route('japic.certifications.workspace', $processing));
        $current->setAttribute('uploaded_at', null);
        $processing->setRelation('currentFinalVersion', $current);
        $record->setRelation('japicCertificationProcessing', $processing);
        $this->assertSame('Date unavailable', app(SurfacedFrDocumentsRecordsService::class)
            ->certificationRecord($record)['date']['value']);
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
        $documents = collect(PswdoEnrollmentDocumentType::cases())->mapWithKeys(function (PswdoEnrollmentDocumentType $type) use ($enrollment, $pswdo): array {
            $path = "pswdo/enrollments/{$enrollment->id}/{$type->value}/final.pdf";
            $document = $enrollment->documents()->create([
                'document_type' => $type,
                'storage_path' => $path,
                'original_filename' => $type->value.'-internal.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 17,
                'sha256' => hash('sha256', $type->value),
                'uploaded_by' => $pswdo->id,
                'correct_document_type_confirmed' => true,
                'belongs_to_fr_confirmed' => true,
                'final_signed_confirmed' => true,
                'uploaded_at' => '2026-09-12 22:33:00',
            ]);
            Storage::disk('local')->put($path, '%PDF-1.4 private');

            return [$type->value => $document];
        });
        $document = $documents->get(PswdoEnrollmentDocumentType::EclipEnrollmentForm->value);
        $path = $document->getRawOriginal('storage_path');
        $writesBefore = [
            DB::table('pswdo_enrollments')->count(),
            DB::table('pswdo_enrollment_documents')->count(),
        ];

        foreach ([
            [$ib39, 'ib39.fr-profiles.show', $record, 'ib39.fr-profiles.records.pswdo', 'ib39.pswdo-enrollment-documents.preview', 'ib39.pswdo-enrollment-documents.download'],
            [$japic, 'japic.certifications.show', $processing, 'japic.certifications.records.pswdo', 'japic.pswdo-enrollment-documents.preview', 'japic.pswdo-enrollment-documents.download'],
            [$pswdo, 'pswdo.enrollments.show', $enrollment, 'pswdo.enrollments.records.pswdo', 'pswdo.enrollments.documents.preview', 'pswdo.enrollments.documents.download'],
        ] as [$user, $profileRoute, $profileParent, $recordsRoute, $previewRoute, $downloadRoute]) {
            $profile = $this->actingAs($user)->get(route($profileRoute, $profileParent))->assertOk()
                ->assertSee('href="'.route($recordsRoute, $profileParent).'"', false)
                ->assertDontSee('E-CLIP Enrollment Form')
                ->assertDontSee('Secure preview')
                ->assertDontSee('Secure download')
                ->assertDontSee($path)
                ->assertDontSee(str_repeat('f', 64));

            $this->assertStringNotContainsString('type="file"', $this->documentsSection($profile->getContent()));
            DB::flushQueryLog();
            DB::enableQueryLog();
            $recordsPage = $this->actingAs($user)->get(route($recordsRoute, $profileParent))->assertOk()
                ->assertSee('Uploaded:')->assertSee('September 12, 2026 · 10:33 PM')
                ->assertDontSee($path)
                ->assertDontSee('pswdo_enrollment_documents')->assertDontSee('PswdoEnrollmentDocument');
            foreach (PswdoEnrollmentDocumentType::cases() as $type) {
                $item = $documents->get($type->value);
                $recordsPage->assertSee($type->label())
                    ->assertSee('href="'.route('pswdo.enrollments.workspace', [$enrollment, $item]).'"', false)
                    ->assertSee('href="'.route($downloadRoute, [$enrollment, $item]).'"', false)
                    ->assertDontSee($item->getRawOriginal('original_filename'))
                    ->assertDontSee($item->getRawOriginal('storage_path'))
                    ->assertDontSee($item->getRawOriginal('sha256'));
            }
            $documentQueries = collect(DB::getQueryLog())->filter(
                fn (array $query): bool => str_contains(strtolower($query['query']), 'pswdo_enrollment_documents')
            );
            $this->assertCount(1, $documentQueries);
            $this->actingAs($user)->get(route('pswdo.enrollments.workspace', [$enrollment, $document]))
                ->assertOk()
                ->assertSee($document->document_type->label())
                ->assertSee('Comments &amp; Remarks', false)
                ->assertSee('Document History')
                ->assertSee(route($previewRoute, [$enrollment, $document]), false);
            foreach ([$previewRoute, $downloadRoute] as $routeName) {
                $response = $this->actingAs($user)->get(route($routeName, [$enrollment, $document]))->assertOk();
                $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
                $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
                if ($routeName === $previewRoute && ! $user->hasRole('pswdo')) {
                    $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
                }
            }
        }
        $this->assertSame($writesBefore, [
            DB::table('pswdo_enrollments')->count(),
            DB::table('pswdo_enrollment_documents')->count(),
        ]);

        [$unconfirmedJapic, $unconfirmedRecord, $unconfirmedProcessing] = $this->context(false);
        $unconfirmedEnrollment = PswdoEnrollment::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $unconfirmedRecord->id]);
        $unconfirmedDocument = $unconfirmedEnrollment->documents()->create([
            'document_type' => PswdoEnrollmentDocumentType::EclipEnrollmentForm,
            'storage_path' => "pswdo/enrollments/{$unconfirmedEnrollment->id}/eclip_enrollment_form/unconfirmed.pdf",
            'original_filename' => 'unconfirmed.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 17,
            'sha256' => str_repeat('0', 64),
            'uploaded_by' => $pswdo->id,
            'correct_document_type_confirmed' => true,
            'belongs_to_fr_confirmed' => true,
            'final_signed_confirmed' => false,
            'uploaded_at' => now(),
        ]);
        $this->actingAs($unconfirmedJapic)->get(route('pswdo.enrollments.workspace', [$unconfirmedEnrollment, $unconfirmedDocument]))
            ->assertRedirect(route('japic.certifications.show', $unconfirmedProcessing))
            ->assertSessionHas('error', 'Document is not available yet.');
        $this->actingAs($unconfirmedJapic)->get(route('japic.pswdo-enrollment-documents.preview', [$unconfirmedEnrollment, $unconfirmedDocument]))
            ->assertRedirect(route('japic.certifications.show', $unconfirmedProcessing))
            ->assertSessionHas('error', 'Document is not available yet.');

        $unconfirmedEnrollment->setRelation('documents', collect([$unconfirmedDocument]));
        $unconfirmedRecord->setRelation('pswdoEnrollment', $unconfirmedEnrollment);
        $unavailable = app(SurfacedFrDocumentsRecordsService::class)->pswdoRecords($unconfirmedRecord, fn (): string => '', fn (): string => '');
        $this->assertTrue($unavailable->isEmpty());

        $processing->forceFill(['assigned_to' => $japic->id])->save();
        $otherJapic = User::factory()->role('japic')->create();
        $this->actingAs($otherJapic)->get(route('japic.certifications.records.pswdo', $processing))->assertForbidden();
        $this->actingAs($otherJapic)->get(route('japic.pswdo-enrollment-documents.preview', [$enrollment, $document]))->assertForbidden();
        auth()->logout();
        $this->get(route('ib39.fr-profiles.records.pswdo', $record))->assertRedirect(route('login'));
        $this->get(route('ib39.pswdo-enrollment-documents.preview', [$enrollment, $document]))->assertRedirect(route('login'));
        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->actingAs($inactive)->get(route('ib39.fr-profiles.records.pswdo', $record))->assertRedirect(route('login'));
        $this->actingAs($inactive)->get(route('ib39.pswdo-enrollment-documents.preview', [$enrollment, $document]))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role('admin')->create())
            ->get(route('ib39.fr-profiles.records.pswdo', $record))->assertForbidden();
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
        $versionId = DB::table('ib39_cdr_final_documents')->insertGetId(['cdr_processing_id' => $cdr->id,
            'source_type' => 'uploaded', 'storage_path' => "ib39/cdr/{$cdr->id}/final.pdf", 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('c', 64), 'created_by' => $actor->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id]);
        $processing = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_final_document_id' => $versionId, 'status' => JapicCertificationStatus::Pending,
            'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]);

        return [$japic, $record, $processing, $cdr->finalDocument()->findOrFail($versionId)];
    }
}
