<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrPhotoType;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39CdrPhotoVersion;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Ib39CdrEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();

        $municipality = Municipality::query()->create(['name' => 'Synthetic CDR Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Synthetic CDR Barangay']);
        $this->actor = User::factory()->role('39th_ib')->create();
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'OriginalFirst',
            'last_name' => 'OriginalLast',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-31',
            'possessed_firearms' => false,
        ], $this->actor);
    }

    public function test_cdr_routes_reject_guests_inactive_accounts_other_roles_and_soft_deleted_parents(): void
    {
        Storage::fake('local');
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->post(route('ib39.cdr.photos.store', $cdr), [
            'photo_type' => Ib39CdrPhotoType::FrPhoto->value,
            'photo' => $this->fakePng('authorization.png'),
        ])->assertRedirect();
        $version = Ib39CdrPhotoVersion::query()->sole();

        $routes = [
            ['GET', route('ib39.cdr.show', $cdr)],
            ['POST', route('ib39.cdr.start', $cdr)],
            ['GET', route('ib39.cdr.edit', $cdr)],
            ['PUT', route('ib39.cdr.update', $cdr)],
            ['GET', route('ib39.cdr.preview', $cdr)],
            ['GET', route('ib39.cdr.print', $cdr)],
            ['GET', route('ib39.cdr.finalization.review', $cdr)],
            ['POST', route('ib39.cdr.finalize', $cdr)],
            ['POST', route('ib39.cdr.photos.store', $cdr)],
            ['GET', route('ib39.cdr.photos.show', $version)],
        ];

        foreach ($routes as [$method, $url]) {
            auth()->logout();
            $this->call($method, $url)->assertRedirect();
        }

        $otherRole = User::factory()->role('lgu')->create();
        foreach ($routes as [$method, $url]) {
            $this->actingAs($otherRole)->call($method, $url)->assertForbidden();
        }

        $inactive = User::factory()->role('39th_ib')->create(['is_active' => false]);
        foreach ($routes as [$method, $url]) {
            $this->actingAs($inactive)->call($method, $url)->assertRedirect(route('login'));
        }

        $this->record->delete();
        foreach ($routes as [$method, $url]) {
            $this->actingAs($this->actor)->call($method, $url)->assertForbidden();
        }
    }

    public function test_start_and_incomplete_draft_saves_are_idempotent_and_never_complete_the_cdr(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->post(route('ib39.cdr.start', $cdr))->assertRedirect(route('ib39.cdr.edit', $cdr));
        $startedAt = $cdr->fresh()->started_at;

        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => []])->assertRedirect();
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => ['assessment' => 'Incomplete assessment']])->assertRedirect();

        $cdr->refresh();
        $this->assertSame(Ib39CdrStatus::Ongoing, $cdr->status);
        $this->assertTrue($startedAt->equalTo($cdr->started_at));
        $this->assertNull($cdr->completed_at);
        $this->assertSame(2, $cdr->statusHistories()->count());
        $this->assertDatabaseCount('ib39_cdr_document_versions', 0);
    }

    public function test_structured_repeatable_content_signatories_are_saved_encrypted_without_changing_fr_profile(): void
    {
        $cdr = $this->record->cdrProcessing;
        $payload = ['content' => [
            'subject_name' => 'CDR-specific identity',
            'assessment' => 'Structured narrative only',
            'debriefer_name' => 'Editable Debriefer',
            'approving_officer_name' => 'Editable Approver',
            'siblings' => [['name' => 'Synthetic Relative', 'address' => 'Synthetic Address']],
            'party_member_status' => 'yes',
            'party_member_courses' => [['course' => 'Synthetic Course', 'attendees' => '', 'instructor' => '']],
        ]];

        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), $payload)->assertRedirect();
        $form = $cdr->form()->firstOrFail();

        $this->assertSame('Editable Debriefer', $form->content['debriefer_name']);
        $this->assertSame('Synthetic Relative', $form->content['siblings'][0]['name']);
        $this->assertSame($this->actor->id, $form->last_edited_by);
        $this->assertStringNotContainsString('Structured narrative only', DB::table('ib39_cdr_forms')->where('id', $form->id)->value('content'));
        $this->assertStringNotContainsString('Structured narrative only', DB::table('audit_logs')->where('entity_id', $cdr->id)->pluck('new_values')->implode(' '));
        $this->assertSame('OriginalFirst', $this->record->fresh()->first_name);
        $this->assertSame('OriginalLast', $this->record->fresh()->last_name);
    }

    public function test_draft_validation_rejects_unknown_keys_protected_fields_invalid_rows_and_excessive_rows(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['status' => 'Completed', 'content' => ['unknown' => 'value']])
            ->assertSessionHasErrors(['status', 'content']);
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => ['siblings' => [['unknown' => 'value']]]])
            ->assertSessionHasErrors('content.siblings.0');
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => ['siblings' => array_fill(0, 51, ['name' => 'Row'])]])
            ->assertSessionHasErrors('content.siblings');

        $this->assertSame(Ib39CdrStatus::Pending, $cdr->fresh()->status);
    }

    public function test_conditional_sections_clear_na_rows_and_limited_narratives_render_safely(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => [
            'party_member_status' => 'na',
            'party_member_courses' => [['course' => 'Must be cleared', 'attendees' => 'One', 'instructor' => 'Two']],
            'violent_activities_status' => 'na',
            'violent_activities' => [['date_period' => '2026', 'activity' => 'Must be cleared']],
            'chronology_status' => 'na',
            'chronology' => [['date_period' => '2026', 'activity' => 'Must be cleared', 'remarks' => 'Must be cleared']],
            'manpower' => "- **First**\n- <script>alert(1)</script>\n[unsafe](javascript:alert(2))",
        ]])->assertRedirect();

        $content = $cdr->form()->firstOrFail()->content;
        $this->assertSame([], $content['party_member_courses']);
        $this->assertSame([], $content['violent_activities']);
        $this->assertSame([], $content['chronology']);

        $preview = $this->actingAs($this->actor)->get(route('ib39.cdr.preview', $cdr))->assertOk();
        $preview->assertSeeInOrder(['Party Member', 'N/A', 'Significant violent involvement', 'N/A'])
            ->assertSee('<ul><li><strong>First</strong></li>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false)
            ->assertDontSee('Must be cleared');
    }

    public function test_editor_has_exact_navigation_order_fixed_furniture_and_one_fr_photo_input(): void
    {
        $response = $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $this->record->cdrProcessing))->assertOk();
        $response->assertSeeInOrder(['Cover/control information', 'FR Photo', 'Personal Data', 'Physical Description', 'Parents/family', 'Brothers/Sisters', 'Children', 'II. Circumstances of Neutralization', 'III. Background of Entry in the CTM', 'Party Member', 'NPA Member', 'Subversive Mass Activist', 'Significant violent involvement', 'Significant non-violent involvement', 'IV. Order of Battle', 'Composition', 'Disposition', 'Strength and Firearms', 'Manpower', 'Firepower', 'Training', 'Logistics', 'Strategy and Tactics', 'Combat Effectiveness', 'Plans', 'NPA Personalities', 'Posting Areas', 'Mass Contacts', 'Supply Routes', 'NPA Active', 'Other Significant Information', 'Chronology', 'Assessment', 'Recommendation', 'Signatories'])
            ->assertDontSee('name="content[headquarters]"', false)
            ->assertDontSee('name="content[debriefer_rank]"', false)
            ->assertDontSee('Whole-body with firearm')
            ->assertDontSee('Half-body without firearm');
        $this->assertSame(1, substr_count($response->getContent(), 'type="file" name="photo"'));
    }

    public function test_empty_fr_photo_placeholder_occurs_once_inside_cover_and_never_after_signatories(): void
    {
        $html = $this->actingAs($this->actor)->get(route('ib39.cdr.preview', $this->record->cdrProcessing))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="fr-photo-placeholder">FR Photo</span>'));
        $this->assertSame(1, substr_count($html, 'data-cover-fr-photo'));
        $this->assertMatchesRegularExpression('/<section class="cover">.*data-cover-fr-photo.*<\/section>/s', $html);
        $this->assertStringNotContainsString('FR Photo', substr($html, strpos($html, '<section class="signatures">')));
    }

    public function test_preview_and_print_escape_content_mark_the_draft_and_render_all_sections_in_official_order(): void
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => ['assessment' => '<script>alert(1)</script>']]);

        $expectedOrder = [
            'DRAFT — NOT FINAL', 'Personal Data', 'Physical Description', 'Parents/family', 'Brothers/Sisters',
            'Children', 'Relatives Working in Government', 'Relatives Working in UGM', 'II. Circumstances of Neutralization',
            'III. Background of Entry in the CTM', 'Party Member', 'NPA Member', 'Subversive Mass Activist',
            'Significant violent involvement', 'Significant non-violent involvement', 'IV. Order of Battle', 'Composition',
            'Disposition', 'Strength and Firearms', 'Training', 'Logistics', 'Strategy and Tactics', 'Combat Effectiveness',
            'Plans', 'NPA Personalities', 'Posting Areas', 'Mass Contacts', 'Supply Routes', 'NPA Active',
            'Other Significant Information', 'Chronology', 'Assessment', 'Recommendation',
        ];

        $preview = $this->actingAs($this->actor)->get(route('ib39.cdr.preview', $cdr))->assertOk()
            ->assertSeeInOrder($expectedOrder)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('C O N F I D E N T I A L')->assertSee('39TH INFANTRY (SMASH’EM) BATTALION, 10ID, PA')->assertSee('Honor. Patriotism. Duty')
            ->assertSee('@page{size:A4', false);
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->actingAs($this->actor)->get(route('ib39.cdr.print', $cdr))->assertOk()
            ->assertSee('DRAFT — NOT FINAL')->assertSee('window.print()', false);
    }

    public function test_photo_validation_rejects_disallowed_type_non_image_and_oversized_file(): void
    {
        Storage::fake('local');
        $cdr = $this->record->cdrProcessing;
        $route = route('ib39.cdr.photos.store', $cdr);

        $this->actingAs($this->actor)->post($route, ['photo_type' => 'other', 'photo' => $this->fakePng('valid.png')])->assertSessionHasErrors('photo_type');
        $this->actingAs($this->actor)->post($route, ['photo_type' => Ib39CdrPhotoType::FrPhoto->value, 'photo' => UploadedFile::fake()->create('fake.jpg', 10, 'image/jpeg')])->assertSessionHasErrors('photo');
        $this->actingAs($this->actor)->post($route, ['photo_type' => Ib39CdrPhotoType::FrPhoto->value, 'photo' => $this->fakePng('large.png', 5121)])->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('ib39_cdr_photo_versions', 0);
    }

    public function test_only_fr_photo_accepts_valid_jpeg_and_png(): void
    {
        Storage::fake('local');
        $cdr = $this->record->cdrProcessing;
        $route = route('ib39.cdr.photos.store', $cdr);

        foreach (['whole_body_with_firearm', 'half_body_without_firearm'] as $oldType) {
            $this->actingAs($this->actor)->post($route, ['photo_type' => $oldType, 'photo' => $this->fakePng('old.png')])->assertSessionHasErrors('photo_type');
        }
        $this->actingAs($this->actor)->post($route, ['photo_type' => 'fr_photo', 'photo' => $this->fakeJpeg('valid.jpg')])->assertRedirect();
        $this->actingAs($this->actor)->post($route, ['photo_type' => 'fr_photo', 'photo' => $this->fakePng('valid.png')])->assertRedirect();

        $this->assertDatabaseCount('ib39_cdr_photos', 1);
        $this->assertDatabaseCount('ib39_cdr_photo_versions', 2);
    }

    public function test_private_photo_replacement_preserves_randomized_versions_and_authorized_no_store_preview(): void
    {
        Storage::fake('local');
        $cdr = $this->record->cdrProcessing;
        $route = route('ib39.cdr.photos.store', $cdr);
        $type = Ib39CdrPhotoType::FrPhoto->value;

        $this->actingAs($this->actor)->post($route, ['photo_type' => $type, 'photo' => $this->fakePng('first-personal-name.png')])->assertRedirect();
        $this->actingAs($this->actor)->post($route, ['photo_type' => $type, 'photo' => $this->fakePng('second-personal-name.png')])->assertRedirect();

        $versions = Ib39CdrPhotoVersion::query()->orderBy('version_number')->get();
        $this->assertCount(2, $versions);
        $this->assertSame([1, 2], $versions->pluck('version_number')->all());
        foreach ($versions as $version) {
            Storage::disk('local')->assertExists($version->storage_path);
            $this->assertStringStartsWith("ib39/cdr/{$cdr->id}/photos/{$type}/", $version->storage_path);
            $this->assertStringNotContainsString('personal-name', $version->storage_path);
        }
        $this->assertSame($versions->last()->id, $cdr->photos()->firstOrFail()->current_photo_version_id);

        $response = $this->actingAs($this->actor)->get(route('ib39.cdr.photos.show', $versions->last()));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->actingAs(User::factory()->role('lgu')->create())->get(route('ib39.cdr.photos.show', $versions->last()))->assertForbidden();

        $editor = $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertOk();
        $editor->assertDontSee($versions->last()->storage_path, false);
        $preview = $this->actingAs($this->actor)->get(route('ib39.cdr.preview', $cdr))->assertOk()
            ->assertDontSee('Whole-body with firearm')
            ->assertDontSee('Half-body without firearm')
            ->assertDontSee('Firearm photograph');
        $this->assertSame(1, substr_count($preview->getContent(), route('ib39.cdr.photos.show', $versions->last())));
        $this->assertMatchesRegularExpression('/<section class="cover">.*'.preg_quote(route('ib39.cdr.photos.show', $versions->last()), '/').'.*<\/section>/s', $preview->getContent());
        $this->assertStringNotContainsString('FR Photo', substr($preview->getContent(), strpos($preview->getContent(), '<section class="signatures">')));
        $this->assertSame(Ib39CdrStatus::Ongoing, $cdr->fresh()->status);
        $this->assertDatabaseCount('ib39_cdr_document_versions', 0);
    }

    public function test_workspace_link_is_on_profile_and_stage_four_does_not_create_external_workflows(): void
    {
        $cdr = $this->record->cdrProcessing;
        $tables = ['fr_government_assistances'];
        $counts = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->actingAs($this->actor)->get(route('ib39.fr-profiles.show', $this->record))->assertOk()->assertSee(route('ib39.cdr.show', $cdr));
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => ['recommendation' => 'Synthetic recommendation']]);

        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->assertSame(0, Ib39CdrDocumentVersion::query()->count());
    }

    private function fakePng(string $name, ?int $kilobytes = null): UploadedFile
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        if ($kilobytes !== null) {
            $content .= str_repeat("\0", ($kilobytes * 1024) - strlen($content));
        }

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function fakeJpeg(string $name): UploadedFile
    {
        $content = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==', true);

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
