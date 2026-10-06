<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39CdrComment;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelCancellationService;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\JapicCertificationIntakeService;
use App\Services\PswdoEnrollmentIntakeService;
use App\Support\Ib39CdrFormSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Ib39CdrWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-09-29 22:25:00'));

        $municipality = Municipality::query()->create(['name' => 'CDR Workspace Municipality']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'CDR Workspace Barangay',
        ]);
        $this->actor = User::factory()->role('39th_ib')->create(['name' => 'Juan Dela Cruz']);
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Workspace',
            'last_name' => 'Record',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-09-29',
            'possessed_firearms' => false,
        ], $this->actor);
    }

    public function test_pending_and_ongoing_workspace_open_directly_in_the_existing_editor(): void
    {
        $cdr = $this->record->cdrProcessing;

        $this->actingAs($this->actor)->get(route('ib39.cdr.show', $cdr))
            ->assertRedirect(route('ib39.cdr.edit', $cdr));
        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))
            ->assertOk()
            ->assertSee('CDR Drafting Workspace')
            ->assertSee('Custodial Debriefing Report')
            ->assertSee('All changes saved')
            ->assertSee('Preview Draft')
            ->assertSee('Save Draft')
            ->assertDontSee('Draft Editor')
            ->assertDontSee('Submit as Final')
            ->assertDontSee('Save FR Photo')
            ->assertSee('Document History')
            ->assertSee('Comments');

        $this->actingAs($this->actor)->post(route('ib39.cdr.start', $cdr))->assertRedirect();
        $this->assertSame(Ib39CdrStatus::Ongoing, $cdr->fresh()->status);
        $this->actingAs($this->actor)->get(route('ib39.cdr.show', $cdr))
            ->assertRedirect(route('ib39.cdr.edit', $cdr));
    }

    public function test_completed_workspace_stays_read_only_and_cancelled_workspace_is_not_redirected_into_editing(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), [
            'save_intent' => 'manual',
            'content' => ['assessment' => 'Completed workspace assessment'],
        ])->assertRedirect();
        $this->uploadFinal($cdr);

        $this->actingAs($this->actor)->get(route('ib39.cdr.show', $cdr))
            ->assertOk()
            ->assertSee('Uploaded Final CDR')
            ->assertSee('Completed workspace assessment')
            ->assertSee('fieldset class="cdr-read-only-fields" disabled', false)
            ->assertSee('View Uploaded CDR')
            ->assertSee('Completed / Read-only')
            ->assertSee('Preview Draft')
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertSee('name="content[assessment]"', false)
            ->assertDontSee('id="autoSaveIndicator"', false)
            ->assertDontSee('fetch(draftForm.action', false)
            ->assertDontSee('id="btnManualSaveDraft"', false)
            ->assertDontSee('Confirm final CDR')
            ->assertDontSee('Replace CDR')
            ->assertDontSee('Version 1')
            ->assertDontSee('v1');
        $this->actingAs($this->actor)->get(route('ib39.cdr.preview', $cdr))
            ->assertOk()
            ->assertSee('Completed workspace assessment')
            ->assertSee('>Print</a>', false)
            ->assertSee('Download');
        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertForbidden();
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), [
            'save_intent' => 'manual',
            'content' => ['assessment' => 'Forbidden completed edit'],
        ])->assertForbidden();

        $second = $this->newRecord('Cancelled');
        app(Ib39SurfacedFormerRebelCancellationService::class)
            ->cancel($second, 'Approved test cancellation.', $this->actor);

        $this->actingAs($this->actor)->get(route('ib39.cdr.show', $second->cdrProcessing))
            ->assertForbidden();
        $this->actingAs($this->actor)->get(route('cdr.workspace', $second->cdrProcessing))
            ->assertForbidden();
    }

    public function test_history_uses_persisted_actions_in_chronological_order_and_viewing_creates_none(): void
    {
        $cdr = $this->record->cdrProcessing;
        $initialCount = $cdr->statusHistories()->count();

        $this->actingAs($this->actor)->get(route('ib39.cdr.show', $cdr))->assertRedirect();
        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertOk();
        $this->assertSame($initialCount, $cdr->statusHistories()->count());

        $this->travelTo(Carbon::parse('2026-09-29 22:48:00'));
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), [
            'save_intent' => 'manual',
            'content' => ['assessment' => 'Initial assessment'],
        ])->assertRedirect();

        $this->travelTo(Carbon::parse('2026-09-29 23:01:00'));
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), [
            'save_intent' => 'manual',
            'content' => ['assessment' => 'Updated assessment'],
        ])->assertRedirect();

        $events = $cdr->statusHistories()->oldest()->pluck('event')->all();
        $this->assertSame(['surfaced_fr_created', 'draft_processing_started', 'draft_saved', 'draft_updated'], $events);

        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))
            ->assertOk()
            ->assertSeeInOrder(['Draft Created', 'Drafting Started', 'Saved as Draft', 'Draft Updated'])
            ->assertSee('Juan Dela Cruz')
            ->assertSee('39th IB')
            ->assertSee('Sep 29, 2026 · 11:01 PM');
    }

    public function test_completed_upload_event_displays_without_version_or_replacement_ui(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->uploadFinal($cdr);

        $this->actingAs($this->actor)->get(route('ib39.cdr.show', $cdr))
            ->assertOk()
            ->assertSee('Final CDR Uploaded')
            ->assertSee('completed-cdr.pdf')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('39th IB')
            ->assertSee('datetime=', false)
            ->assertDontSee('Version 1')
            ->assertDontSee('Replace CDR')
            ->assertSee('Sep 29, 2026');
    }

    public function test_system_authored_finalization_displays_the_real_completed_event(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->createHistoricalGeneratedFinal($cdr, []);

        $this->actingAs($this->actor)->get(route('ib39.cdr.show', $cdr))
            ->assertOk()
            ->assertSee('Completed')
            ->assertDontSee('Version 1')
            ->assertSee('View Completed CDR')
            ->assertSee('fieldset class="cdr-read-only-fields" disabled', false)
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertSee('name="content[assessment]"', false)
            ->assertDontSee('id="btnManualSaveDraft"', false);
    }

    public function test_authorized_comment_is_trimmed_persisted_attributed_displayed_and_html_escaped(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->travelTo(Carbon::parse('2026-09-29 23:01:00'));

        $this->actingAs($this->actor)->from(route('ib39.cdr.edit', $cdr))
            ->post(route('cdr.comments.store', $cdr), [
                'text' => '  <script>alert(1)</script> Updated Section C.  ',
                'author_role' => 'super_admin',
            ])
            ->assertRedirect(route('ib39.cdr.edit', $cdr).'#cdr-comments')
            ->assertSessionHas('status', 'Comment posted.');

        $comment = Ib39CdrComment::query()->sole();
        $this->assertSame($this->actor->id, $comment->user_id);
        $this->assertSame('39th_ib', $comment->author_role);
        $this->assertSame('<script>alert(1)</script> Updated Section C.', $comment->text);

        $response = $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertOk();
        $response->assertSee('Juan Dela Cruz')
            ->assertSee('39th IB')
            ->assertSee('Sep 29, 2026 · 11:01 PM')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Updated Section C.', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_comment_validation_and_unauthorized_access_are_enforced(): void
    {
        $cdr = $this->record->cdrProcessing;

        $this->actingAs($this->actor)->post(route('cdr.comments.store', $cdr), ['text' => " \n\t "])
            ->assertSessionHasErrors('text');
        $this->actingAs($this->actor)->post(route('cdr.comments.store', $cdr), ['text' => str_repeat('x', 2001)])
            ->assertSessionHasErrors('text');

        $unauthorized = User::factory()->role('lgu')->create();
        $this->actingAs($unauthorized)->post(route('cdr.comments.store', $cdr), ['text' => 'Denied'])
            ->assertForbidden();
        $this->assertDatabaseCount('ib39_cdr_comments', 0);
    }

    public function test_authorized_japic_document_viewer_can_comment_but_cannot_edit_the_cdr(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->uploadFinal($cdr);
        $japic = User::factory()->role('japic')->create(['name' => 'Maria Santos']);

        $this->actingAs($japic)->post(route('cdr.comments.store', $cdr), ['text' => 'Please verify Section C.'])
            ->assertRedirect();
        $this->assertDatabaseHas('ib39_cdr_comments', [
            'cdr_processing_id' => $cdr->id,
            'user_id' => $japic->id,
            'author_role' => 'japic',
            'text' => 'Please verify Section C.',
        ]);

        $this->actingAs($japic)->get(route('ib39.cdr.edit', $cdr))->assertForbidden();
        $this->actingAs($japic)->put(route('ib39.cdr.update', $cdr), [
            'content' => ['assessment' => 'Unauthorized edit'],
        ])->assertForbidden();
    }

    public function test_ib39_japic_and_pswdo_share_one_chronological_cdr_comment_thread_without_edit_access(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->uploadFinal($cdr);
        $japic = User::factory()->role('japic')->create(['name' => 'Maria Santos']);
        $pswdo = User::factory()->role('pswdo')->create(['name' => 'Ana Reyes']);
        $certification = JapicCertificationProcessing::query()
            ->where('ib39_surfaced_former_rebel_id', $this->record->id)
            ->firstOrFail();
        $final = $certification->documentVersions()->create([
            'version_number' => 1,
            'storage_path' => "japic/certifications/{$certification->id}/final-documents/final.pdf",
            'original_filename' => 'certification.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'sha256' => str_repeat('b', 64),
            'uploaded_by' => $japic->id,
            'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true,
            'uploaded_at' => now(),
        ]);
        $certification->forceFill([
            'status' => 'Completed',
            'current_final_version_id' => $final->id,
            'completed_at' => now(),
            'completed_by' => $japic->id,
        ])->save();
        $enrollment = DB::transaction(fn (): ?PswdoEnrollment => app(PswdoEnrollmentIntakeService::class)->receiveEligible($this->record->id));
        $this->assertNotNull($enrollment);

        $viewers = [$this->actor, $japic, $pswdo];
        $comments = [
            [$this->actor, 'Please review Section C.'],
            [$japic, 'Certification details verified.'],
            [$pswdo, 'Please clarify supporting document.'],
        ];

        foreach ($comments as $index => [$author, $text]) {
            $this->travelTo(Carbon::parse('2026-09-29 22:26:00')->addMinutes(min($index, 1)));
            $this->actingAs($author)->from(route('cdr.workspace', $cdr))
                ->post(route('cdr.comments.store', $cdr), ['text' => $text])
                ->assertRedirect(route('cdr.workspace', $cdr))
                ->assertSessionHas('status', 'Comment posted.');

            foreach ($viewers as $viewer) {
                $response = $this->actingAs($viewer)->get(route('cdr.workspace', $cdr))->assertOk();
                $response->assertSeeInOrder(array_column(array_slice($comments, 0, $index + 1), 1));
                $response->assertSee($author->name)
                    ->assertSee($author->role === '39th_ib' ? '39th IB' : strtoupper($author->role))
                    ->assertSee(now()->format('M d, Y'));
            }
        }

        $this->assertSame(
            array_column($comments, 1),
            $cdr->comments()->orderBy('created_at')->orderBy('id')->pluck('text')->all(),
        );
        $this->assertSame(3, $cdr->comments()->count());

        foreach ([$japic, $pswdo] as $viewer) {
            $this->actingAs($viewer)->get(route('ib39.cdr.edit', $cdr))->assertForbidden();
            $this->actingAs($viewer)->put(route('ib39.cdr.update', $cdr), [
                'content' => ['assessment' => 'Unauthorized edit'],
            ])->assertForbidden();
            $this->actingAs($viewer)->post(route('ib39.cdr.documents.upload', $cdr), [])->assertForbidden();
            $this->actingAs($viewer)->get(route('ib39.cdr.documents.download', $cdr))->assertForbidden();
        }

        $unrelated = User::factory()->role('lgu')->create();
        $this->actingAs($unrelated)->get(route('cdr.workspace', $cdr))->assertForbidden();
        $this->actingAs($unrelated)->post(route('cdr.comments.store', $cdr), ['text' => 'Private note'])->assertForbidden();
        $this->assertSame(3, $cdr->comments()->count());
    }

    public function test_authorized_japic_viewer_sees_the_uploaded_final_instead_of_the_generated_preview(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), [
            'save_intent' => 'manual',
            'content' => ['alias' => 'Workspace Alias', 'assessment' => 'Stored assessment'],
        ])->assertRedirect();
        $this->uploadFinal($cdr);
        $japic = User::factory()->role('japic')->create(['name' => 'Maria Santos']);

        $this->actingAs($japic)->get(route('cdr.workspace', $cdr))
            ->assertOk()
            ->assertSee('Final CDR')
            ->assertSee('src="'.route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]).'#toolbar=0&amp;navpanes=0"', false)
            ->assertDontSee('CDR Document Preview')
            ->assertDontSee('Workspace Alias')
            ->assertDontSee('Stored assessment')
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertDontSee('name="content[assessment]"', false)
            ->assertDontSee('Save draft')
            ->assertDontSee('Upload CDR')
            ->assertDontSee('Submit as Final')
            ->assertDontSee('Download CDR')
            ->assertDontSee('Print Document')
            ->assertDontSee($cdr->fresh()->finalDocument->getRawOriginal('storage_path'))
            ->assertDontSee('autoSaveIndicator');

        $this->actingAs($japic)->withSession(['_old_input' => ['content' => ['alias' => 'Forged preview']]])
            ->get(route('cdr.workspace', $cdr))
            ->assertOk()->assertDontSee('Workspace Alias')->assertDontSee('Forged preview');

        $preview = $this->actingAs($japic)->get(route('cdr.documents.preview', $cdr))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Disposition', 'inline; filename="completed-cdr.pdf"');
        $this->assertStringStartsWith('%PDF-1.4', $preview->streamedContent());
        $this->actingAs($japic)->get(route('japic.cdr.documents.preview', $cdr))
            ->assertOk()->assertHeader('X-Frame-Options', 'DENY');

        $this->actingAs($japic)->from(route('cdr.workspace', $cdr))
            ->post(route('cdr.comments.store', $cdr), ['text' => 'Review note'])
            ->assertRedirect(route('cdr.workspace', $cdr));
        $this->actingAs($japic)->get(route('cdr.workspace', $cdr))
            ->assertOk()->assertSee('Review note')->assertSee('Maria Santos');

        $this->actingAs($japic)->get(route('ib39.cdr.edit', $cdr))->assertForbidden();
        $this->actingAs($japic)->put(route('ib39.cdr.update', $cdr), [
            'content' => ['assessment' => 'Unauthorized edit'],
        ])->assertForbidden();
        $this->actingAs($japic)->get(route('japic.cdr.documents.download', $cdr))->assertForbidden();
        $this->actingAs($japic)->post(route('ib39.cdr.documents.upload', $cdr), [])->assertForbidden();
        $this->actingAs($this->actor)->get(route('ib39.cdr.documents.download', $cdr))->assertOk();
    }

    public function test_generated_final_uses_the_official_persisted_form_preview_for_japic(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->post(route('ib39.cdr.photos.store', $cdr), [
            'photo_type' => 'fr_photo',
            'photo' => UploadedFile::fake()->image('official-subject.jpg'),
        ])->assertRedirect();
        $photoVersion = $cdr->photos()->firstOrFail()->currentVersion;
        $this->actingAs($this->actor)->post(route('ib39.cdr.start', $cdr))->assertRedirect();
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), [
            'save_intent' => 'manual',
            'content' => ['alias' => 'Persisted fallback alias'],
        ])->assertRedirect();
        $this->createHistoricalGeneratedFinal($cdr, ['alias' => 'Persisted fallback alias'], $photoVersion->id);
        $japic = User::factory()->role('japic')->create();

        $this->actingAs($japic)->get(route('cdr.workspace', $cdr))
            ->assertOk()
            ->assertSee('Final CDR')
            ->assertSee(route('cdr.documents.preview', $cdr))
            ->assertSee('<iframe class="cdr-uploaded-preview"', false)
            ->assertDontSee('name="content[alias]"', false)
            ->assertDontSee('Download CDR')
            ->assertDontSee('Print Document');
        $this->actingAs($japic)->get(route('cdr.documents.preview', $cdr))
            ->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('<!doctype html>', false)->assertSee('<style>', false)
            ->assertSee('Persisted fallback alias')
            ->assertSee(route('cdr.photos.preview', $photoVersion))
            ->assertDontSee('Print Document');
        $this->actingAs($japic)->get(route('cdr.photos.preview', $photoVersion))->assertOk();
    }

    public function test_uploaded_png_and_jpeg_finals_use_the_secure_inline_image_preview(): void
    {
        $japic = User::factory()->role('japic')->create();

        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg'] as $extension => $mimeType) {
            $cdr = $extension === 'png' ? $this->record->cdrProcessing : $this->newRecord('Jpeg')->cdrProcessing;
            $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
                'document' => UploadedFile::fake()->image('final-cdr.'.$extension),
                'confirmed' => '1',
            ])->assertRedirect();

            $this->actingAs($japic)->get(route('cdr.workspace', $cdr))
                ->assertOk()
                ->assertSee('cdr-uploaded-preview-image')
                ->assertSee('src="'.route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]).'"', false)
                ->assertDontSee('CDR Document Preview')
                ->assertDontSee('Download CDR');
            $this->actingAs($japic)->get(route('cdr.documents.preview', $cdr))
                ->assertOk()->assertHeader('Content-Type', $mimeType)
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }
    }

    public function test_external_viewer_cannot_use_the_shared_photo_url_outside_the_official_completed_document(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->post(route('ib39.cdr.photos.store', $cdr), [
            'photo_type' => 'fr_photo',
            'photo' => UploadedFile::fake()->image('subject.jpg'),
        ])->assertRedirect();
        $photoVersion = $cdr->photos()->firstOrFail()->currentVersion;
        $this->uploadFinal($cdr);
        $japic = User::factory()->role('japic')->create();

        $this->actingAs($japic)->get(route('cdr.photos.preview', $photoVersion))->assertNotFound();

        $cdr->fresh()->update(['status' => Ib39CdrStatus::Pending, 'completed_at' => null, 'completed_by' => null]);
        $processing = $this->record->japicCertificationProcessing()->firstOrFail();
        $this->actingAs($japic)->get(route('cdr.photos.preview', $photoVersion))
            ->assertRedirect(route('japic.certifications.show', $processing))
            ->assertSessionHas('error', 'Document is not available yet.');
    }

    public function test_unrelated_user_cannot_open_or_preview_the_cdr_workspace(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->uploadFinal($cdr);
        $unrelated = User::factory()->role('lgu')->create();

        $this->actingAs($unrelated)->get(route('cdr.workspace', $cdr))->assertForbidden();
        $this->actingAs($unrelated)->get(route('cdr.documents.preview', $cdr))->assertForbidden();
        $this->actingAs($unrelated)->get(route('japic.cdr.documents.preview', $cdr))->assertForbidden();
        $this->actingAs($unrelated)->get(route('japic.cdr.documents.download', $cdr))->assertForbidden();
        $this->actingAs($unrelated)->post(route('cdr.comments.store', $cdr), ['text' => 'Denied'])->assertForbidden();
    }

    public function test_workspace_eager_loads_comment_authors_without_per_comment_queries(): void
    {
        $cdr = $this->record->cdrProcessing;
        foreach (range(1, 4) as $number) {
            $cdr->comments()->create([
                'user_id' => $this->actor->id,
                'author_role' => $this->actor->role,
                'text' => 'Comment '.$number,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertOk();

        $userQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains(strtolower($query), 'from "users"'));
        $this->assertLessThanOrEqual(2, $userQueries->count());
    }

    private function uploadFinal($cdr): void
    {
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
            'document' => $this->pdf(),
            'confirmed' => '1',
        ])->assertRedirect();
    }

    private function createHistoricalGeneratedFinal($cdr, array $content, ?int $photoVersionId = null): void
    {
        $cdr->form()->firstOrFail()->update(['content' => $content]);
        $snapshot = ['content' => $content, 'fr_photo_version_id' => $photoVersionId];
        $serialized = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $completedAt = now();
        $cdr->finalDocument()->create([
            'source_type' => Ib39CdrDocumentSource::Generated,
            'storage_path' => "generated/cdr/{$cdr->id}/final",
            'original_filename' => 'generated-cdr.html',
            'mime_type' => 'text/html',
            'size_bytes' => strlen($serialized),
            'sha256' => hash('sha256', $serialized),
            'content_schema_version' => Ib39CdrFormSchema::VERSION,
            'content_snapshot' => $snapshot,
            'created_by' => $this->actor->id,
            'finalized_at' => $completedAt,
        ]);
        $fromStatus = $cdr->status;
        $cdr->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => $completedAt,
            'completed_by' => $this->actor->id,
        ]);
        $cdr->statusHistories()->create([
            'user_id' => $this->actor->id,
            'from_status' => $fromStatus,
            'to_status' => Ib39CdrStatus::Completed,
            'event' => 'system_authored_finalized',
        ]);
        app(JapicCertificationIntakeService::class)->createForCompletedCdr($cdr->fresh());
    }

    private function pdf(string $name = 'completed-cdr.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function newRecord(string $suffix): Ib39SurfacedFormerRebel
    {
        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Second',
            'last_name' => $suffix,
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $this->record->municipality_id,
            'barangay_id' => $this->record->barangay_id,
            'surfaced_at' => '2026-09-29',
            'possessed_firearms' => false,
        ], $this->actor);
    }
}
