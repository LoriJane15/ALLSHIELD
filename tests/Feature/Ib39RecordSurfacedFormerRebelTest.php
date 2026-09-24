<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Ib39ReferenceSequence;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Ib39RecordSurfacedFormerRebelTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = Municipality::query()->create(['name' => 'Stage Three Municipality']);
        $this->barangay = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Stage Three Barangay',
        ]);
    }

    public function test_guest_is_redirected_to_login_from_create_and_store_routes(): void
    {
        $this->get(route('ib39.fr-profiles.create'))->assertRedirect(route('login'));
        $this->post(route('ib39.fr-profiles.store'), $this->validPayload())->assertRedirect(route('login'));
    }

    public function test_active_39th_ib_user_can_access_approved_name_form(): void
    {
        $response = $this->actingAs($this->ib39User())
            ->get(route('ib39.fr-profiles.create'));

        $response->assertOk()
            ->assertSee('Generated automatically after saving')
            ->assertSee('Davao del Sur')
            ->assertSee($this->municipality->name)
            ->assertSee($this->barangay->name)
            ->assertSee('name="first_name"', false)
            ->assertSee('name="last_name"', false)
            ->assertSee('data-surfaced-fr-form', false);

        foreach ($this->protectedFields() as $field) {
            $response->assertDontSee('name="'.$field.'"', false);
        }
    }

    public function test_inactive_39th_ib_user_fails_policy_and_is_removed_by_active_account_middleware(): void
    {
        $user = User::factory()->role('39th_ib')->create(['is_active' => false]);

        $this->assertFalse($user->can('create', Ib39SurfacedFormerRebel::class));
        $this->actingAs($user)
            ->get(route('ib39.fr-profiles.create'))
            ->assertRedirect(route('login'));
    }

    public function test_every_non_39th_ib_role_receives_403_for_create_and_store(): void
    {
        $roles = ['super_admin', 'admin', 'lgu', 'gov_agency', 'mblrc', 'afp'];

        foreach ($roles as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get(route('ib39.fr-profiles.create'))->assertForbidden();
            $this->actingAs($user)->post(route('ib39.fr-profiles.store'), $this->validPayload())->assertForbidden();
        }

        $this->assertDatabaseCount('ib39_surfaced_former_rebels', 0);
    }

    public function test_successful_creation_generates_reference_creator_audit_and_redirects_to_dashboard(): void
    {
        $actor = $this->ib39User();

        $response = $this->actingAs($actor)
            ->post(route('ib39.fr-profiles.store'), $this->validPayload());

        $record = Ib39SurfacedFormerRebel::query()->sole();
        $response->assertRedirect(route('ib39.dashboard'))
            ->assertSessionHas('success', 'Surfaced FR FR001 was recorded successfully.');
        $this->assertSame('FR001', $record->reference_number);
        $this->assertSame('Test First Test Last', $record->display_name);
        $this->assertSame($actor->id, $record->created_by);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'ib39_surfaced_former_rebel_recorded',
            'entity_type' => Ib39SurfacedFormerRebel::class,
            'entity_id' => $record->id,
        ]);
        $this->assertSame('FR001', AuditLog::query()->sole()->new_values['reference_number']);
    }

    public function test_submitted_server_owned_unapproved_identity_and_forwarding_fields_are_rejected(): void
    {
        $payload = $this->validPayload();
        foreach ($this->protectedFields() as $field) {
            $payload[$field] = 'prohibited-value';
        }

        $this->actingAs($this->ib39User())
            ->post(route('ib39.fr-profiles.store'), $payload)
            ->assertSessionHasErrors($this->protectedFields());

        $this->assertDatabaseCount('ib39_surfaced_former_rebels', 0);
    }

    public function test_first_and_last_names_are_required_trimmed_and_limited_to_100_characters(): void
    {
        $this->postAsIb39(['first_name' => '', 'last_name' => ''])
            ->assertSessionHasErrors(['first_name', 'last_name']);

        $this->postAsIb39([
            'first_name' => str_repeat('a', 101),
            'last_name' => str_repeat('b', 101),
        ])->assertSessionHasErrors(['first_name', 'last_name']);

        $this->postAsIb39([
            'first_name' => '  Test First  ',
            'last_name' => '  Test Last  ',
        ])->assertSessionHasNoErrors();

        $record = Ib39SurfacedFormerRebel::query()->sole();
        $this->assertSame('Test First', $record->first_name);
        $this->assertSame('Test Last', $record->last_name);
        $this->assertSame('Test First Test Last', $record->display_name);
    }

    public function test_category_must_use_allow_list_and_other_requires_specification(): void
    {
        $this->postAsIb39(['category' => 'Invented Category'])
            ->assertSessionHasErrors('category');

        $this->postAsIb39([
            'category' => Ib39FrCategory::Other->value,
            'other_category_specification' => '',
        ])->assertSessionHasErrors('other_category_specification');
    }

    public function test_non_other_category_normalizes_other_specification_to_null(): void
    {
        $this->postAsIb39([
            'category' => Ib39FrCategory::RegularMember->value,
            'other_category_specification' => 'Must be removed',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Ib39SurfacedFormerRebel::query()->sole()->other_category_specification);
    }

    public function test_province_is_required_and_restricted_to_davao_del_sur(): void
    {
        $this->postAsIb39(['province' => ''])
            ->assertSessionHasErrors('province');

        $this->postAsIb39(['province' => 'Another Province'])
            ->assertSessionHasErrors('province');
    }

    public function test_municipality_is_required_and_barangay_must_belong_to_it(): void
    {
        $this->postAsIb39(['municipality_id' => ''])
            ->assertSessionHasErrors('municipality_id');

        $otherMunicipality = Municipality::query()->create(['name' => 'Other Municipality']);
        $this->postAsIb39(['municipality_id' => $otherMunicipality->id])
            ->assertSessionHasErrors('barangay_id');
    }

    public function test_empty_optional_barangay_is_normalized_to_null(): void
    {
        $this->postAsIb39(['barangay_id' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Ib39SurfacedFormerRebel::query()->sole()->barangay_id);
    }

    public function test_future_surfacing_date_is_rejected(): void
    {
        $this->postAsIb39(['surfaced_at' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('surfaced_at');
    }

    public function test_firearms_indicator_is_required_and_boolean(): void
    {
        $payload = $this->validPayload();
        unset($payload['possessed_firearms']);

        $this->actingAs($this->ib39User())
            ->post(route('ib39.fr-profiles.store'), $payload)
            ->assertSessionHasErrors('possessed_firearms');

        $this->postAsIb39(['possessed_firearms' => 'invalid'])
            ->assertSessionHasErrors('possessed_firearms');
    }

    public function test_initial_remarks_maximum_length_is_enforced(): void
    {
        $this->postAsIb39(['initial_remarks' => str_repeat('x', 5001)])
            ->assertSessionHasErrors('initial_remarks');
    }

    public function test_audit_failure_rolls_back_record_and_sequence_increment(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_ib39_audit_insert
            BEFORE INSERT ON audit_logs
            WHEN NEW.action = 'ib39_surfaced_former_rebel_recorded'
            BEGIN
                SELECT RAISE(ABORT, 'simulated audit failure');
            END;
        SQL);

        try {
            app(Ib39SurfacedFormerRebelService::class)->create(
                $this->validPayload(),
                $this->ib39User(),
            );
            $this->fail('The simulated audit failure did not abort creation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated audit failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('ib39_surfaced_former_rebels', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(0, Ib39ReferenceSequence::query()->firstOrFail()->current_value);
    }

    public function test_existing_39th_ib_routes_remain_available(): void
    {
        $actor = $this->ib39User();

        foreach (['ib39.dashboard', 'ib39.areas.index', 'ib39.map'] as $routeName) {
            $this->actingAs($actor)->get(route($routeName))->assertSuccessful();
        }

        $this->actingAs($actor)->get(route('ib39.area.data'))->assertSuccessful();
        $this->actingAs($actor)->get(route('ib39.barangays', [
            'municipality_id' => $this->municipality->id,
        ]))->assertSuccessful();
        $this->actingAs($actor)->get(route('ib39.barangay.data', [
            'barangay_id' => $this->barangay->id,
        ]))->assertSuccessful();

        $this->assertFalse(Route::has('ib39.map.data'));
    }

    private function postAsIb39(array $overrides = [])
    {
        return $this->actingAs($this->ib39User())
            ->post(route('ib39.fr-profiles.store'), [
                ...$this->validPayload(),
                ...$overrides,
            ]);
    }

    private function validPayload(): array
    {
        return [
            'first_name' => 'Test First',
            'last_name' => 'Test Last',
            'category' => Ib39FrCategory::MilisyaNgBayan->value,
            'other_category_specification' => null,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->barangay->id,
            'specific_location' => 'Authorized receiving location',
            'surfaced_at' => today()->toDateString(),
            'possessed_firearms' => '0',
            'initial_remarks' => 'Initial operational remarks.',
        ];
    }

    private function ib39User(): User
    {
        return User::factory()->role('39th_ib')->create();
    }

    private function protectedFields(): array
    {
        return [
            'reference_number', 'created_by', 'forwarded', 'forwarding_organization',
            'other_organization_specification', 'firstname', 'middlename', 'lastname',
            'real_name', 'alias', 'nickname', 'identity_document_number',
            'former_rebel_id',
        ];
    }
}
