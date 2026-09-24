<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39FeaDraftSchema;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ib39FeaDraftPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => 'Synthetic Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Synthetic Barangay']);
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Preview Subject',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30', 'possessed_firearms' => true,
        ], $this->actor);
    }

    public function test_all_four_saved_forms_share_preview_and_print_renderers_with_private_headers(): void
    {
        foreach ($this->editableTypes() as $type) {
            $document = $this->saveSyntheticDraft($type);
            foreach (['preview', 'print'] as $mode) {
                $response = $this->actingAs($this->actor)->get($this->url($document, $mode))->assertOk();
                $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
                    ->assertHeader('Pragma', 'no-cache')
                    ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                    ->assertSee('DRAFT — NOT FINAL')
                    ->assertSee($document->document_type->label());
            }
        }
    }

    public function test_missing_draft_shows_honest_state_and_performs_no_writes(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        $before = $document->updated_at;
        $historyCounts = [$document->histories()->count(), $document->draftHistories()->count(), $document->processing->histories()->count()];

        $this->actingAs($this->actor)->get($this->url($document, 'preview'))->assertOk()
            ->assertSee('DRAFT — NOT FINAL')
            ->assertSee('Save a draft first')
            ->assertDontSee('Synthetic Preview Subject')
            ->assertDontSee('data-form="tir"', false);

        $document->refresh();
        $this->assertNull($document->draft_data);
        $this->assertSame(0, $document->draft_revision);
        $this->assertTrue($before->equalTo($document->updated_at));
        $this->assertSame($historyCounts, [$document->histories()->count(), $document->draftHistories()->count(), $document->processing->histories()->count()]);
    }

    public function test_saved_values_are_escaped_line_breaks_preserved_and_condition_is_checked_without_html_execution(): void
    {
        $document = $this->saveSyntheticDraft(Ib39FeaDocumentType::Tir, [
            'condition' => 'FAIR',
            'remarks' => "First line\n<script>alert('preview')</script>",
        ]);
        $response = $this->actingAs($this->actor)->get($this->url($document, 'preview'))->assertOk();
        $response->assertSee("First line\n&lt;script&gt;alert(&#039;preview&#039;)&lt;/script&gt;", false)
            ->assertDontSee("<script>alert('preview')</script>", false)
            ->assertSee('✓</span> FAIR', false)
            ->assertSee('<span class="checkbox"></span> GOOD', false);
    }

    public function test_fixed_wording_tables_photo_placeholders_legend_and_print_css_are_present(): void
    {
        $cvif = $this->saveSyntheticDraft(Ib39FeaDocumentType::Cvif);
        $this->actingAs($this->actor)->get($this->url($cvif, 'preview'))->assertSee('THAT UPON DUE DELIBERATION')->assertSee('CERTIFIED BY:');

        $ptis = $this->saveSyntheticDraft(Ib39FeaDocumentType::Ptis);
        $this->actingAs($this->actor)->get($this->url($ptis, 'preview'))->assertSee('LEGEND FOR REMARKS')->assertSee('I HEREBY CERTIFY')->assertDontSee('Page 70 of 123');

        $justification = $this->saveSyntheticDraft(Ib39FeaDocumentType::Justification);
        $response = $this->actingAs($this->actor)->get($this->url($justification, 'preview'))
            ->assertSee('<span class="question-number">3</span>&nbsp;&nbsp;Photo documentation', false)
            ->assertSee('<span class="question-number">4</span>&nbsp;&nbsp;Photo of an example', false)
            ->assertDontSee('sa</')
            ->assertDontSee('type="file"', false)
            ->assertSee('@page { size: A4 portrait;', false)
            ->assertSee('.no-print { display: none !important;', false);
        $this->assertSame(2, substr_count($response->getContent(), 'EMPTY PHOTO AREA'));
    }

    public function test_removed_legacy_signatories_stay_blank_and_justification_positions_are_dynamic(): void
    {
        $tir = $this->saveSyntheticDraft(Ib39FeaDocumentType::Tir, ['inspected_by' => 'LEGACY TIR NAME', 'noted_by' => 'LEGACY NOTER']);
        $this->actingAs($this->actor)->get($this->url($tir, 'preview'))->assertOk()
            ->assertSee('INSPECTED BY:')->assertSee('NOTED BY:')
            ->assertDontSee('LEGACY TIR NAME')->assertDontSee('LEGACY NOTER');

        $cvif = $this->saveSyntheticDraft(Ib39FeaDocumentType::Cvif, [
            'technical_inventory_at' => 'LEGACY DUPLICATE PLACE',
            'pnp_representative' => 'LEGACY PNP NAME',
            'date_of_inspection' => '2026-09-02',
            'place_of_inspection' => 'Synthetic Inspection Place',
        ]);
        $this->actingAs($this->actor)->get($this->url($cvif, 'preview'))->assertOk()
            ->assertSee('on <span class="inline-line value">2026-09-02</span> at <span class="inline-line value">Synthetic Inspection Place</span>', false)
            ->assertDontSee('LEGACY DUPLICATE PLACE')->assertDontSee('LEGACY PNP NAME');

        $ptis = $this->saveSyntheticDraft(Ib39FeaDocumentType::Ptis, [
            'to' => 'LEGACY TO', 'from' => 'LEGACY FROM', 'basis' => 'LEGACY BASIS',
            'received_by' => 'LEGACY RECEIVER', 'commanding_officer' => 'LEGACY COMMANDER',
            'supply_classification_officer' => 'TO CONTENT', 'organization_unit' => 'FROM CONTENT',
        ]);
        $this->actingAs($this->actor)->get($this->url($ptis, 'preview'))->assertOk()
            ->assertSee('TO CONTENT')->assertSee('FROM CONTENT')->assertSee('BASIS:')
            ->assertDontSee('LEGACY TO')->assertDontSee('LEGACY FROM')->assertDontSee('LEGACY BASIS')
            ->assertDontSee('LEGACY RECEIVER')->assertDontSee('LEGACY COMMANDER');

        $justification = $this->saveSyntheticDraft(Ib39FeaDocumentType::Justification, [
            'prepared_by' => 'Prepared Person', 'prepared_by_position' => 'Prepared Rank',
            'reviewed_by' => 'Reviewed Person', 'reviewed_by_position' => 'Reviewed Rank',
        ]);
        $this->actingAs($this->actor)->get($this->url($justification, 'preview'))->assertOk()
            ->assertSee('Prepared Person')->assertSee('Prepared Rank')->assertSee('Reviewed Person')->assertSee('Reviewed Rank')
            ->assertDontSee('Firearms Technician,RSAO PRO 11')->assertDontSee('OIC,Regional Supply Accountable Officer');
    }

    public function test_preview_and_print_do_not_change_workflow_draft_actor_revision_or_histories(): void
    {
        $document = $this->saveSyntheticDraft(Ib39FeaDocumentType::Cvif);
        $snapshot = $document->only(['status', 'draft_revision', 'draft_saved_by', 'draft_saved_at', 'updated_at']);
        $counts = [$document->histories()->count(), $document->draftHistories()->count(), $document->processing->histories()->count()];

        $this->actingAs($this->actor)->get($this->url($document, 'preview'))->assertOk();
        $this->actingAs($this->actor)->get($this->url($document, 'print'))->assertOk();

        $document->refresh();
        $this->assertEquals($snapshot, $document->only(array_keys($snapshot)));
        $this->assertSame($counts, [$document->histories()->count(), $document->draftHistories()->count(), $document->processing->histories()->count()]);
    }

    public function test_lock_denies_editor_but_preserves_saved_draft_preview_and_print(): void
    {
        $document = $this->saveSyntheticDraft(Ib39FeaDocumentType::Tir);

        $this->actingAs($this->actor)
            ->get(route('ib39.fea.documents.draft.edit', [$document->processing, $document]))
            ->assertForbidden();
        $this->actingAs($this->actor)->get($this->url($document, 'preview'))->assertOk();
        $this->actingAs($this->actor)->get($this->url($document, 'print'))->assertOk();
    }

    public function test_access_rejects_guests_inactive_other_roles_cross_processing_deleted_parents_and_photo_types(): void
    {
        $document = $this->saveSyntheticDraft(Ib39FeaDocumentType::Tir);
        $this->get($this->url($document, 'preview'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role('39th_ib')->create(['is_active' => false]))->get($this->url($document, 'preview'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role('admin')->create())->get($this->url($document, 'preview'))->assertForbidden();

        $other = $this->createOtherRecord()->feaProcessing;
        $this->actingAs($this->actor)->get(route('ib39.fea.documents.draft.preview', [$other, $document]))->assertForbidden();
        $photo = $this->document(Ib39FeaDocumentType::FirearmPhoto);
        $this->actingAs($this->actor)->get($this->url($photo, 'preview'))->assertForbidden();
        $this->record->delete();
        $this->actingAs($this->actor)->get($this->url($document, 'print'))->assertForbidden();
    }

    private function saveSyntheticDraft(Ib39FeaDocumentType $type, array $values = []): Ib39FeaDocument
    {
        $document = $this->document($type);
        $draft = app(Ib39FeaDraftSchema::class)->initial($type, 'Synthetic Preview Subject');
        $draft = array_replace($draft, $values);
        $document->update([
            'draft_data' => $draft,
            'draft_schema_version' => Ib39FeaDraftSchema::VERSION,
            'draft_revision' => 1,
            'draft_saved_at' => now(),
            'draft_saved_by' => $this->actor->id,
        ]);

        return $document->fresh();
    }

    private function editableTypes(): array
    {
        return [Ib39FeaDocumentType::Tir, Ib39FeaDocumentType::Cvif, Ib39FeaDocumentType::Ptis, Ib39FeaDocumentType::Justification];
    }

    private function document(Ib39FeaDocumentType $type): Ib39FeaDocument
    {
        return $this->record->feaProcessing->documents()->where('document_type', $type->value)->firstOrFail();
    }

    private function url(Ib39FeaDocument $document, string $mode): string
    {
        return route('ib39.fea.documents.draft.'.$mode, [$document->processing, $document]);
    }

    private function createOtherRecord(): Ib39SurfacedFormerRebel
    {
        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Other', 'last_name' => 'Record', 'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $this->record->municipality_id, 'barangay_id' => $this->record->barangay_id,
            'surfaced_at' => '2026-08-30', 'possessed_firearms' => true,
        ], $this->actor);
    }
}
