<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39CdrFinalDocument;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
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
            'first_name' => 'Synthetic',
            'last_name' => 'Finalization',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-31',
            'possessed_firearms' => false,
        ], $this->actor);
    }

    public function test_obsolete_generated_finalization_routes_are_removed_and_workspace_has_no_submit_action(): void
    {
        $cdr = $this->ongoing(['assessment' => 'Saved draft']);

        $this->assertFalse(Route::has('ib39.cdr.finalization.review'));
        $this->assertFalse(Route::has('ib39.cdr.finalize'));
        $this->assertSame(Ib39CdrStatus::Ongoing, $cdr->status);
        $this->assertDatabaseCount('ib39_cdr_final_documents', 0);

        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertOk()
            ->assertSee('Preview Draft')
            ->assertSee('Save Draft')
            ->assertSee('Upload CDR')
            ->assertDontSee('Submit as Final');
    }

    public function test_confirmed_final_upload_is_the_completion_action_and_owns_completion_metadata(): void
    {
        Storage::fake('local');
        $cdr = $this->ongoing(['assessment' => 'Saved before upload']);

        $this->travelTo(now()->startOfSecond());
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
            'document' => $this->pdf(),
            'confirmed' => '1',
            'status' => 'Pending',
            'completed_by' => 999,
        ])->assertSessionHasErrors(['status', 'completed_by']);
        $this->assertSame(Ib39CdrStatus::Ongoing, $cdr->fresh()->status);

        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
            'document' => $this->pdf(),
            'confirmed' => '1',
        ])->assertRedirect(route('ib39.cdr.show', $cdr));

        $completed = $cdr->fresh();
        $document = Ib39CdrFinalDocument::query()->sole();
        $this->assertSame(Ib39CdrStatus::Completed, $completed->status);
        $this->assertSame($this->actor->id, $completed->completed_by);
        $this->assertTrue(now()->equalTo($completed->completed_at));
        $this->assertTrue($completed->finalDocument->is($document));
        $this->assertSame(Ib39CdrDocumentSource::Uploaded, $document->source_type);
        $this->assertSame($this->actor->id, $document->created_by);
        $this->assertTrue(now()->equalTo($document->finalized_at));
        $this->assertSame('completed-cdr.pdf', $document->original_filename);
        $this->assertDatabaseHas('ib39_cdr_status_histories', [
            'cdr_processing_id' => $completed->id,
            'event' => 'final_document_uploaded',
            'user_id' => $this->actor->id,
        ]);
    }

    public function test_completed_upload_locks_draft_photo_and_second_final_upload(): void
    {
        Storage::fake('local');
        $cdr = $this->ongoing(['assessment' => 'Locked after upload']);
        $this->actingAs($this->actor)->post(route('ib39.cdr.photos.store', $cdr), [
            'photo_type' => 'fr_photo',
            'photo' => $this->fakePng(),
        ])->assertRedirect();
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
            'document' => $this->pdf(),
            'confirmed' => '1',
        ])->assertRedirect();

        $this->actingAs($this->actor)->get(route('ib39.cdr.edit', $cdr))->assertForbidden();
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => []])->assertForbidden();
        $this->actingAs($this->actor)->post(route('ib39.cdr.start', $cdr))->assertForbidden();
        $this->actingAs($this->actor)->post(route('ib39.cdr.photos.store', $cdr), [
            'photo_type' => 'fr_photo',
            'photo' => $this->fakePng(),
        ])->assertForbidden();
        $this->actingAs($this->actor)->post(route('ib39.cdr.documents.upload', $cdr), [
            'document' => $this->pdf(),
            'confirmed' => '1',
        ])->assertForbidden();
        $this->assertDatabaseCount('ib39_cdr_final_documents', 1);
    }

    private function ongoing(array $content)
    {
        $cdr = $this->record->cdrProcessing;
        $this->actingAs($this->actor)->put(route('ib39.cdr.update', $cdr), ['content' => $content])->assertRedirect();

        return $cdr->fresh();
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('completed-cdr.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    }
}
