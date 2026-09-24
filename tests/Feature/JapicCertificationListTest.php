<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JapicCertificationListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_displays_only_certification_tasks_and_escapes_like_wildcards(): void
    {
        Carbon::setTestNow('2026-09-20');
        $japic = User::factory()->role('japic')->create();
        $literal = $this->processing('REF%_LITERAL', 'Wildcard');
        $this->processing('REF-OTHER', 'Other');

        $this->actingAs($japic)->get(route('japic.certifications.index', ['search' => '%_']))
            ->assertOk()->assertSee($literal->surfacedFormerRebel->reference_number)->assertDontSee('REF-OTHER')
            ->assertSee('Overall FR status')->assertSee('CDR Completed')->assertSee('JAPIC certification status')
            ->assertSee('Delayed')->assertDontSee('CDR completed</th>', false)->assertDontSee('Timing')->assertDontSee('Cancellation');
        $this->actingAs($japic)->get(route('japic.certifications.index', ['timing' => 'overdue']))->assertOk();
    }

    public function test_delayed_is_strictly_after_the_due_instant_and_never_applies_to_completed_or_cancelled_records(): void
    {
        $processing = $this->processing('DELAY-BOUNDARY', 'Boundary');
        Carbon::setTestNow($processing->due_at);
        $this->assertFalse($processing->fresh()->load('surfacedFormerRebel.cancellation')->delayed);

        Carbon::setTestNow($processing->due_at->addSecond());
        $this->assertTrue($processing->fresh()->load('surfacedFormerRebel.cancellation')->delayed);
        $processing->forceFill(['status' => JapicCertificationStatus::Completed])->save();
        $this->assertFalse($processing->fresh()->load('surfacedFormerRebel.cancellation')->delayed);

        $processing->forceFill(['status' => JapicCertificationStatus::Pending])->save();
        DB::table('ib39_fr_cancellations')->insert([
            'ib39_surfaced_former_rebel_id' => $processing->ib39_surfaced_former_rebel_id,
            'previous_overall_status' => 'CDR Completed',
            'reason' => encrypt('Cancelled at boundary'),
            'cancelled_by' => User::factory()->role('39th_ib')->create()->id,
            'cancelled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertFalse($processing->fresh()->load('surfacedFormerRebel.cancellation')->delayed);
    }

    public function test_filters_are_allowlisted_validated_and_pagination_keeps_only_validated_values(): void
    {
        $japic = User::factory()->role('japic')->create();
        foreach (range(1, 16) as $number) {
            $this->processing('LIST-'.$number, 'Person '.$number);
        }
        $this->actingAs($japic)->get(route('japic.certifications.index', ['status' => JapicCertificationStatus::Pending->value, 'page' => 1]))
            ->assertOk()->assertSee('page=2');
        foreach ([['status' => 'Delayed'], ['timing' => 'late'], ['received_from' => '09/01/2026'], ['unknown' => 'x']] as $filters) {
            $this->actingAs($japic)->get(route('japic.certifications.index', $filters))->assertSessionHasErrors();
        }
    }

    public function test_list_derives_japic_certified_from_valid_final_pointers_without_n_plus_one_queries(): void
    {
        $japic = User::factory()->role('japic')->create();
        $records = collect(range(1, 3))->map(fn (int $number) => $this->processing('CERTIFIED-'.$number, 'Person '.$number));
        foreach ($records as $processing) {
            $final = JapicCertificationDocumentVersion::query()->forceCreate([
                'processing_id' => $processing->id, 'version_number' => 1,
                'storage_path' => "japic/certifications/{$processing->id}/final-documents/final.pdf",
                'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1,
                'sha256' => hash('sha256', (string) $processing->id), 'uploaded_by' => $japic->id,
                'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now(),
            ]);
            $processing->forceFill([
                'status' => JapicCertificationStatus::Completed,
                'current_final_version_id' => $final->id,
                'completed_at' => now(),
                'completed_by' => $japic->id,
            ])->save();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($japic)->get(route('japic.certifications.index'))->assertOk();
        foreach ($records as $processing) {
            $response->assertSee($processing->surfacedFormerRebel->reference_number);
        }
        $response->assertSee('JAPIC Certified');

        $queries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        foreach (['ib39_cdr_processings', 'ib39_cdr_document_versions', 'ib39_fr_cancellations', 'japic_certification_document_versions'] as $table) {
            $this->assertLessThanOrEqual(1, $queries->filter(fn (string $sql): bool => str_contains($sql, 'from "'.$table.'"'))->count(), "{$table} was queried per row.");
        }
        $this->assertFalse($queries->contains(fn (string $sql): bool => str_contains($sql, 'fr_government_assistances')));
    }

    private function processing(string $reference, string $lastName): JapicCertificationProcessing
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'List '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => $reference, 'first_name' => 'List', 'last_name' => $lastName,
            'category' => Ib39FrCategory::RegularMember->value, 'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, 'municipality_id' => $municipality,
            'surfaced_at' => '2026-09-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => '2026-09-02', 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'private/final.pdf', 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64), 'created_by' => $actor->id, 'finalized_at' => '2026-09-02', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $version]);

        return JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version,
            'status' => JapicCertificationStatus::Pending, 'received_at' => '2026-09-02', 'due_at' => '2026-09-16', 'lock_version' => 0])->load('surfacedFormerRebel');
    }
}
