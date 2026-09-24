<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationEvent;
use App\Enums\JapicCertificationStatus;
use App\Models\AuditLog;
use App\Models\Ib39FrCancellation;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationDraft;
use App\Models\JapicCertificationDraftHistory;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelCancellationService;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\JapicCertificationCancellationCoordinator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class Ib39SurfacedFormerRebelCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_39th_ib_user_can_cancel_without_deleting_related_data(): void
    {
        [$actor, $record] = $this->context();
        $counts = $this->protectedCounts();

        $this->actingAs($actor)->post(route('ib39.fr-profiles.cancel', $record), [
            'reason' => '  Approved operational cancellation reason.  ',
            'confirmed' => '1',
        ])->assertRedirect(route('ib39.fr-profiles.show', $record));

        $cancellation = Ib39FrCancellation::query()->sole();
        $this->assertSame('Approved operational cancellation reason.', $cancellation->reason);
        $this->assertSame($actor->id, $cancellation->cancelled_by);
        $this->assertSame($counts, $this->protectedCounts());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ib39_surfaced_former_rebel_cancelled',
            'entity_id' => $record->id,
        ]);
        $this->assertArrayNotHasKey('reason', AuditLog::query()->latest('id')->firstOrFail()->new_values);
    }

    public function test_validation_and_authorization_are_enforced(): void
    {
        [$actor, $record] = $this->context();
        $route = route('ib39.fr-profiles.cancel', $record);

        $this->post($route, [])->assertRedirect(route('login'));
        foreach (['super_admin', 'admin', 'lgu', 'gov_agency', 'mblrc', 'afp'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->post($route, ['reason' => 'Not authorized', 'confirmed' => '1'])
                ->assertForbidden();
        }
        $this->actingAs($actor)->post($route, ['reason' => ''])->assertSessionHasErrors(['reason', 'confirmed']);
        $this->actingAs($actor)->post($route, [
            'reason' => str_repeat('x', 2001),
            'confirmed' => '1',
        ])->assertSessionHasErrors('reason');
        foreach (['cancelled_by', 'cancelled_at', 'previous_overall_status', 'status'] as $field) {
            $this->actingAs($actor)->post($route, [
                'reason' => 'Valid reason', 'confirmed' => '1', $field => 'server-owned',
            ])->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('ib39_fr_cancellations', 0);
    }

    public function test_duplicate_cancellation_is_rejected_and_history_is_immutable(): void
    {
        [$actor, $record] = $this->context();
        $service = app(Ib39SurfacedFormerRebelCancellationService::class);
        $service->cancel($record, 'First approved reason', $actor);

        try {
            $service->cancel($record, 'Duplicate reason', $actor);
            $this->fail('A duplicate cancellation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertDatabaseCount('ib39_fr_cancellations', 1);
        $this->expectException(LogicException::class);
        Ib39FrCancellation::query()->sole()->update(['reason' => 'Changed']);
    }

    public function test_cancellation_without_japic_task_creates_none_and_does_not_mutate_cdr_fea_or_rcsp(): void
    {
        [$actor, $record] = $this->context();
        $cdr = $record->cdrProcessing()->firstOrFail();
        $fea = $record->feaProcessing()->firstOrFail();
        $document = $fea->documents()->firstOrFail();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(Ib39SurfacedFormerRebelCancellationService::class)
            ->cancel($record, 'Approved cancellation', $actor);

        $this->actingAs($actor)->post(route('ib39.cdr.start', $cdr))->assertForbidden();
        $this->actingAs($actor)->post(route('ib39.fea.documents.start', [$fea, $document]))->assertForbidden();
        $this->assertSame(Ib39FeaDocumentStatus::Pending, $document->fresh()->status);
        $this->assertDatabaseCount('japic_certification_processings', 0);
        $this->assertFalse(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'rcsp')));
    }

    public function test_active_japic_task_is_cancelled_once_while_drafts_versions_cdr_and_fea_are_preserved(): void
    {
        [$actor, $record] = $this->context();
        $processing = $this->japicProcessing($record, JapicCertificationStatus::Drafting);
        JapicCertificationDraft::query()->forceCreate([
            'processing_id' => $processing->id, 'payload' => ['name' => 'Private draft'],
            'schema_version' => 1, 'revision' => 1, 'last_saved_by' => $actor->id, 'last_saved_at' => now(),
        ]);
        JapicCertificationDraftHistory::query()->forceCreate([
            'processing_id' => $processing->id, 'revision' => 1, 'payload' => ['name' => 'Private draft'],
            'saved_by' => $actor->id, 'saved_at' => now(),
        ]);
        JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $processing->id, 'version_number' => 1, 'storage_path' => 'private/japic/final.pdf',
            'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 123,
            'sha256' => str_repeat('a', 64), 'uploaded_by' => $actor->id, 'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $protected = $this->protectedCounts();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        app(Ib39SurfacedFormerRebelCancellationService::class)->cancel($record, 'Approved cancellation', $actor);

        $this->assertSame(JapicCertificationStatus::Cancelled, $processing->fresh()->status);
        $this->assertDatabaseCount('japic_certification_drafts', 1);
        $this->assertDatabaseCount('japic_certification_draft_histories', 1);
        $this->assertDatabaseCount('japic_certification_document_versions', 1);
        $this->assertSame($protected, $this->protectedCounts());
        $this->assertDatabaseHas('japic_certification_histories', [
            'processing_id' => $processing->id, 'from_status' => JapicCertificationStatus::Drafting->value,
            'to_status' => JapicCertificationStatus::Cancelled->value, 'event' => JapicCertificationEvent::FrCancelled->value,
        ]);
        $this->assertDatabaseCount('japic_certification_histories', 1);
        $this->assertFalse(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'rcsp')));
    }

    public function test_completed_japic_remains_completed_and_records_informational_event(): void
    {
        [$actor, $record] = $this->context();
        $processing = $this->japicProcessing($record, JapicCertificationStatus::Completed);
        app(Ib39SurfacedFormerRebelCancellationService::class)->cancel($record, 'Later cancellation', $actor);

        $this->assertSame(JapicCertificationStatus::Completed, $processing->fresh()->status);
        $this->assertDatabaseHas('japic_certification_histories', [
            'processing_id' => $processing->id, 'from_status' => JapicCertificationStatus::Completed->value,
            'to_status' => JapicCertificationStatus::Completed->value,
            'event' => JapicCertificationEvent::FrCancelledAfterCompletion->value,
        ]);
    }

    public function test_cancellation_after_both_valid_final_workflows_captures_japic_certified_as_previous_status(): void
    {
        [$actor, $record] = $this->context();
        $cdr = $record->cdrProcessing()->firstOrFail();
        $cdrVersionId = $this->cdrVersion($record);
        $cdr->forceFill([
            'status' => 'Completed',
            'current_final_version_id' => $cdrVersionId,
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ])->save();
        $received = now()->subDay();
        $processing = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_document_version_id' => $cdrVersionId,
            'status' => JapicCertificationStatus::Completed,
            'received_at' => $received,
            'due_at' => $received->copy()->addDays(14),
            'completed_at' => now(),
            'completed_by' => $actor->id,
            'lock_version' => 0,
        ]);
        $final = JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $processing->id, 'version_number' => 1,
            'storage_path' => "japic/certifications/{$processing->id}/final-documents/final.pdf",
            'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1,
            'sha256' => str_repeat('f', 64), 'uploaded_by' => $actor->id,
            'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $processing->forceFill(['current_final_version_id' => $final->id])->save();

        app(Ib39SurfacedFormerRebelCancellationService::class)->cancel($record, 'Post-certification cancellation', $actor);

        $this->assertSame('JAPIC Certified', $record->cancellation()->sole()->previous_overall_status);
        $this->assertSame('Cancelled', $record->fresh()->load([
            'cancellation',
            'cdrProcessing.currentFinalVersion',
            'japicCertificationProcessing.currentFinalVersion',
        ])->overall_case_status);
        $this->assertSame(JapicCertificationStatus::Completed, $processing->fresh()->status);
    }

    public function test_failed_japic_coordination_rolls_back_entire_cancellation(): void
    {
        [$actor, $record] = $this->context();
        $processing = $this->japicProcessing($record, JapicCertificationStatus::Pending);
        DB::unprepared("CREATE TRIGGER fail_japic_coordination BEFORE INSERT ON japic_certification_histories BEGIN SELECT RAISE(ABORT, 'simulated coordination failure'); END");

        try {
            app(Ib39SurfacedFormerRebelCancellationService::class)->cancel($record, 'Rollback all', $actor);
            $this->fail('The simulated coordination failure did not abort cancellation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated coordination failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('ib39_fr_cancellations', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'ib39_surfaced_former_rebel_cancelled']);
        $this->assertSame(JapicCertificationStatus::Pending, $processing->fresh()->status);
        $this->assertDatabaseCount('japic_certification_histories', 0);
    }

    public function test_repeated_coordination_cannot_duplicate_history(): void
    {
        [$actor, $record] = $this->context();
        $processing = $this->japicProcessing($record, JapicCertificationStatus::Pending);
        $cancellation = app(Ib39SurfacedFormerRebelCancellationService::class)->cancel($record, 'Approved once', $actor);
        app(JapicCertificationCancellationCoordinator::class)->coordinate($record, $cancellation, $actor);

        $this->assertDatabaseCount('japic_certification_histories', 1);
        $this->assertSame(JapicCertificationStatus::Cancelled, $processing->fresh()->status);
    }

    public function test_audit_failure_rolls_back_cancellation(): void
    {
        [$actor, $record] = $this->context();
        DB::unprepared("CREATE TRIGGER fail_fr_cancellation_audit BEFORE INSERT ON audit_logs WHEN NEW.action = 'ib39_surfaced_former_rebel_cancelled' BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");

        try {
            app(Ib39SurfacedFormerRebelCancellationService::class)->cancel($record, 'Rollback reason', $actor);
            $this->fail('The simulated audit failure did not abort cancellation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('ib39_fr_cancellations', 0);
    }

    private function context(): array
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => 'Cancellation Test Municipality']);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Synthetic', 'last_name' => 'Cancellation',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality->id, 'barangay_id' => null,
            'specific_location' => null, 'surfaced_at' => '2026-09-01',
            'possessed_firearms' => true, 'initial_remarks' => null,
        ], $actor);

        return [$actor, $record];
    }

    private function protectedCounts(): array
    {
        return [
            'fr' => DB::table('ib39_surfaced_former_rebels')->count(),
            'cdr' => DB::table('ib39_cdr_processings')->count(),
            'cdr_forms' => DB::table('ib39_cdr_forms')->count(),
            'fea' => DB::table('ib39_fea_processings')->count(),
            'fea_documents' => DB::table('ib39_fea_documents')->count(),
        ];
    }

    private function japicProcessing(Ib39SurfacedFormerRebel $record, JapicCertificationStatus $status): JapicCertificationProcessing
    {
        $received = now()->subDay();

        return JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_document_version_id' => $this->cdrVersion($record),
            'status' => $status, 'received_at' => $received, 'due_at' => $received->copy()->addDays(14),
            'completed_at' => $status === JapicCertificationStatus::Completed ? now() : null, 'lock_version' => 0,
        ]);
    }

    private function cdrVersion(Ib39SurfacedFormerRebel $record): int
    {
        $actor = User::factory()->role('39th_ib')->create();

        return DB::table('ib39_cdr_document_versions')->insertGetId([
            'cdr_processing_id' => $record->cdrProcessing()->value('id'), 'version_number' => 1,
            'source_type' => 'generated', 'storage_path' => 'private/cdr/final.pdf', 'original_filename' => 'final.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 123, 'sha256' => str_repeat('b', 64),
            'created_by' => $actor->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
