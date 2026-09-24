<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FrCategory;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39FeaDraftSchema;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ib39FeaDraftEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Ib39SurfacedFormerRebel $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => 'Draft Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Draft Barangay']);
        $this->record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Draft Subject',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30', 'possessed_firearms' => true,
        ], $this->actor);
    }

    public function test_all_official_editors_and_saves_are_unavailable_while_locked(): void
    {
        $auditCount = AuditLog::query()->count();

        foreach ($this->editableTypes() as $type) {
            $document = $this->document($type);
            $this->actingAs($this->actor)->get($this->editUrl($document))->assertForbidden();
            $this->actingAs($this->actor)->put($this->updateUrl($document), [
                'revision' => 0,
                'draft' => app(Ib39FeaDraftSchema::class)->initial($type, 'Locked Subject'),
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('ib39_fea_draft_histories', 0);
        $this->assertDatabaseCount('ib39_fea_processing_histories', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertTrue($this->record->feaProcessing->documents->every(
            fn (Ib39FeaDocument $document): bool => $document->draft_data === null && $document->draft_revision === 0
        ));
    }

    public function test_cross_record_editor_and_crafted_save_cannot_bypass_lock(): void
    {
        $document = $this->document(Ib39FeaDocumentType::Tir);
        $other = $this->createOtherRecord()->feaProcessing;

        $this->actingAs($this->actor)
            ->get(route('ib39.fea.documents.draft.edit', [$other, $document]))
            ->assertForbidden();
        $this->actingAs($this->actor)
            ->put(route('ib39.fea.documents.draft.update', [$other, $document]), [
                'revision' => 0,
                'draft' => ['server_owned' => 'crafted'],
            ])->assertForbidden();

        $this->assertNull($document->fresh()->draft_data);
        $this->assertDatabaseCount('ib39_fea_draft_histories', 0);
    }

    private function editableTypes(): array
    {
        return [Ib39FeaDocumentType::Tir, Ib39FeaDocumentType::Cvif, Ib39FeaDocumentType::Ptis, Ib39FeaDocumentType::Justification];
    }

    private function document(Ib39FeaDocumentType $type): Ib39FeaDocument
    {
        return $this->record->feaProcessing->documents()->where('document_type', $type)->firstOrFail();
    }

    private function editUrl(Ib39FeaDocument $document): string
    {
        return route('ib39.fea.documents.draft.edit', [$document->processing, $document]);
    }

    private function updateUrl(Ib39FeaDocument $document): string
    {
        return route('ib39.fea.documents.draft.update', [$document->processing, $document]);
    }

    private function createOtherRecord(): Ib39SurfacedFormerRebel
    {
        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Other', 'last_name' => 'Draft Record',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $this->record->municipality_id,
            'barangay_id' => $this->record->barangay_id,
            'surfaced_at' => '2026-08-30', 'possessed_firearms' => true,
        ], $this->actor);
    }
}
