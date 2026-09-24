<?php

namespace Tests\Feature;

use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\JapicCertificationDocumentService;
use App\Support\JapicCertificationDraftSchema;
use App\Support\JapicCertificationUploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class JapicCertificationFinalDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_pending_upload_completes_truthfully_without_fabricating_draft_or_intermediate_events(): void
    {
        [$processing, $japic] = $this->context(JapicCertificationStatus::Pending);

        $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $this->payload($processing))
            ->assertRedirect(route('japic.certifications.show', $processing));

        $completed = $processing->fresh();
        $version = $completed->currentFinalVersion;
        $this->assertSame(JapicCertificationStatus::Completed, $completed->status);
        $this->assertSame($japic->id, $completed->completed_by);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(1, $completed->lock_version);
        $this->assertSame(1, $version->version_number);
        $this->assertTrue($version->all_signatories_confirmed);
        $this->assertTrue($version->correct_final_confirmed);
        $this->assertSame('application/pdf', $version->mime_type);
        $this->assertSame(hash('sha256', $this->pdfContents()), $version->sha256);
        $this->assertStringNotContainsString('unsafe', $version->getRawOriginal('storage_path'));
        $this->assertMatchesRegularExpression('#^japic/certifications/'.$processing->id.'/final-documents/[0-9a-f-]{36}\.pdf$#', $version->getRawOriginal('storage_path'));
        Storage::disk('local')->assertExists($version->getRawOriginal('storage_path'));

        $this->assertDatabaseCount('japic_certification_drafts', 0);
        $this->assertDatabaseCount('japic_certification_draft_histories', 0);
        $this->assertDatabaseMissing('japic_certification_histories', ['processing_id' => $processing->id, 'event' => JapicCertificationEvent::Started->value]);
        $this->assertDatabaseMissing('japic_certification_histories', ['processing_id' => $processing->id, 'event' => JapicCertificationEvent::MarkedForSigning->value]);
        $history = $completed->histories()->where('event', JapicCertificationEvent::FinalUploaded->value)->sole();
        $this->assertSame(JapicCertificationStatus::Pending, $history->from_status);
        $this->assertSame(JapicCertificationStatus::Completed, $history->to_status);
        $this->assertSame('direct_from_pending', data_get($history->metadata, 'completion_path'));
        $this->assertNull(data_get($history->metadata, 'draft_revision'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'japic_certification_final_uploaded',
            'entity_id' => $processing->id,
        ]);
    }

    public function test_drafting_for_signing_and_awaiting_final_upload_can_complete_with_the_real_draft_revision(): void
    {
        foreach ([
            [JapicCertificationStatus::Drafting, 'direct_from_drafting'],
            [JapicCertificationStatus::ForSigning, 'signed_workflow'],
            [JapicCertificationStatus::AwaitingFinalUpload, 'signed_workflow'],
        ] as [$status, $path]) {
            [$processing, $japic] = $this->context($status);
            $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $this->payload($processing))
                ->assertRedirect();
            $completed = $processing->fresh();
            $this->assertSame(JapicCertificationStatus::Completed, $completed->status);
            $history = $completed->histories()->where('event', JapicCertificationEvent::FinalUploaded->value)->sole();
            $this->assertSame($status, $history->from_status);
            $this->assertSame($path, data_get($history->metadata, 'completion_path'));
            $this->assertSame(1, data_get($history->metadata, 'draft_revision'));
        }
    }

    public function test_both_explicit_confirmations_are_required(): void
    {
        foreach (['all_signatories_confirmed', 'correct_final_confirmed'] as $missing) {
            [$processing, $japic] = $this->context(JapicCertificationStatus::Pending);
            $payload = $this->payload($processing);
            unset($payload[$missing]);
            $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $payload)
                ->assertSessionHasErrors($missing);
            $this->assertNull($processing->fresh()->current_final_version_id);
        }
    }

    public function test_pdf_extension_mime_signature_eof_empty_and_size_are_enforced(): void
    {
        [$processing, $japic] = $this->context(JapicCertificationStatus::Pending);
        $invalidFiles = [
            UploadedFile::fake()->createWithContent('wrong.txt', $this->pdfContents())->mimeType('application/pdf'),
            UploadedFile::fake()->createWithContent('wrong-mime.pdf', $this->pdfContents())->mimeType('text/plain'),
            UploadedFile::fake()->createWithContent('wrong-header.pdf', 'not-pdf%%EOF')->mimeType('application/pdf'),
            UploadedFile::fake()->createWithContent('missing-eof.pdf', '%PDF-1.7 incomplete')->mimeType('application/pdf'),
            UploadedFile::fake()->createWithContent('empty.pdf', '')->mimeType('application/pdf'),
            UploadedFile::fake()->create('oversize.pdf', 20481, 'application/pdf'),
        ];

        foreach ($invalidFiles as $file) {
            $payload = $this->payload($processing);
            $payload['document'] = $file;
            $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $payload)
                ->assertSessionHasErrors('document');
        }
        $this->assertDatabaseCount('japic_certification_document_versions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_sanitized_filename_is_metadata_only_and_private_path_is_random(): void
    {
        [$processing, $japic] = $this->context(JapicCertificationStatus::Pending);
        $payload = $this->payload($processing);
        $payload['document'] = UploadedFile::fake()->createWithContent('../../unsafe final (signed).pdf', $this->pdfContents())->mimeType('application/pdf');
        $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $payload)->assertRedirect();

        $version = $processing->fresh()->currentFinalVersion;
        $this->assertSame('unsafe-final-signed.pdf', $version->original_filename);
        $this->assertStringNotContainsString($version->original_filename, $version->getRawOriginal('storage_path'));
    }

    public function test_unauthorized_inactive_other_assignee_stale_duplicate_cancelled_and_completed_uploads_are_rejected(): void
    {
        [$assigned, $owner] = $this->context(JapicCertificationStatus::Pending);
        $other = User::factory()->role('japic')->create();
        $assigned->forceFill(['assigned_to' => $owner->id])->save();
        $this->actingAs($other)->post(route('japic.certifications.final-document.upload', $assigned), $this->payload($assigned))->assertForbidden();
        $this->actingAs(User::factory()->role('39th_ib')->create())
            ->post(route('japic.certifications.final-document.upload', $assigned), $this->payload($assigned))->assertForbidden();
        $inactive = User::factory()->role('japic')->create(['is_active' => false]);
        $this->actingAs($inactive)->post(route('japic.certifications.final-document.upload', $assigned), $this->payload($assigned))->assertRedirect(route('login'));

        [$stale, $staleUser] = $this->context(JapicCertificationStatus::Pending);
        $stalePayload = $this->payload($stale);
        $stale->forceFill(['lock_version' => 1])->save();
        $this->actingAs($staleUser)->post(route('japic.certifications.final-document.upload', $stale), $stalePayload)->assertConflict();

        [$duplicate, $duplicateUser] = $this->context(JapicCertificationStatus::Pending);
        $duplicate->documentVersions()->forceCreate([
            'version_number' => 1,
            'storage_path' => 'japic/certifications/'.$duplicate->id.'/final-documents/existing.pdf',
            'original_filename' => 'existing.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($this->pdfContents()),
            'sha256' => hash('sha256', $this->pdfContents()),
            'uploaded_by' => $duplicateUser->id,
            'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true,
            'uploaded_at' => now(),
        ]);
        $this->actingAs($duplicateUser)->post(route('japic.certifications.final-document.upload', $duplicate), $this->payload($duplicate))
            ->assertSessionHasErrors('document');

        [$cancelled, $cancelledUser] = $this->context(JapicCertificationStatus::Pending);
        DB::table('ib39_fr_cancellations')->insert([
            'ib39_surfaced_former_rebel_id' => $cancelled->ib39_surfaced_former_rebel_id,
            'previous_overall_status' => 'CDR Completed',
            'reason' => encrypt('Cancelled final'),
            'cancelled_by' => $cancelledUser->id,
            'cancelled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($cancelledUser)->post(route('japic.certifications.final-document.upload', $cancelled), $this->payload($cancelled))->assertForbidden();

        [$completed, $completedUser] = $this->context(JapicCertificationStatus::Pending);
        $this->actingAs($completedUser)->post(route('japic.certifications.final-document.upload', $completed), $this->payload($completed))->assertRedirect();
        $this->actingAs($completedUser)->post(route('japic.certifications.final-document.upload', $completed), $this->payload($completed->fresh()))->assertForbidden();
    }

    public function test_incomplete_and_stale_drafts_are_rejected_without_orphan_files(): void
    {
        [$processing, $japic] = $this->context(JapicCertificationStatus::Drafting);
        $draft = $processing->draft()->firstOrFail();
        $draft->forceFill(['payload' => ['schema_version' => 2, 'source_snapshot' => [], 'certificate' => []]])->save();
        $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $this->payload($processing))
            ->assertSessionHasErrors('draft');
        $this->assertSame([], Storage::disk('local')->allFiles());

        $processing->forceFill(['status' => JapicCertificationStatus::Pending])->save();
        $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $this->payload($processing))
            ->assertConflict();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_private_preview_download_and_parent_substitution_are_protected(): void
    {
        [$processing, $japic] = $this->context(JapicCertificationStatus::Pending);
        $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $this->payload($processing))->assertRedirect();
        $completed = $processing->fresh();
        $version = $completed->currentFinalVersion;

        $preview = $this->actingAs($japic)->get(route('japic.certifications.document-versions.preview', [$completed, $version]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="final-signed.pdf"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->actingAs($japic)->get(route('japic.certifications.document-versions.download', [$completed, $version]))
            ->assertOk()->assertDownload('final-signed.pdf');
        $this->assertDatabaseHas('audit_logs', ['action' => 'japic_certification_final_previewed', 'entity_id' => $version->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'japic_certification_final_downloaded', 'entity_id' => $version->id]);

        [$other] = $this->context(JapicCertificationStatus::Pending);
        $this->actingAs($japic)->get(route('japic.certifications.document-versions.preview', [$other, $version]))->assertNotFound();
        $html = $this->actingAs($japic)->get(route('japic.certifications.show', $completed))->assertOk()->getContent();
        $this->assertStringNotContainsString($version->getRawOriginal('storage_path'), $html);
        $this->assertStringNotContainsString('payload', $html);
    }

    public function test_document_versions_are_immutable(): void
    {
        [$processing, $japic] = $this->context(JapicCertificationStatus::Pending);
        $this->actingAs($japic)->post(route('japic.certifications.final-document.upload', $processing), $this->payload($processing))->assertRedirect();
        $version = $processing->fresh()->currentFinalVersion;

        try {
            $version->update(['original_filename' => 'changed.pdf']);
            $this->fail('Immutable version was updated.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
        try {
            $version->delete();
            $this->fail('Immutable version was deleted.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseHas('japic_certification_document_versions', ['id' => $version->id, 'original_filename' => 'final-signed.pdf']);
    }

    public function test_storage_failure_creates_no_document_record(): void
    {
        [$storageFailure, $storageUser] = $this->context(JapicCertificationStatus::Pending);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);
        try {
            app(JapicCertificationDocumentService::class)->uploadFinal(
                $storageFailure,
                $this->pdf(),
                0,
                0,
                true,
                true,
                $storageUser,
                null,
                null,
            );
            $this->fail('Storage failure was accepted.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('japic_certification_document_versions', 0);
    }

    public function test_database_failure_leaves_no_document_record_or_orphan_file(): void
    {
        [$databaseFailure, $databaseUser] = $this->context(JapicCertificationStatus::Pending);
        DB::unprepared("CREATE TRIGGER fail_japic_final_audit BEFORE INSERT ON audit_logs WHEN NEW.action = 'japic_certification_final_uploaded' BEGIN SELECT RAISE(ABORT, 'forced final audit failure'); END");
        try {
            app(JapicCertificationDocumentService::class)->uploadFinal(
                $databaseFailure,
                $this->pdf(),
                0,
                0,
                true,
                true,
                $databaseUser,
                null,
                null,
            );
            $this->fail('Database failure was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('japic_certification_document_versions', 0);
        $this->assertNull($databaseFailure->fresh()->current_final_version_id);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_low_level_pdf_inspector_rejects_invalid_content_even_outside_http_validation(): void
    {
        $this->expectException(ValidationException::class);
        JapicCertificationUploadedFile::inspectFinalPdf(
            UploadedFile::fake()->createWithContent('forged.pdf', '%PDF-1.7 without trailer')->mimeType('application/pdf')
        );
    }

    private function context(JapicCertificationStatus $status): array
    {
        $japic = User::factory()->role('japic')->create();
        $ib39 = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Final '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId([
            'reference_number' => 'FINAL-'.strtoupper(uniqid()),
            'first_name' => 'Final',
            'last_name' => 'Subject',
            'category' => 'Regular Member',
            'province' => 'Davao del Sur',
            'municipality_id' => $municipality,
            'surfaced_at' => '2026-09-01',
            'possessed_firearms' => 0,
            'created_by' => $ib39->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId([
            'ib39_surfaced_former_rebel_id' => $fr,
            'status' => 'Completed',
            'completed_at' => now(),
            'completed_by' => $ib39->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cdrVersion = Ib39CdrDocumentVersion::query()->forceCreate([
            'cdr_processing_id' => $cdr,
            'version_number' => 1,
            'source_type' => 'generated',
            'storage_path' => 'generated/final-source',
            'original_filename' => 'source.html',
            'mime_type' => 'text/html',
            'size_bytes' => 1,
            'sha256' => str_repeat('a', 64),
            'content_schema_version' => 2,
            'content_snapshot' => ['content' => []],
            'created_by' => $ib39->id,
            'finalized_at' => now(),
        ]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $cdrVersion->id]);
        $lockVersion = $status === JapicCertificationStatus::Pending ? 0 : ($status === JapicCertificationStatus::Drafting ? 1 : 2);
        $processing = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $fr,
            'triggering_cdr_document_version_id' => $cdrVersion->id,
            'status' => $status,
            'received_at' => now(),
            'due_at' => now()->addDays(14),
            'lock_version' => $lockVersion,
        ]);
        $processing->histories()->create([
            'from_status' => null,
            'to_status' => JapicCertificationStatus::Pending,
            'event' => JapicCertificationEvent::IntakeCreated,
            'occurred_at' => now(),
        ]);

        if ($status !== JapicCertificationStatus::Pending) {
            $schema = app(JapicCertificationDraftSchema::class);
            $payload = $schema->normalize(['certificate' => [
                'date_issued' => '2026-09-11',
                'narrative_values' => [
                    'fr_name' => 'Final Subject',
                    'residence' => 'Final Address',
                    'former_organization_or_category' => 'Former member',
                    'areas_of_operation' => 'Area One',
                    'affiliated_organization' => 'Organization',
                    'surrendered_to' => '39IB',
                    'surrendered_on' => '2026-09-01',
                    'surrendered_at' => 'Final Place',
                ],
                'prepared_by' => [['full_name' => 'PREPARING OFFICER', 'rank' => 'CPT']],
                'attested_by' => [['full_name' => 'ATTESTING OFFICER', 'rank' => 'LTC']],
            ]], ['gender' => 'Male'], 'FINAL-CONTROL-'.$processing->id, null);
            $processing->draft()->forceCreate([
                'payload' => $payload,
                'schema_version' => 2,
                'revision' => 1,
                'last_saved_by' => $japic->id,
                'last_saved_at' => now(),
            ]);
            if (in_array($status, [JapicCertificationStatus::ForSigning, JapicCertificationStatus::AwaitingFinalUpload], true)) {
                $processing->histories()->create([
                    'actor_id' => $japic->id,
                    'from_status' => JapicCertificationStatus::Drafting,
                    'to_status' => JapicCertificationStatus::ForSigning,
                    'event' => JapicCertificationEvent::MarkedForSigning,
                    'metadata' => ['revision' => 1, 'payload_fingerprint' => $schema->fingerprint($payload)],
                    'occurred_at' => now(),
                ]);
            }
        }

        return [$processing->fresh(), $japic];
    }

    private function payload(JapicCertificationProcessing $processing): array
    {
        return [
            'document' => $this->pdf(),
            'revision' => $processing->draft()->value('revision') ?? 0,
            'lock_version' => $processing->lock_version,
            'all_signatories_confirmed' => '1',
            'correct_final_confirmed' => '1',
        ];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('final signed.pdf', $this->pdfContents())->mimeType('application/pdf');
    }

    private function pdfContents(): string
    {
        return "%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";
    }
}
