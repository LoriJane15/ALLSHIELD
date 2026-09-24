<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class JapicDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_has_honest_empty_state_and_single_aggregate_overview(): void
    {
        $user = User::factory()->role('japic')->create();
        $this->actingAs($user)->get(route('japic.dashboard'))->assertOk()
            ->assertSee('No certification tasks are currently available.')
            ->assertSee('No intake notifications are available.');
    }

    public function test_dashboard_and_deadline_accessors_cover_calendar_day_boundaries(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');
        $user = User::factory()->role('japic')->create();
        $onTime = $this->processing('Due Today', JapicCertificationStatus::Pending, '2026-09-20');
        $overdue = $this->processing('Overdue', JapicCertificationStatus::Pending, '2026-09-19');
        $completed = $this->processing('Completed Late', JapicCertificationStatus::Completed, '2026-09-18', '2026-09-19');
        $cancelled = $this->processing('Cancelled Early', JapicCertificationStatus::Cancelled, '2026-09-21');
        DB::table('ib39_fr_cancellations')->insert(['ib39_surfaced_former_rebel_id' => $cancelled->ib39_surfaced_former_rebel_id,
            'previous_overall_status' => 'CDR Completed', 'reason' => encrypt('Safe cancellation'), 'cancelled_by' => $user->id,
            'cancelled_at' => '2026-09-20 10:00:00', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('Due today', $onTime->fresh()->deadline_label);
        $this->assertSame('1 day overdue', $overdue->fresh()->deadline_label);
        $this->assertSame('1 day overdue', $completed->fresh()->deadline_label);
        $this->assertSame('1 day remaining', $cancelled->fresh()->load('surfacedFormerRebel.cancellation')->deadline_label);
        $this->assertSame(2, JapicCertificationProcessing::withTiming('overdue')->count());

        $this->actingAs($user)->get(route('japic.dashboard'))->assertOk()
            ->assertSee('4')->assertSee('Completed')->assertSee('Cancelled');
    }

    public function test_malformed_notification_route_data_cannot_create_an_arbitrary_link_or_crash_dashboard(): void
    {
        $user = User::factory()->role('japic')->create();
        $processing = $this->processing('Notification', JapicCertificationStatus::Pending, '2026-09-20');
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'malformed',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'processing_id' => $processing->id,
                'route' => 'super_admin.users.index',
                'route_parameter' => 999,
                'fr_reference' => 'Safe reference',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('japic.dashboard'))->assertOk()
            ->assertSee(route('japic.certifications.show', $processing), false)
            ->assertDontSee(route('super_admin.users.index'), false);
    }

    private function processing(string $lastName, JapicCertificationStatus $status, string $due, ?string $completed = null): JapicCertificationProcessing
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Dashboard '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'JAP-'.uniqid(), 'first_name' => 'Test', 'last_name' => $lastName,
            'category' => Ib39FrCategory::RegularMember->value, 'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => 0, 'created_by' => $actor->id,
            'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => '2026-09-01', 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'private/test.pdf', 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'created_by' => $actor->id, 'finalized_at' => '2026-09-01', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $version]);

        return JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version,
            'status' => $status, 'received_at' => Carbon::parse($due)->subDays(14), 'due_at' => $due, 'completed_at' => $completed, 'lock_version' => 0]);
    }
}
