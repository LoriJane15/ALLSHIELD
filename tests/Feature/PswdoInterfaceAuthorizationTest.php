<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PswdoInterfaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pswdo_home_empty_states_and_only_one_mutation_route_are_truthful(): void
    {
        $pswdo = User::factory()->role('pswdo')->create();
        $this->assertSame('pswdo.dashboard', $pswdo->homeRoute());
        $this->actingAs($pswdo)->get(route('pswdo.dashboard'))->assertOk()->assertSee('No surfaced FR currently');
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.index'))->assertOk()->assertSee('No eligible surfaced FRs');

        $mutations = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'pswdo.') && ! in_array('GET', $route->methods(), true))
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->methods()[0]])->all();
        $this->assertSame(['pswdo.enrollments.documents.store' => 'POST'], $mutations);
    }

    public function test_guest_inactive_and_other_roles_cannot_enter_pswdo_routes(): void
    {
        foreach ([route('pswdo.dashboard'), route('pswdo.enrollments.index')] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $inactive = User::factory()->role('pswdo')->create(['is_active' => false]);
        foreach ([route('pswdo.dashboard'), route('pswdo.enrollments.index')] as $url) {
            $this->actingAs($inactive)->get($url)->assertRedirect(route('login'));
        }
        foreach (['39th_ib', 'japic', 'admin', 'super_admin', 'lgu', 'afp', 'mblrc', 'gov_agency'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('pswdo.dashboard'))->assertForbidden();
        }
    }

    public function test_profile_workspace_and_progress_display_only_for_eligible_enrollment(): void
    {
        [$enrollment, $pswdo] = $this->enrollment();
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.show', $enrollment))->assertOk()
            ->assertSee('Documents/Records')
            ->assertSeeInOrder([
                'CDR', 'JAPIC Certification', 'PSWDO Enrollment Documents',
                'FEA Processing Documents', 'Assistance Records',
            ])
            ->assertSee('href="'.route('pswdo.enrollments.records.pswdo', $enrollment).'"', false)
            ->assertDontSee('E-CLIP Enrollment Form')
            ->assertDontSee('No documents available')
            ->assertDontSee('type="file"', false)
            ->assertDontSee('Upload Final Signed PDF')
            ->assertDontSee('Replace')->assertDontSee('Delete');
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.records.pswdo', $enrollment))->assertOk()
            ->assertSee('No documents available')
            ->assertDontSee('E-CLIP Enrollment Form')
            ->assertDontSee('Secure preview')
            ->assertDontSee('Secure download');
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.workspace', $enrollment))->assertOk()
            ->assertSee('Upload Final Signed PDF')->assertSee('Endorsement Letter is locked')
            ->assertDontSee('Drafting')->assertDontSee('For Signature');

        $enrollment->surfacedFormerRebel->cdrProcessing->update(['current_final_version_id' => null]);
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.show', $enrollment))->assertForbidden();
    }

    private function enrollment(): array
    {
        $pswdo = User::factory()->role('pswdo')->create();
        $ib39 = User::factory()->role('39th_ib')->create();
        $japic = User::factory()->role('japic')->create();
        $municipality = Municipality::query()->create(['name' => 'Interface Municipality']);
        $record = app(Ib39SurfacedFormerRebelService::class)->create(['first_name' => 'Interface', 'last_name' => 'Subject', 'category' => Ib39FrCategory::RegularMember->value, 'municipality_id' => $municipality->id, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => true], $ib39)->load('cdrProcessing');
        $cdr = $record->cdrProcessing;
        $cdrVersion = $cdr->documentVersions()->create(['version_number' => 1, 'source_type' => 'uploaded', 'storage_path' => "ib39/cdr/{$cdr->id}/final.pdf", 'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('a', 64), 'created_by' => $ib39->id, 'finalized_at' => now()]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $ib39->id, 'current_final_version_id' => $cdrVersion->id]);
        $processing = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id, 'triggering_cdr_document_version_id' => $cdrVersion->id, 'status' => 'Completed', 'received_at' => now(), 'due_at' => now()->addDays(14), 'completed_at' => now(), 'completed_by' => $japic->id, 'lock_version' => 1]);
        $final = $processing->documentVersions()->create(['version_number' => 1, 'storage_path' => "japic/certifications/{$processing->id}/final-documents/final.pdf", 'original_filename' => 'japic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('b', 64), 'uploaded_by' => $japic->id, 'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now()]);
        $processing->forceFill(['current_final_version_id' => $final->id])->save();
        $enrollment = DB::transaction(fn () => app(PswdoEnrollmentIntakeService::class)->receiveEligible($record->id));

        return [$enrollment, $pswdo];
    }
}
