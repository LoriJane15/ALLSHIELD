<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
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

class Ib39CdrFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        $municipality = Municipality::query()->create(['name' => 'Finalization Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Finalization Barangay']);
        $this->actor = User::factory()->role('39th_ib')->create();
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Finalization', 'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id, 'surfaced_at' => '2026-08-31', 'possessed_firearms' => false,
        ], $this->actor);
    }

    public function test_only_active_39th_ib_users_can_review_and_confirm_finalization(): void
    {
        $cdr = $this->ongoing(['assessment' => 'Draft']);
        $review = route('ib39.cdr.finalization.review', $cdr);
        $finalize = route('ib39.cdr.finalize', $cdr);

        auth()->logout();
        $this->get($review)->assertRedirect();
        $this->post($finalize)->assertRedirect();
        $other = User::factory()->role('lgu')->create();
        $this->actingAs($other)->get($review)->assertForbidden();
        $this->actingAs($other)->post($finalize)->assertForbidden();
        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        $this->actingAs($inactive)->get($review)->assertRedirect(route('login'));
        $this->actingAs($inactive)->post($finalize)->assertRedirect(route('login'));
    }

    public function test_review_groups_missing_fields_ignores_na_and_reports_applicable_empty_tables_and_optional_photo(): void
    {
        $cdr = $this->ongoing([
            'subject_name' => '   ',
            'party_member_status' => 'na',
            'party_member_courses' => [],
            'npa_member_status' => 'yes',
            'npa_member_courses' => [],
            'violent_activities_status' => 'na',
            'violent_activities' => [],
            'debriefer_name' => '',
            'approving_officer_name' => 'Approver',
        ]);

        $this->actingAs($this->actor)->get(route('ib39.cdr.finalization.review', $cdr))->assertOk()
            ->assertSeeInOrder(['Cover/control information', 'Name'])
            ->assertSeeInOrder(['NPA Member', 'NPA Member table'])
            ->assertSeeInOrder(['Signatories', 'Debriefer — Full Name'])
            ->assertSeeInOrder(['FR Photo', 'FR Photo (optional)'])
            ->assertDontSee('Party Member table')
            ->assertDontSee('Significant violent involvement table')
            ->assertSee('Final submission will complete and lock this CDR.');
    }

    public function test_confirmation_is_required_and_user_can_finalize_despite_warnings(): void
    {
        $cdr = $this->ongoing([]);
        $fingerprint = $this->fingerprint($cdr);
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), ['draft_fingerprint' => $fingerprint])
            ->assertSessionHasErrors('confirmed');

        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), ['confirmed' => '1', 'draft_fingerprint' => $fingerprint])
            ->assertRedirect(route('ib39.cdr.show', $cdr));
        $this->assertSame(Ib39CdrStatus::Completed, $cdr->fresh()->status);
    }

    public function test_stale_confirmation_is_rejected_and_double_submission_creates_one_version(): void
    {
        $cdr = $this->ongoing(['assessment' => 'First']);
        $stale = $this->fingerprint($cdr);
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => ['assessment' => 'Changed']])->assertRedirect();
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), ['confirmed' => '1', 'draft_fingerprint' => $stale])
            ->assertSessionHasErrors('draft_fingerprint');
        $this->assertDatabaseCount('ib39_cdr_document_versions', 0);

        $current = $this->fingerprint($cdr->fresh());
        $payload = ['confirmed' => '1', 'draft_fingerprint' => $current];
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), $payload)->assertRedirect();
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), $payload)->assertForbidden();
        $this->assertDatabaseCount('ib39_cdr_document_versions', 1);
        $this->assertDatabaseCount('ib39_cdr_status_histories', 3);
    }

    public function test_finalization_owns_completion_metadata_encrypts_immutable_snapshot_and_avoids_external_workflows(): void
    {
        $cdr = $this->ongoing(['assessment' => 'Sensitive snapshot value']);
        $tables = ['fr_government_assistances'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);

        $this->travelTo(now()->startOfSecond());
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), [
            'confirmed' => '1', 'draft_fingerprint' => $this->fingerprint($cdr),
            'status' => 'Pending', 'version_number' => 99, 'completed_by' => 999,
        ])->assertSessionHasErrors(['status', 'version_number', 'completed_by']);
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), ['confirmed' => '1', 'draft_fingerprint' => $this->fingerprint($cdr)])->assertRedirect();

        $completed = $cdr->fresh();
        $version = Ib39CdrDocumentVersion::query()->sole();
        $this->assertSame(Ib39CdrStatus::Completed, $completed->status);
        $this->assertSame($this->actor->id, $completed->completed_by);
        $this->assertTrue(now()->equalTo($completed->completed_at));
        $this->assertSame($version->id, $completed->current_final_version_id);
        $this->assertSame(1, $version->version_number);
        $this->assertSame(Ib39CdrDocumentSource::Generated, $version->source_type);
        $this->assertSame(2, $version->content_schema_version);
        $this->assertSame('Sensitive snapshot value', $version->content_snapshot['content']['assessment']);
        $this->assertStringNotContainsString('Sensitive snapshot value', DB::table('ib39_cdr_document_versions')->value('content_snapshot'));
        $this->assertDatabaseHas('ib39_cdr_status_histories', ['event' => 'system_authored_finalized', 'document_version_id' => $version->id]);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }

        $this->expectException(\LogicException::class);
        $version->update(['mime_type' => 'application/pdf']);
    }

    public function test_final_copy_uses_snapshot_has_final_mark_and_completed_record_is_locked(): void
    {
        Storage::fake('local');
        $cdr = $this->ongoing(['assessment' => 'Immutable final assessment']);
        $this->actingAs($this->actor)->post(route('ib39.cdr.photos.store', $cdr), ['photo_type' => 'fr_photo', 'photo' => $this->fakePng()])->assertRedirect();
        $fingerprint = $this->fingerprint($cdr->fresh());
        $this->actingAs($this->actor)->post(route('ib39.cdr.finalize', $cdr), ['confirmed' => '1', 'draft_fingerprint' => $fingerprint])->assertRedirect();
        $photoUrl = route('ib39.cdr.photos.show', $cdr->photos()->firstOrFail()->currentVersion);

        $cdr->form()->firstOrFail()->update(['content' => ['assessment' => 'Later mutable value']]);
        $final = $this->actingAs($this->actor)->get(route('ib39.cdr.preview', $cdr))->assertOk()
            ->assertSee('FINAL COPY')->assertDontSee('DRAFT — NOT FINAL')->assertSee('Immutable final assessment')->assertDontSee('Later mutable value');
        $this->assertSame(1, substr_count($final->getContent(), $photoUrl));
        $this->actingAs($this->actor)->get(route('ib39.cdr.print', $cdr))->assertOk()->assertSee('FINAL COPY')->assertDontSee('DRAFT — NOT FINAL');

        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertForbidden();
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => []])->assertForbidden();
        $this->actingAs($this->actor)->post(route('ib39.cdr.start', $cdr))->assertForbidden();
        $this->actingAs($this->actor)->post(route('ib39.cdr.photos.store', $cdr), ['photo_type' => 'fr_photo', 'photo' => $this->fakePng()])->assertForbidden();
        $this->assertDatabaseCount('ib39_cdr_document_versions', 1);
    }

    private function ongoing(array $content)
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => $content])->assertRedirect();

        return $cdr->fresh();
    }

    private function fingerprint($cdr): string
    {
        $html = $this->actingAs($this->actor)->get(route('ib39.cdr.finalization.review', $cdr))->assertOk()->getContent();
        preg_match('/name="draft_fingerprint" value="([a-f0-9]{64})"/', $html, $matches);

        return $matches[1];
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    }
}
