<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Ib39CdrDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actor = User::factory()->role('39th_ib')->create();
        $this->record = $this->record('Stage Five');
    }

    public function test_every_new_route_rejects_guests_inactive_accounts_and_other_roles(): void
    {
        $cdr = $this->record->cdrProcessing;
        $version = $this->upload($cdr);
        $routes = [
            ['get', route('ib39.cdr.documents.preview', [$cdr, $version])],
            ['get', route('ib39.cdr.documents.download', [$cdr, $version])],
            ['post', route('ib39.cdr.documents.replace', $cdr)],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect();
        }

        foreach ([User::factory()->role('lgu')->create(), User::factory()->role('39th_ib')->create(['is_active' => false])] as $user) {
            foreach ($routes as [$method, $url]) {
                $response = $this->actingAs($user)->{$method}($url);
                $user->is_active ? $response->assertForbidden() : $response->assertRedirect(route('login'));
            }
        }
    }

    public function test_pdf_jpeg_and_png_are_accepted_with_private_random_server_owned_metadata(): void
    {
        foreach ([$this->pdf(), $this->jpeg(), $this->png()] as $index => $file) {
            $record = $index === 0 ? $this->record : $this->record('Allowed '.$index);
            $cdr = $record->cdrProcessing;
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
                'document' => $file, 'confirmed' => '1', 'version_number' => null,
            ])->assertSessionHasErrors('version_number');
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), ['document' => $file, 'confirmed' => '1'])->assertRedirect();

            $version = $cdr->documentVersions()->sole();
            $this->assertSame(Ib39CdrDocumentSource::Uploaded, $version->source_type);
            $this->assertStringStartsWith("ib39/cdr/{$cdr->id}/final-documents/", $version->getRawOriginal('storage_path'));
            $this->assertDoesNotMatchRegularExpression('/stage|allowed/i', basename($version->getRawOriginal('storage_path')));
            $this->assertSame(64, strlen($version->sha256));
            $this->assertArrayNotHasKey('storage_path', $version->toArray());
            Storage::disk('local')->assertExists($version->getRawOriginal('storage_path'));
        }
    }

    public function test_unsupported_spoofed_malformed_and_oversized_files_are_rejected(): void
    {
        $files = [
            UploadedFile::fake()->createWithContent('document.docx', 'PK synthetic'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg/>'),
            UploadedFile::fake()->createWithContent('page.html', '<html/>'),
            UploadedFile::fake()->createWithContent('program.exe', "MZ\0\0"),
            UploadedFile::fake()->createWithContent('spoofed.pdf', '<html>not pdf</html>'),
            UploadedFile::fake()->createWithContent('truncated.pdf', '%PDF-1.4 incomplete'),
            UploadedFile::fake()->createWithContent('malformed.png', 'not an image'),
        ];

        foreach ($files as $file) {
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $this->record->cdrProcessing), ['document' => $file, 'confirmed' => '1'])
                ->assertSessionHasErrors('document');
        }
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $this->record->cdrProcessing), [
            'document' => UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf'), 'confirmed' => '1',
        ])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('ib39_cdr_document_versions', 0);
    }

    public function test_direct_upload_requires_confirmation_and_completes_pending_or_ongoing_without_using_draft(): void
    {
        foreach ([Ib39CdrStatus::Pending, Ib39CdrStatus::Ongoing] as $status) {
            $record = $status === Ib39CdrStatus::Pending ? $this->record : $this->record('Ongoing');
            $cdr = $record->cdrProcessing;
            if ($status === Ib39CdrStatus::Ongoing) {
                $cdr->update(['status' => $status, 'started_at' => now()]);
            }
            $cdr->form->update(['content' => ['assessment' => '   ']]);
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), ['document' => $this->pdf()])->assertSessionHasErrors('confirmed');
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), ['document' => $this->pdf(), 'confirmed' => '1'])->assertRedirect();

            $completed = $cdr->fresh();
            $version = $completed->currentFinalVersion;
            $this->assertSame(Ib39CdrStatus::Completed, $completed->status);
            $this->assertSame($this->actor->id, $completed->completed_by);
            $this->assertNotNull($completed->completed_at);
            $this->assertSame(1, $version->version_number);
            $this->assertNull($version->content_snapshot);
            $this->assertDatabaseHas('ib39_cdr_status_histories', ['document_version_id' => $version->id, 'event' => 'direct_final_uploaded']);
        }
    }

    public function test_repeated_direct_and_replacement_submissions_do_not_create_duplicate_versions(): void
    {
        $cdr = $this->record->cdrProcessing;
        $payload = ['document' => $this->pdf(), 'confirmed' => '1'];
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), $payload)->assertRedirect();
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), $payload)->assertForbidden();
        $this->assertDatabaseCount('ib39_cdr_document_versions', 1);

        $replacement = ['document' => $this->png(), 'replacement_reason' => 'Corrected signed copy', 'confirmed' => '1'];
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.replace', $cdr), $replacement)->assertRedirect();
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.replace', $cdr), $replacement)->assertRedirect();
        $this->assertDatabaseCount('ib39_cdr_document_versions', 2);
    }

    public function test_replacement_requires_reason_preserves_versions_files_and_original_completion_time(): void
    {
        $cdr = $this->record->cdrProcessing;
        $first = $this->upload($cdr);
        $completedAt = $cdr->fresh()->completed_at->copy();
        $firstPath = $first->getRawOriginal('storage_path');
        $this->travel(1)->day();
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.replace', $cdr), ['document' => $this->png(), 'replacement_reason' => '   ', 'confirmed' => '1'])->assertSessionHasErrors('replacement_reason');
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.replace', $cdr), ['document' => $this->png(), 'replacement_reason' => 'Corrected approval page', 'confirmed' => '1'])->assertRedirect();

        $versions = $cdr->documentVersions()->orderBy('version_number')->get();
        $this->assertCount(2, $versions);
        $this->assertSame($versions[0]->id, $versions[1]->replaces_version_id);
        $this->assertSame('Corrected approval page', $versions[1]->replacement_reason);
        $this->assertTrue($completedAt->equalTo($cdr->fresh()->completed_at));
        $this->assertTrue($versions[1]->finalized_at->greaterThan($completedAt));
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($versions[1]->getRawOriginal('storage_path'));
    }

    public function test_database_failure_removes_only_staged_replacement_and_preserves_current_version(): void
    {
        $cdr = $this->record->cdrProcessing;
        $first = $this->upload($cdr);
        $firstPath = $first->getRawOriginal('storage_path');
        DB::unprepared("CREATE TRIGGER fail_stage5_audit BEFORE INSERT ON audit_logs WHEN NEW.action = 'ib39_cdr_final_document_replaced' BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.replace', $cdr), ['document' => $this->png(), 'replacement_reason' => 'Fails safely', 'confirmed' => '1']);
            $this->fail('The simulated database failure did not abort replacement.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('simulated failure', $exception->getMessage());
        }

        $this->assertSame($first->id, $cdr->fresh()->current_final_version_id);
        $this->assertDatabaseCount('ib39_cdr_document_versions', 1);
        Storage::disk('local')->assertExists($firstPath);
        $this->assertCount(1, Storage::disk('local')->allFiles("ib39/cdr/{$cdr->id}/final-documents"));
    }

    public function test_preview_download_headers_missing_files_cross_cdr_scope_and_safe_access_audits(): void
    {
        $cdr = $this->record->cdrProcessing;
        $version = $this->upload($cdr);
        $preview = route('ib39.cdr.documents.preview', [$cdr, $version]);
        $download = route('ib39.cdr.documents.download', [$cdr, $version]);

        $previewResponse = $this->actingAs($this->actor)->get($preview)->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="completed-cdr.pdf"')
            ->assertHeader('Pragma', 'no-cache')->assertHeader('X-Content-Type-Options', 'nosniff');
        foreach (['private', 'no-store', 'no-cache'] as $directive) {
            $this->assertStringContainsString($directive, $previewResponse->headers->get('Cache-Control'));
        }
        $this->actingAs($this->actor)->get($download)->assertOk()->assertDownload('completed-cdr.pdf');
        $this->assertDatabaseHas('audit_logs', ['action' => 'ib39_cdr_final_document_preview', 'entity_id' => $version->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ib39_cdr_final_document_download', 'entity_id' => $version->id]);
        $auditText = AuditLog::query()->where('entity_id', $version->id)->pluck('new_values')->implode(' ');
        $this->assertStringNotContainsString($version->getRawOriginal('storage_path'), $auditText);
        $this->assertStringNotContainsString($version->sha256, $auditText);

        $other = $this->record('Other')->cdrProcessing;
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.preview', [$other, $version]))->assertForbidden();
        Storage::disk('local')->delete($version->getRawOriginal('storage_path'));
        $this->actingAs($this->actor)->get($preview)->assertNotFound();
    }

    public function test_history_ui_shows_safe_version_details_and_generated_versions_remain_snapshot_printable(): void
    {
        $cdr = $this->record->cdrProcessing;
        $cdr->update(['status' => Ib39CdrStatus::Ongoing, 'started_at' => now()]);
        $cdr->form->update(['content' => ['assessment' => 'Immutable generated Stage Five snapshot']]);
        $review = $this->actingAs($this->actor)->get(route('ib39.cdr.finalization.review', $cdr))->getContent();
        preg_match('/name="draft_fingerprint" value="([a-f0-9]{64})"/', $review, $matches);
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), ['confirmed' => '1', 'draft_fingerprint' => $matches[1]])->assertRedirect();
        $generated = $cdr->fresh()->currentFinalVersion;

        $workspace = $this->actingAs($this->actor)->get(route('ib39.cdr.show', $cdr))->assertOk()
            ->assertSee('Final-document version history')->assertSee('System Generated')->assertSee('Current')->assertSee('Print');
        $this->assertStringNotContainsString($generated->getRawOriginal('storage_path'), $workspace->getContent());
        $this->assertStringNotContainsString($generated->sha256, $workspace->getContent());
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.preview', [$cdr, $generated]))->assertOk()->assertSee('Immutable generated Stage Five snapshot')->assertSee('FINAL COPY');
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.print', [$cdr, $generated]))->assertOk()->assertSee('Immutable generated Stage Five snapshot');
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.download', [$cdr, $generated]))->assertForbidden();
    }

    public function test_stage5_creates_no_external_workflow_records(): void
    {
        $tables = ['fr_government_assistances'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $this->upload($this->record->cdrProcessing);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
    }

    private function upload($cdr): Ib39CdrDocumentVersion
    {
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), ['document' => $this->pdf(), 'confirmed' => '1'])->assertRedirect();

        return $cdr->documentVersions()->latest('version_number')->firstOrFail();
    }

    private function record(string $lastName): Ib39SurfacedFormerRebel
    {
        $municipality = Municipality::query()->create(['name' => 'Stage 5 '.uniqid()]);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Stage 5 Barangay']);

        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => $lastName, 'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id, 'surfaced_at' => '2026-08-31', 'possessed_firearms' => false,
        ], $this->actor);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('completed-cdr.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('completed-cdr.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    }

    private function jpeg(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('completed-cdr.jpg', base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true));
    }
}
