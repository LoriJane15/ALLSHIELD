<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use App\Models\User;
use App\Services\ChatConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperAdminAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_routes_do_not_exist(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create();

        $this->assertFalse(Route::has('super_admin.users.destroy'));
        $this->assertFalse(Route::has('profile.destroy'));

        $this->actingAs($superAdmin)
            ->delete("/super-admin/users/{$target->id}")
            ->assertStatus(405);
        $this->delete('/profile', ['password' => 'password'])
            ->assertStatus(405);

        $this->assertNotNull($target->fresh());
        $this->assertNotNull($superAdmin->fresh());
    }

    public function test_forged_delete_request_preserves_accounts_and_operational_history(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $municipality = Municipality::create(['name' => 'Lifecycle Test Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Lifecycle Test Barangay',
        ]);
        $target = User::factory()->lgu($municipality->id)->create();
        $peer = User::factory()->role('admin')->create();

        $conversation = app(ChatConversationService::class)->start($target, $peer->id);
        $conversation->messages()->create([
            'sender_id' => $target->id,
            'body' => 'Synthetic preservation marker',
        ]);

        $phase = RcspPhase::query()
            ->where('catalog_key', RcspPhase::CONFIGURABLE_CATALOG_KEY)
            ->where('number', 0)
            ->firstOrFail();
        $rcspBarangay = RcspBarangay::create([
            'barangay_id' => $barangay->id,
            'municipality_id' => $municipality->id,
            'catalog_key' => RcspPhase::CONFIGURABLE_CATALOG_KEY,
        ]);
        $activity = RcspActivity::create([
            'rcsp_phase_id' => $phase->id,
            'rcsp_barangay_id' => $rcspBarangay->id,
            'created_by_user_id' => $target->id,
            'description' => 'Synthetic lifecycle activity',
            'normalized_title' => 'synthetic lifecycle activity',
        ]);
        RcspForm::create([
            'lgu_user_id' => $target->id,
            'rcsp_barangay_id' => $rcspBarangay->id,
            'rcsp_phase_id' => $phase->id,
            'rcsp_activity_id' => $activity->id,
            'submission_version' => 1,
            'conduct' => 'yes',
            'status' => 'submitted',
        ]);

        DB::table('implementations')->insert([
            'lgu_user_id' => $target->id,
            'status' => 'not yet started',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'user_id' => $target->id,
            'action' => 'synthetic.lifecycle.preservation',
            'entity_type' => User::class,
            'entity_id' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ib39_surfaced_former_rebels')->insert([
            'reference_number' => 'LIFECYCLE-PRESERVATION',
            'first_name' => 'Synthetic',
            'last_name' => 'Subject',
            'category' => 'Regular Member',
            'province' => 'Davao del Sur',
            'municipality_id' => $municipality->id,
            'surfaced_at' => '2026-09-15',
            'possessed_firearms' => false,
            'created_by' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tables = [
            'users',
            'chat_conversations',
            'chat_messages',
            'rcsp_activities',
            'rcsp_forms',
            'implementations',
            'audit_logs',
            'ib39_surfaced_former_rebels',
        ];
        $before = collect($tables)->mapWithKeys(
            fn (string $table): array => [$table => $this->tableSnapshot($table)]
        )->all();

        $this->actingAs($superAdmin)
            ->delete("/super-admin/users/{$target->id}")
            ->assertStatus(405);

        foreach ($tables as $table) {
            $this->assertSame($before[$table], $this->tableSnapshot($table), "{$table} changed after a forged delete request.");
        }
    }

    public function test_super_administrator_can_deactivate_and_reactivate_an_ordinary_account(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create(['is_active' => true]);

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, ['is_active' => '0']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertFalse($target->refresh()->is_active);

        $this->put(route('super_admin.users.update', $target), $this->updatePayload($target, ['is_active' => '1']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertTrue($target->refresh()->is_active);
    }

    public function test_deactivated_account_is_denied_on_its_next_request(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create();

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, ['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->actingAs($target->refresh())
            ->get('/profile')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_final_active_super_administrator_cannot_deactivate_itself(): void
    {
        $target = User::factory()->role('super_admin')->create();
        $before = $target->only(['username', 'name', 'role', 'is_active']);

        $this->actingAs($target)
            ->from(route('super_admin.users.index'))
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
                'name' => 'Rejected Name',
                'is_active' => '0',
            ]))
            ->assertRedirect(route('super_admin.users.index'))
            ->assertSessionHasErrors('account_lifecycle');

        $this->assertSame($before, $target->refresh()->only(array_keys($before)));
    }

    public function test_final_active_super_administrator_cannot_be_deactivated_by_another_super_administrator(): void
    {
        $target = User::factory()->role('super_admin')->create();
        $otherSuperAdmin = User::factory()->role('super_admin')->create();

        $this->actingAs($otherSuperAdmin);
        User::query()->whereKey($otherSuperAdmin->id)->update(['is_active' => false]);

        $this->put(route('super_admin.users.update', $target), $this->updatePayload($target, ['is_active' => '0']))
            ->assertSessionHasErrors('account_lifecycle');

        $this->assertTrue($target->refresh()->is_active);
        $this->assertSame('super_admin', $target->role);
    }

    public function test_final_active_super_administrator_cannot_change_its_role(): void
    {
        $target = User::factory()->role('super_admin')->create();
        $before = $target->only(['username', 'name', 'role', 'is_active']);

        $this->actingAs($target)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
                'name' => 'Rejected Demotion',
                'role' => 'admin',
            ]))
            ->assertSessionHasErrors('account_lifecycle');

        $this->assertSame($before, $target->refresh()->only(array_keys($before)));
    }

    public function test_one_of_two_active_super_administrators_can_be_deactivated(): void
    {
        $actor = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('super_admin')->create();

        $this->actingAs($actor)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, ['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($target->refresh()->is_active);
        $this->assertTrue($actor->refresh()->is_active);
    }

    public function test_one_of_two_active_super_administrators_can_be_demoted_to_a_supported_role(): void
    {
        $actor = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('super_admin')->create();

        $this->actingAs($actor)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, ['role' => 'admin']))
            ->assertSessionHasNoErrors();

        $this->assertSame('admin', $target->refresh()->role);
        $this->assertTrue($target->is_active);
    }

    public function test_guest_non_super_administrator_and_inactive_super_administrator_cannot_update_lifecycle(): void
    {
        $target = User::factory()->role('admin')->create();
        $payload = $this->updatePayload($target, ['is_active' => '0']);

        $this->put(route('super_admin.users.update', $target), $payload)
            ->assertRedirect(route('login'));

        $ordinary = User::factory()->role('admin')->create();
        $this->actingAs($ordinary)
            ->put(route('super_admin.users.update', $target), $payload)
            ->assertForbidden();

        $inactiveSuperAdmin = User::factory()->role('super_admin')->create(['is_active' => false]);
        $this->actingAs($inactiveSuperAdmin)
            ->put(route('super_admin.users.update', $target), $payload)
            ->assertRedirect(route('login'));

        $this->assertTrue($target->refresh()->is_active);
    }

    public function test_missing_or_invalid_active_state_cannot_alter_other_fields(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create(['name' => 'Original Name']);

        $missing = $this->updatePayload($target, ['name' => 'Missing State']);
        unset($missing['is_active']);
        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $target), $missing)
            ->assertSessionHasErrors('is_active');
        $this->assertSame('Original Name', $target->refresh()->name);

        $this->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
            'name' => 'Invalid State',
            'is_active' => 'not-a-boolean',
        ]))->assertSessionHasErrors('is_active');
        $this->assertSame('Original Name', $target->refresh()->name);
        $this->assertTrue($target->is_active);
    }

    public function test_unrelated_client_attributes_cannot_be_mass_assigned(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create([
            'email' => 'original@example.test',
            'remember_token' => 'original-token',
        ]);

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
                'email' => 'forged@example.test',
                'remember_token' => 'forged-token',
                'created_at' => '2000-01-01 00:00:00',
            ]))
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('original@example.test', $target->email);
        $this->assertSame('original-token', $target->remember_token);
        $this->assertNotSame('2000-01-01 00:00:00', $target->created_at?->format('Y-m-d H:i:s'));
    }

    public function test_update_route_retains_authentication_and_super_administrator_role_middleware(): void
    {
        $route = Route::getRoutes()->getByName('super_admin.users.update');

        $this->assertNotNull($route);
        $this->assertSame(['PUT'], $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('role:super_admin', $route->gatherMiddleware());
    }

    public function test_user_management_interface_has_status_controls_and_no_deletion_interface(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create(['name' => 'Active Lifecycle Admin']);
        User::factory()->role('admin')->create(['name' => 'Inactive Lifecycle User', 'is_active' => false]);

        $this->actingAs($superAdmin)
            ->get(route('super_admin.users.index'))
            ->assertOk()
            ->assertSee('Account Status')
            ->assertSee('Active')
            ->assertSee('Inactive')
            ->assertSee('Activate an account to allow sign-in.')
            ->assertSee('Deactivate it to block sign-in while preserving its records.')
            ->assertSee('data-active="1"', false)
            ->assertSee('data-active="0"', false)
            ->assertDontSee('Delete User')
            ->assertDontSee('Confirm User Deletion')
            ->assertDontSee('mdi-delete');
    }

    public function test_lifecycle_business_rule_error_is_rendered_on_the_user_management_page(): void
    {
        $target = User::factory()->role('super_admin')->create();

        $this->actingAs($target)
            ->from(route('super_admin.users.index'))
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, ['is_active' => '0']))
            ->assertRedirect(route('super_admin.users.index'))
            ->assertSessionHasErrors('account_lifecycle');

        $this->get(route('super_admin.users.index'))
            ->assertOk()
            ->assertSee('At least one active Super Administrator account must remain.');
    }

    /** @return array<string, mixed> */
    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'username' => $user->username,
            'name' => $user->name,
            'password' => '',
            'role' => $user->role,
            'is_active' => $user->is_active ? '1' : '0',
            'municipality_id' => $user->municipality_id,
            'gov_agency_id' => $user->gov_agency_id,
        ], $overrides);
    }

    /** @return array<int, array<string, mixed>> */
    private function tableSnapshot(string $table): array
    {
        return DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
}
