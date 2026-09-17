<?php

namespace Tests\Feature;

use App\Models\GovAgency;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const SUPPORTED_ROLES = [
        'super_admin',
        'admin',
        '39th_ib',
        'gov_agency',
        'lgu',
        'mblrc',
        'afp',
        'pswdo',
        'japic',
    ];

    public function test_super_administrator_can_create_a_japic_account_without_irrelevant_scope(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $municipality = Municipality::create(['name' => 'JAPIC Irrelevant Municipality']);
        $agency = GovAgency::create(['name' => 'JAPIC Irrelevant Agency', 'acronym' => 'JIA']);

        $this->actingAs($superAdmin)
            ->post(route('super_admin.users.store'), $this->storePayload('japic', [
                'username' => 'japic-provisioned',
                'municipality_id' => $municipality->id,
                'gov_agency_id' => $agency->id,
            ]))
            ->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'japic-provisioned')->sole();
        $this->assertSame('japic', $user->role);
        $this->assertNull($user->municipality_id);
        $this->assertNull($user->gov_agency_id);
    }

    public function test_super_administrator_can_update_an_existing_account_to_japic_when_safe(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $municipality = Municipality::create(['name' => 'Former LGU Municipality']);
        $target = User::factory()->lgu($municipality->id)->create();

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
                'role' => 'japic',
            ]))
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('japic', $target->role);
        $this->assertNull($target->municipality_id);
        $this->assertNull($target->gov_agency_id);
    }

    public function test_every_explicitly_supported_role_is_accepted_and_scoped_server_side(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $municipality = Municipality::create(['name' => 'Supported Role Municipality']);
        $agency = GovAgency::create(['name' => 'Supported Role Agency', 'acronym' => 'SRA']);

        foreach (self::SUPPORTED_ROLES as $index => $role) {
            $username = "supported-role-{$index}";
            $this->actingAs($superAdmin)
                ->post(route('super_admin.users.store'), $this->storePayload($role, [
                    'username' => $username,
                    'municipality_id' => $municipality->id,
                    'gov_agency_id' => $agency->id,
                ]))
                ->assertSessionHasNoErrors();

            $created = User::query()->where('username', $username)->sole();
            $this->assertSame($role, $created->role);
            $this->assertSame($role === 'lgu' ? $municipality->id : null, $created->municipality_id);
            $this->assertSame($role === 'gov_agency' ? $agency->id : null, $created->gov_agency_id);
        }
    }

    public function test_unknown_and_legacy_roles_are_rejected_for_create_and_update(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create();

        foreach (['legacy_admin', 'cdr', 'unknown_role'] as $index => $role) {
            $this->actingAs($superAdmin)
                ->post(route('super_admin.users.store'), $this->storePayload($role, [
                    'username' => "rejected-role-{$index}",
                ]))
                ->assertSessionHasErrors('role');
            $this->assertDatabaseMissing('users', ['username' => "rejected-role-{$index}"]);

            $this->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
                'name' => "Rejected role {$index}",
                'role' => $role,
            ]))->assertSessionHasErrors('role');
            $this->assertSame('admin', $target->refresh()->role);
            $this->assertNotSame("Rejected role {$index}", $target->name);
        }
    }

    public function test_store_requires_a_framework_valid_password_and_matching_confirmation(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();

        $missing = $this->storePayload('admin', ['username' => 'missing-password']);
        unset($missing['password'], $missing['password_confirmation']);
        $this->actingAs($superAdmin)
            ->post(route('super_admin.users.store'), $missing)
            ->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['username' => 'missing-password']);

        $this->post(route('super_admin.users.store'), $this->storePayload('admin', [
            'username' => 'mismatched-password',
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['username' => 'mismatched-password']);

        $this->post(route('super_admin.users.store'), $this->storePayload('admin', [
            'username' => 'weak-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['username' => 'weak-password']);
    }

    public function test_update_preserves_an_omitted_password(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create();
        $originalHash = $target->password;
        $payload = $this->updatePayload($target, ['name' => 'Password Preserved']);
        unset($payload['password'], $payload['password_confirmation']);

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $target), $payload)
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('Password Preserved', $target->name);
        $this->assertSame($originalHash, $target->password);
    }

    public function test_update_rejects_an_unconfirmed_password_and_hashes_an_accepted_change(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create();
        $originalHash = $target->password;

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
                'name' => 'Rejected Password Change',
                'password' => 'new-secure-password',
                'password_confirmation' => 'does-not-match',
            ]))
            ->assertSessionHasErrors('password');
        $target->refresh();
        $this->assertSame($originalHash, $target->password);
        $this->assertNotSame('Rejected Password Change', $target->name);

        $this->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
            'password' => 'accepted-secure-password',
            'password_confirmation' => 'accepted-secure-password',
        ]))->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertNotSame('accepted-secure-password', $target->password);
        $this->assertTrue(Hash::check('accepted-secure-password', $target->password));
    }

    public function test_passwords_and_confirmations_are_not_rendered_after_failed_validation(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $password = 'never-render-this-password';
        $confirmation = 'never-render-this-confirmation';

        $this->actingAs($superAdmin)
            ->from(route('super_admin.users.index'))
            ->post(route('super_admin.users.store'), $this->storePayload('admin', [
                'username' => 'secret-response-test',
                'password' => $password,
                'password_confirmation' => $confirmation,
            ]))
            ->assertSessionHasErrors('password');

        $this->get(route('super_admin.users.index'))
            ->assertOk()
            ->assertDontSee($password)
            ->assertDontSee($confirmation)
            ->assertDontSee('name="password" value=', false)
            ->assertDontSee('name="password_confirmation" value=', false);
    }

    public function test_lgu_and_government_agency_roles_require_valid_assignments(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();

        $this->actingAs($superAdmin)
            ->post(route('super_admin.users.store'), $this->storePayload('lgu', [
                'username' => 'lgu-missing-scope',
                'municipality_id' => null,
            ]))
            ->assertSessionHasErrors('municipality_id');
        $this->post(route('super_admin.users.store'), $this->storePayload('lgu', [
            'username' => 'lgu-invalid-scope',
            'municipality_id' => 999999,
        ]))->assertSessionHasErrors('municipality_id');

        $this->post(route('super_admin.users.store'), $this->storePayload('gov_agency', [
            'username' => 'agency-missing-scope',
            'gov_agency_id' => null,
        ]))->assertSessionHasErrors('gov_agency_id');
        $this->post(route('super_admin.users.store'), $this->storePayload('gov_agency', [
            'username' => 'agency-invalid-scope',
            'gov_agency_id' => 999999,
        ]))->assertSessionHasErrors('gov_agency_id');

        foreach (['lgu-missing-scope', 'lgu-invalid-scope', 'agency-missing-scope', 'agency-invalid-scope'] as $username) {
            $this->assertDatabaseMissing('users', ['username' => $username]);
        }
    }

    public function test_changing_away_from_scoped_roles_clears_assignments(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $municipality = Municipality::create(['name' => 'Scope Clearing Municipality']);
        $agency = GovAgency::create(['name' => 'Scope Clearing Agency', 'acronym' => 'SCA']);
        $lgu = User::factory()->lgu($municipality->id)->create();
        $agencyUser = User::factory()->govAgency($agency->id)->create();

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $lgu), $this->updatePayload($lgu, [
                'role' => 'admin',
                'gov_agency_id' => $agency->id,
            ]))
            ->assertSessionHasNoErrors();
        $lgu->refresh();
        $this->assertNull($lgu->municipality_id);
        $this->assertNull($lgu->gov_agency_id);

        $this->put(route('super_admin.users.update', $agencyUser), $this->updatePayload($agencyUser, [
            'role' => 'mblrc',
            'municipality_id' => $municipality->id,
        ]))->assertSessionHasNoErrors();
        $agencyUser->refresh();
        $this->assertNull($agencyUser->municipality_id);
        $this->assertNull($agencyUser->gov_agency_id);
    }

    public function test_text_search_matches_name_or_username_and_role_filter_applies_to_both(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        User::factory()->role('admin')->create([
            'name' => 'Shared Name Match',
            'username' => 'ordinary-admin',
        ]);
        User::factory()->role('admin')->create([
            'name' => 'Username Search Result',
            'username' => 'shared-username-token',
        ]);
        User::factory()->role('lgu')->create([
            'name' => 'Grouped Match Token',
            'username' => 'wrong-role-name-match',
        ]);
        User::factory()->role('admin')->create([
            'name' => 'Correct Filtered Result',
            'username' => 'grouped-match-token',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super_admin.users.index', ['search' => 'shared']))
            ->assertOk()
            ->assertSee('Shared Name Match')
            ->assertSee('Username Search Result');

        $this->get(route('super_admin.users.index', [
            'search' => 'grouped-match-token',
            'role' => 'admin',
        ]))
            ->assertOk()
            ->assertSee('Correct Filtered Result')
            ->assertDontSee('Grouped Match Token');
    }

    public function test_search_and_role_filters_are_preserved_in_pagination_links(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        User::factory()->count(16)->role('admin')->sequence(
            fn ($sequence): array => [
                'name' => 'Paged Provisioning Match '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
            ]
        )->create();

        $this->actingAs($superAdmin)
            ->get(route('super_admin.users.index', [
                'search' => 'Paged Provisioning Match',
                'role' => 'admin',
            ]))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('search=Paged%20Provisioning%20Match', false)
            ->assertSee('role=admin', false);
    }

    public function test_failed_create_validation_reopens_the_add_modal(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $payload = $this->storePayload('admin', ['username' => 'failed-create-modal']);
        unset($payload['password_confirmation']);

        $this->actingAs($superAdmin)
            ->from(route('super_admin.users.index'))
            ->post(route('super_admin.users.store'), $payload)
            ->assertSessionHasErrors('password')
            ->assertSessionMissing('super_admin_edit_user_id');

        $this->get(route('super_admin.users.index'))
            ->assertOk()
            ->assertSee("document.getElementById('addUserModal')", false)
            ->assertDontSee("DOMContentLoaded', () => openEditUserModal({", false);
        $this->assertDatabaseMissing('users', ['username' => 'failed-create-modal']);
    }

    public function test_failed_edit_validation_reopens_the_correct_edit_modal_with_only_safe_input(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $target = User::factory()->role('admin')->create(['name' => 'Correct Edit Target']);
        $other = User::factory()->role('admin')->create(['name' => 'Other Account']);
        $password = 'edit-password-must-not-render';
        $confirmation = 'different-confirmation-must-not-render';

        $this->actingAs($superAdmin)
            ->from(route('super_admin.users.index'))
            ->put(route('super_admin.users.update', $target), $this->updatePayload($target, [
                'username' => $other->username,
                'name' => 'Safe Restored Edit Name',
                'password' => $password,
                'password_confirmation' => $confirmation,
                'user_id' => $other->id,
            ]))
            ->assertSessionHasErrors(['username', 'password'])
            ->assertSessionHas('super_admin_edit_user_id', $target->id);

        $page = $this->get(route('super_admin.users.index'));
        $page->assertOk()
            ->assertSee("DOMContentLoaded', () => openEditUserModal({", false)
            ->assertSee('action: '.json_encode(route('super_admin.users.update', $target)), false)
            ->assertSee('name: '.json_encode('Safe Restored Edit Name'), false)
            ->assertDontSee($password)
            ->assertDontSee($confirmation)
            ->assertDontSee('name="password" value=', false)
            ->assertDontSee('name="password_confirmation" value=', false);

        $this->assertSame('Correct Edit Target', $target->refresh()->name);
        $this->assertSame('Other Account', $other->refresh()->name);
    }

    public function test_non_super_and_inactive_super_administrators_cannot_provision_accounts(): void
    {
        $targetUsername = 'unauthorized-provisioning';
        $ordinary = User::factory()->role('admin')->create();

        $this->actingAs($ordinary)
            ->post(route('super_admin.users.store'), $this->storePayload('admin', ['username' => $targetUsername]))
            ->assertForbidden();

        $inactiveSuperAdmin = User::factory()->role('super_admin')->create(['is_active' => false]);
        $this->actingAs($inactiveSuperAdmin)
            ->post(route('super_admin.users.store'), $this->storePayload('admin', ['username' => $targetUsername]))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['username' => $targetUsername]);
    }

    public function test_phase_a1_activation_and_final_active_super_administrator_guard_remain_intact(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();
        $ordinary = User::factory()->role('admin')->create();

        $this->actingAs($superAdmin)
            ->put(route('super_admin.users.update', $ordinary), $this->updatePayload($ordinary, ['is_active' => '0']))
            ->assertSessionHasNoErrors();
        $this->assertFalse($ordinary->refresh()->is_active);

        $this->put(route('super_admin.users.update', $superAdmin), $this->updatePayload($superAdmin, [
            'role' => 'admin',
        ]))->assertSessionHasErrors('account_lifecycle');
        $superAdmin->refresh();
        $this->assertSame('super_admin', $superAdmin->role);
        $this->assertTrue($superAdmin->is_active);
    }

    public function test_form_guidance_matches_password_and_role_scope_validation(): void
    {
        $superAdmin = User::factory()->role('super_admin')->create();

        $this->actingAs($superAdmin)
            ->get(route('super_admin.users.index'))
            ->assertOk()
            ->assertSee('Confirm Password')
            ->assertSee('The password must meet the system password rule and match its confirmation.')
            ->assertSee('Confirmation is required when changing the password.')
            ->assertSee('A valid municipality is required for LGU accounts.')
            ->assertSee('A valid government agency is required for Government Agency accounts.');
    }

    /** @return array<string, mixed> */
    private function storePayload(string $role, array $overrides = []): array
    {
        return array_merge([
            'username' => 'provision-'.fake()->unique()->numerify('########'),
            'name' => 'Synthetic Provisioning User',
            'password' => 'valid-password',
            'password_confirmation' => 'valid-password',
            'role' => $role,
            'municipality_id' => null,
            'gov_agency_id' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
            'is_active' => $user->is_active ? '1' : '0',
            'municipality_id' => $user->municipality_id,
            'gov_agency_id' => $user->gov_agency_id,
        ], $overrides);
    }
}
