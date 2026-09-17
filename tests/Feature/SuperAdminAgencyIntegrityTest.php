<?php

namespace Tests\Feature;

use App\Models\GovAgency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperAdminAgencyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const DEPENDENCY_ERROR = 'Agency cannot be deleted while linked accounts or implementation records exist.';

    public function test_agency_delete_route_remains_delete_only_with_existing_middleware(): void
    {
        $route = Route::getRoutes()->getByName('super_admin.agencies.destroy');

        $this->assertNotNull($route);
        $this->assertSame(['DELETE'], $route->methods());
        $this->assertSame('super-admin/agencies/{agency}', $route->uri());
        $this->assertSame(['web', 'auth', 'role:super_admin'], $route->gatherMiddleware());

        $agency = $this->agency('Delete Only', 'DO');
        $this->actingAs($this->superAdmin())
            ->post(route('super_admin.agencies.destroy', $agency))
            ->assertStatus(405);

        $this->assertNotNull($agency->fresh());
    }

    public function test_super_administrator_can_delete_a_genuinely_unreferenced_agency(): void
    {
        $agency = $this->agency('Unreferenced Agency', 'UA');

        $this->actingAs($this->superAdmin())
            ->from(route('super_admin.agencies.index'))
            ->delete(route('super_admin.agencies.destroy', $agency))
            ->assertRedirect(route('super_admin.agencies.index'))
            ->assertSessionHas('success', 'Agency deleted.');

        $this->assertDatabaseMissing('gov_agencies', ['id' => $agency->id]);
    }

    public function test_active_and_inactive_assigned_users_each_block_deletion(): void
    {
        foreach ([true, false] as $isActive) {
            $agency = $this->agency(
                $isActive ? 'Active User Agency' : 'Inactive User Agency',
                $isActive ? 'AUA' : 'IUA'
            );
            $assigned = User::factory()->govAgency($agency->id)->create(['is_active' => $isActive]);

            $this->assertDeletionIsBlocked($agency);
            $this->assertDatabaseHas('users', [
                'id' => $assigned->id,
                'gov_agency_id' => $agency->id,
                'is_active' => $isActive,
            ]);
        }
    }

    public function test_implan_response_and_implementation_tagging_each_block_deletion(): void
    {
        foreach (['response', 'tagging'] as $dependency) {
            $agency = $this->agency(
                $dependency === 'response' ? 'Response Agency' : 'Tagging Agency',
                $dependency === 'response' ? 'RA' : 'TA'
            );
            $this->attachDependencies($agency, [$dependency]);

            $this->assertDeletionIsBlocked($agency);
            $this->assertDatabaseHas(
                $dependency === 'response' ? 'agency_implan_responses' : 'implementation_taggings',
                ['gov_agency_id' => $agency->id]
            );
        }
    }

    public function test_every_combination_of_dependency_categories_blocks_deletion_and_preserves_records(): void
    {
        $superAdmin = $this->superAdmin();
        $combinations = [
            ['user'],
            ['response'],
            ['tagging'],
            ['user', 'response'],
            ['user', 'tagging'],
            ['response', 'tagging'],
            ['user', 'response', 'tagging'],
        ];

        foreach ($combinations as $index => $dependencies) {
            $agency = $this->agency("Dependency Combination {$index}", "DC{$index}");
            $this->attachDependencies($agency, $dependencies);
            $before = $this->protectedTableCounts();

            $this->assertDeletionIsBlocked($agency, $superAdmin);

            $this->assertSame($before, $this->protectedTableCounts());
            $this->assertNotNull($agency->fresh());

            if (in_array('user', $dependencies, true)) {
                $this->assertDatabaseHas('users', ['gov_agency_id' => $agency->id]);
            }

            if (in_array('response', $dependencies, true)) {
                $this->assertDatabaseHas('agency_implan_responses', ['gov_agency_id' => $agency->id]);
            }

            if (in_array('tagging', $dependencies, true)) {
                $this->assertDatabaseHas('implementation_taggings', ['gov_agency_id' => $agency->id]);
            }
        }
    }

    public function test_forged_client_counts_cannot_bypass_fresh_dependency_checks(): void
    {
        $agency = $this->agency('Fresh State Agency', 'FSA');
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSee(route('super_admin.agencies.destroy', $agency));

        $assigned = User::factory()->govAgency($agency->id)->create(['is_active' => false]);

        $this->actingAs($superAdmin)
            ->from(route('super_admin.agencies.index'))
            ->delete(route('super_admin.agencies.destroy', $agency), [
                'users_count' => 0,
                'responses_count' => 0,
                'implementation_taggings_count' => 0,
                'can_delete' => true,
            ])
            ->assertRedirect(route('super_admin.agencies.index'))
            ->assertSessionHasErrors(['agency_deletion' => self::DEPENDENCY_ERROR]);

        $this->assertDatabaseHas('gov_agencies', ['id' => $agency->id]);
        $this->assertDatabaseHas('users', [
            'id' => $assigned->id,
            'gov_agency_id' => $agency->id,
        ]);
    }

    public function test_interface_shows_delete_only_for_unreferenced_agencies(): void
    {
        $safe = $this->agency('Safe Interface Agency', 'SIA');

        $this->actingAs($this->superAdmin())
            ->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSee('data-confirm-title="Confirm Agency Deletion"', false)
            ->assertDontSee(self::DEPENDENCY_ERROR);

        User::factory()->govAgency($safe->id)->create(['is_active' => false]);

        $this->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertDontSee('data-confirm-title="Confirm Agency Deletion"', false)
            ->assertSeeText(self::DEPENDENCY_ERROR);
    }

    public function test_interface_disables_deletion_for_each_dependency_category(): void
    {
        $superAdmin = $this->superAdmin();

        foreach (['user', 'response', 'tagging'] as $index => $dependency) {
            $agency = $this->agency("Interface Dependency {$index}", "ID{$index}");
            $this->attachDependencies($agency, [$dependency]);

            $this->actingAs($superAdmin)
                ->get(route('super_admin.agencies.index', ['search' => $agency->acronym]))
                ->assertOk()
                ->assertSeeText(self::DEPENDENCY_ERROR)
                ->assertDontSee("Are you sure you want to delete agency '{$agency->acronym}'?");
        }
    }

    public function test_interface_displays_only_active_assigned_users_with_accurate_wording(): void
    {
        $singular = $this->agency('Singular Count Agency', 'SCA');
        User::factory()->govAgency($singular->id)->create(['is_active' => true]);
        User::factory()->govAgency($singular->id)->create(['is_active' => false]);

        $plural = $this->agency('Plural Count Agency', 'PCA');
        User::factory()->count(2)->govAgency($plural->id)->create(['is_active' => true]);

        $inactiveOnly = $this->agency('Inactive Only Agency', 'IOA');
        User::factory()->govAgency($inactiveOnly->id)->create(['is_active' => false]);

        $this->actingAs($this->superAdmin())
            ->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSeeText('1 Active user')
            ->assertSeeText('2 Active users')
            ->assertSeeText('0 Active users')
            ->assertDontSee("Are you sure you want to delete agency '{$inactiveOnly->acronym}'?");
    }

    public function test_dependency_warning_exposes_no_dependent_record_information(): void
    {
        $agency = $this->agency('Private Dependency Agency', 'PDA');
        User::factory()->govAgency($agency->id)->create([
            'name' => 'PRIVATE DEPENDENT NAME MARKER',
            'username' => 'private-dependent-marker',
            'is_active' => false,
        ]);
        $this->attachDependencies($agency, ['response', 'tagging']);

        $this->actingAs($this->superAdmin())
            ->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSeeText(self::DEPENDENCY_ERROR)
            ->assertDontSee('PRIVATE DEPENDENT NAME MARKER')
            ->assertDontSee('private-dependent-marker')
            ->assertDontSee('PRIVATE RESPONSE REASON MARKER')
            ->assertDontSee('PRIVATE TAGGING REASON MARKER');
    }

    public function test_non_super_administrators_and_inactive_super_administrators_cannot_delete_agencies(): void
    {
        $agency = $this->agency('Authorization Agency', 'AA');

        $this->actingAs(User::factory()->role('admin')->create())
            ->delete(route('super_admin.agencies.destroy', $agency))
            ->assertForbidden();

        $this->actingAs(User::factory()->role('super_admin')->create(['is_active' => false]))
            ->delete(route('super_admin.agencies.destroy', $agency))
            ->assertRedirect(route('login'));

        $this->assertNotNull($agency->fresh());
    }

    public function test_rejected_deletion_renders_a_controlled_interface_error(): void
    {
        $agency = $this->agency('Controlled Error Agency', 'CEA');
        User::factory()->govAgency($agency->id)->create();

        $this->actingAs($this->superAdmin())
            ->from(route('super_admin.agencies.index', ['search' => 'CEA']))
            ->delete(route('super_admin.agencies.destroy', $agency))
            ->assertRedirect(route('super_admin.agencies.index', ['search' => 'CEA']))
            ->assertSessionHasErrors(['agency_deletion' => self::DEPENDENCY_ERROR]);

        $this->get(route('super_admin.agencies.index', ['search' => 'CEA']))
            ->assertOk()
            ->assertSeeText(self::DEPENDENCY_ERROR)
            ->assertSeeText('Controlled Error Agency');
    }

    public function test_existing_agency_create_and_update_operations_remain_available(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('super_admin.agencies.store'), [
                'name' => 'Created Agency',
                'acronym' => 'CA',
                'profile' => null,
            ])
            ->assertSessionHasNoErrors();

        $agency = GovAgency::query()->where('acronym', 'CA')->sole();

        $this->put(route('super_admin.agencies.update', $agency), [
            'name' => 'Updated Agency',
            'acronym' => 'UA',
            'profile' => null,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gov_agencies', [
            'id' => $agency->id,
            'name' => 'Updated Agency',
            'acronym' => 'UA',
        ]);
    }

    public function test_index_dependency_aggregates_use_a_bounded_number_of_queries(): void
    {
        $this->agency('Initial Query Agency', 'IQA');
        $this->actingAs($this->superAdmin());

        [$initialCount] = $this->measureAgencyIndexQueries();

        foreach (range(1, 12) as $index) {
            $this->agency("Additional Query Agency {$index}", "AQA{$index}");
        }

        [$expandedCount, $expandedQueries] = $this->measureAgencyIndexQueries();
        $agencySql = collect($expandedQueries)
            ->pluck('query')
            ->filter(fn (string $sql) => str_contains($sql, 'from "gov_agencies"'))
            ->implode(' ');

        $this->assertLessThanOrEqual($initialCount + 1, $expandedCount);
        $this->assertStringContainsString('active_users_count', $agencySql);
        $this->assertStringContainsString('agency_implan_responses', $agencySql);
        $this->assertStringContainsString('implementation_taggings', $agencySql);
    }

    private function superAdmin(): User
    {
        return User::factory()->role('super_admin')->create();
    }

    private function agency(string $name, string $acronym): GovAgency
    {
        return GovAgency::create([
            'name' => $name,
            'acronym' => $acronym,
            'profile' => null,
        ]);
    }

    /** @param array<int, string> $dependencies */
    private function attachDependencies(GovAgency $agency, array $dependencies): void
    {
        if (in_array('user', $dependencies, true)) {
            User::factory()->govAgency($agency->id)->create(['is_active' => false]);
        }

        if (! array_intersect(['response', 'tagging'], $dependencies)) {
            return;
        }

        $implementationId = DB::table('implementations')->insertGetId([
            'lgu_user_id' => User::factory()->role('lgu')->create()->id,
            'status' => 'not yet started',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (in_array('response', $dependencies, true)) {
            DB::table('agency_implan_responses')->insert([
                'gov_agency_id' => $agency->id,
                'implementation_id' => $implementationId,
                'response_status' => 'rejected',
                'rejection_reason' => 'PRIVATE RESPONSE REASON MARKER',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (in_array('tagging', $dependencies, true)) {
            DB::table('implementation_taggings')->insert([
                'implementation_id' => $implementationId,
                'gov_agency_id' => $agency->id,
                'status' => 'Rejected',
                'reason' => 'PRIVATE TAGGING REASON MARKER',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, int> */
    private function protectedTableCounts(): array
    {
        return collect([
            'gov_agencies',
            'users',
            'agency_implan_responses',
            'implementation_taggings',
        ])->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
    }

    private function assertDeletionIsBlocked(GovAgency $agency, ?User $superAdmin = null): void
    {
        $this->actingAs($superAdmin ?? $this->superAdmin())
            ->from(route('super_admin.agencies.index'))
            ->delete(route('super_admin.agencies.destroy', $agency))
            ->assertRedirect(route('super_admin.agencies.index'))
            ->assertSessionHasErrors(['agency_deletion' => self::DEPENDENCY_ERROR]);

        $this->assertDatabaseHas('gov_agencies', ['id' => $agency->id]);
    }

    /** @return array{int, array<int, array<string, mixed>>} */
    private function measureAgencyIndexQueries(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('super_admin.agencies.index'))->assertOk();
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        return [count($queries), $queries];
    }
}
