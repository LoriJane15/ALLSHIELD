<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationEvent;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelCancellationService;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\JapicCertificationIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JapicCertificationIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_cdr_with_owned_final_version_creates_exactly_one_intake_without_fea(): void
    {
        [$actor, $record] = $this->context(false);
        $cdr = $record->cdrProcessing;
        $completed = now()->subHours(3)->startOfSecond();
        $version = $this->version($cdr->id, $actor->id, $completed->copy()->subDay());
        $cdr->update(['status' => 'Completed', 'completed_at' => $completed, 'completed_by' => $actor->id,
            'current_final_version_id' => $version]);

        $service = app(JapicCertificationIntakeService::class);
        $first = $service->createForCompletedCdr($cdr);
        $second = $service->createForCompletedCdr($cdr->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->received_at->equalTo($completed));
        $this->assertTrue($first->due_at->equalTo($completed->copy()->addDays(14)));
        $this->assertNull($record->feaProcessing);
        $this->assertDatabaseCount('japic_certification_processings', 1);
        $this->assertDatabaseCount('japic_certification_histories', 1);
        $this->assertDatabaseHas('japic_certification_histories', ['event' => JapicCertificationEvent::IntakeCreated->value]);
    }

    public function test_incomplete_missing_final_and_cancelled_records_are_ineligible(): void
    {
        [$actor, $incomplete] = $this->context(false);
        $this->assertNull(app(JapicCertificationIntakeService::class)->createForCompletedCdr($incomplete->cdrProcessing));

        [, $cancelled] = $this->context(false);
        $cdr = $cancelled->cdrProcessing;
        $version = $this->version($cdr->id, $actor->id, now());
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id,
            'current_final_version_id' => $version]);
        app(Ib39SurfacedFormerRebelCancellationService::class)->cancel($cancelled, 'Not eligible', $actor);
        $this->assertNull(app(JapicCertificationIntakeService::class)->createForCompletedCdr($cdr));
        $this->assertDatabaseCount('japic_certification_processings', 0);
    }

    private function context(bool $firearms): array
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'JAPIC Intake '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Intake', 'last_name' => 'Test', 'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality,
            'barangay_id' => null, 'specific_location' => null, 'surfaced_at' => now()->toDateString(),
            'possessed_firearms' => $firearms, 'initial_remarks' => null,
        ], $actor);

        return [$actor, $record->load('cdrProcessing', 'feaProcessing')];
    }

    private function version(int $cdr, int $actor, $finalized): int
    {
        return DB::table('ib39_cdr_document_versions')->insertGetId([
            'cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated',
            'storage_path' => 'private/cdr/final.pdf', 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('c', 64), 'created_by' => $actor, 'finalized_at' => $finalized,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
