<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39FeaDocumentWorkflowService;
use App\Services\Ib39FeaDraftSchema;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Ib39FeaPreliminaryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'FEA processing is unavailable until the required PSWDO enrollment forms are completed.';

    public function test_direct_preliminary_workflow_mutations_are_locked_without_writes(): void
    {
        [$actor, $processing, $document] = $this->context();
        $before = $this->fingerprint();
        $workflow = app(Ib39FeaDocumentWorkflowService::class);

        $this->assertLocked(fn () => $workflow->start($processing, $document, $actor));
        $this->assertLocked(fn () => $workflow->update($processing, $document, $this->payload(), $actor));
        $this->assertLocked(fn () => $workflow->saveDraft(
            $processing,
            $document,
            app(Ib39FeaDraftSchema::class)->initial(Ib39FeaDocumentType::Tir, 'Locked Subject'),
            0,
            $actor,
        ));

        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(Ib39FeaDocumentStatus::Pending, $document->fresh()->status);
        $this->assertNull($document->fresh()->draft_data);
    }

    public function test_preliminary_routes_deny_active_actor_and_preserve_status_histories_and_audits(): void
    {
        [$actor, $processing, $document] = $this->context();
        $before = $this->fingerprint();

        $this->actingAs($actor)
            ->post(route('ib39.fea.documents.start', [$processing, $document]))
            ->assertForbidden();
        $this->actingAs($actor)
            ->patch(route('ib39.fea.documents.update', [$processing, $document]), ['document' => $this->payload()])
            ->assertForbidden();

        $this->assertSame($before, $this->fingerprint());
    }

    private function assertLocked(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The temporary FEA lock did not deny a direct workflow mutation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(self::MESSAGE, $exception->getMessage());
        }
    }

    private function fingerprint(): string
    {
        $tables = [
            'ib39_fea_processings', 'ib39_fea_documents', 'ib39_fea_document_histories',
            'ib39_fea_processing_histories', 'ib39_fea_draft_histories', 'audit_logs',
        ];

        return hash('sha256', collect($tables)->mapWithKeys(
            fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()]
        )->toJson());
    }

    private function context(): array
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => 'Workflow Municipality']);
        $barangay = Barangay::query()->create(['municipality_id' => $municipality->id, 'name' => 'Workflow Barangay']);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Workflow',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30', 'possessed_firearms' => true,
        ], $actor);
        $processing = $record->feaProcessing()->firstOrFail();
        $document = $processing->documents()->where('document_type', Ib39FeaDocumentType::Tir)->firstOrFail();

        return [$actor, $processing, $document];
    }

    private function payload(): array
    {
        return [
            'status' => Ib39FeaDocumentStatus::Processing->value,
            'compliance_status' => Ib39FeaComplianceStatus::None->value,
            'remarks' => null,
            'compliance_reason' => null,
            'is_delayed' => false,
            'delay_reason' => null,
        ];
    }
}
