<?php

namespace Tests\Feature;

use App\Models\GovAgency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminAgencyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const DEPENDENCY_ERROR = 'Agency cannot be deleted while linked accounts or implementation records exist.';

    private ?string $isolatedPublicPath = null;

    private ?string $originalPublicPath = null;

    protected function tearDown(): void
    {
        if ($this->originalPublicPath !== null) {
            $this->app->usePublicPath($this->originalPublicPath);
        }

        if ($this->isolatedPublicPath !== null) {
            File::deleteDirectory($this->isolatedPublicPath);
        }

        parent::tearDown();
    }

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

    public function test_agency_form_lists_only_valid_top_level_logo_images_and_supports_uploads(): void
    {
        $directory = $this->useIsolatedAgencyLogoDirectory();
        $this->placeLogo($directory, 'existing-agency.png');
        File::copy($directory.'/existing-agency.png', $directory.'/.hidden-agency.png');
        File::copy($directory.'/existing-agency.png', $directory.'/executable.php');
        File::put($directory.'/disguised.png', '<?php echo "not an image";');
        File::put($directory.'/notes.txt', 'not an image');
        File::ensureDirectoryExists($directory.'/nested.png');

        $this->actingAs($this->superAdmin())
            ->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="profile_upload"', false)
            ->assertSee('name="profile"', false)
            ->assertSee('data-agency-logo-picker', false)
            ->assertSee('data-logo="existing-agency.png"', false)
            ->assertSee('src="'.asset('assets/logoAgency/existing-agency.png').'"', false)
            ->assertDontSee('<select name="profile"', false)
            ->assertDontSee('.hidden-agency.png')
            ->assertDontSee('executable.php')
            ->assertDontSee('disguised.png')
            ->assertDontSee('notes.txt')
            ->assertDontSee('nested.png');
    }

    public function test_existing_agency_logo_selection_persists_renders_and_rejects_forged_paths(): void
    {
        $directory = $this->useIsolatedAgencyLogoDirectory();
        $this->placeLogo($directory, 'selectable-agency.png');
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('super_admin.agencies.store'), [
                'name' => 'Selected Logo Agency',
                'acronym' => 'SLA',
                'profile' => 'selectable-agency.png',
            ])
            ->assertSessionHasNoErrors();

        $agency = GovAgency::query()->where('acronym', 'SLA')->sole();
        $this->assertSame('selectable-agency.png', $agency->profile);

        $this->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSee('src="'.asset('assets/logoAgency/selectable-agency.png').'"', false)
            ->assertSee('data-profile="selectable-agency.png"', false);

        $this->post(route('super_admin.agencies.store'), [
            'name' => 'Forged Logo Agency',
            'acronym' => 'FLA',
            'profile' => '../selectable-agency.png',
        ])->assertSessionHasErrors('profile');

        $this->assertDatabaseMissing('gov_agencies', ['acronym' => 'FLA']);
    }

    public function test_agency_logo_upload_uses_a_safe_unique_filename_and_rejects_invalid_or_ambiguous_files(): void
    {
        $directory = $this->useIsolatedAgencyLogoDirectory();
        $this->placeLogo($directory, 'existing-agency.png');
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('super_admin.agencies.store'), [
                'name' => 'Uploaded Logo Agency',
                'acronym' => 'ULA',
                'profile_upload' => UploadedFile::fake()->image('shared agency logo.png'),
            ])
            ->assertSessionHasNoErrors();

        $first = GovAgency::query()->where('acronym', 'ULA')->sole();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $first->profile);
        $this->assertFileExists($directory.'/'.$first->profile);

        $this->post(route('super_admin.agencies.store'), [
            'name' => 'Second Uploaded Logo Agency',
            'acronym' => 'SULA',
            'profile_upload' => UploadedFile::fake()->image('shared agency logo.png'),
        ])->assertSessionHasNoErrors();

        $second = GovAgency::query()->where('acronym', 'SULA')->sole();
        $this->assertNotSame($first->profile, $second->profile);
        $this->assertFileExists($directory.'/'.$second->profile);

        $this->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSee('data-logo="'.$first->profile.'"', false)
            ->assertSee('src="'.asset('assets/logoAgency/'.$first->profile).'"', false);

        $this->post(route('super_admin.agencies.store'), [
            'name' => 'Invalid Upload Agency',
            'acronym' => 'IUA',
            'profile_upload' => UploadedFile::fake()
                ->createWithContent('payload.php', '<?php echo "not an image";')
                ->mimeType('application/x-php'),
        ])->assertSessionHasErrors('profile_upload');

        $beforeAmbiguousSubmission = count(File::files($directory));
        $this->post(route('super_admin.agencies.store'), [
            'name' => 'Ambiguous Logo Agency',
            'acronym' => 'ALA',
            'profile' => 'existing-agency.png',
            'profile_upload' => UploadedFile::fake()->image('ambiguous.png'),
        ])->assertSessionHasErrors(['profile', 'profile_upload']);

        $this->assertSame($beforeAmbiguousSubmission, count(File::files($directory)));
        $this->assertDatabaseMissing('gov_agencies', ['acronym' => 'IUA']);
        $this->assertDatabaseMissing('gov_agencies', ['acronym' => 'ALA']);
    }

    public function test_edit_preserves_displays_and_replaces_the_current_agency_logo(): void
    {
        $directory = $this->useIsolatedAgencyLogoDirectory();
        $this->placeLogo($directory, 'current-agency.png');
        $this->placeLogo($directory, 'replacement-agency.png');
        $agency = GovAgency::create([
            'name' => 'Editable Logo Agency',
            'acronym' => 'ELA',
            'profile' => 'current-agency.png',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSee('data-profile="current-agency.png"', false)
            ->assertSee('src="'.asset('assets/logoAgency/current-agency.png').'"', false);

        $this->put(route('super_admin.agencies.update', $agency), [
            'name' => 'Editable Logo Agency Renamed',
            'acronym' => 'ELA',
        ])->assertSessionHasNoErrors();
        $this->assertSame('current-agency.png', $agency->refresh()->profile);

        $this->put(route('super_admin.agencies.update', $agency), [
            'name' => $agency->name,
            'acronym' => $agency->acronym,
            'profile' => 'replacement-agency.png',
        ])->assertSessionHasNoErrors();
        $this->assertSame('replacement-agency.png', $agency->refresh()->profile);

        $this->put(route('super_admin.agencies.update', $agency), [
            'name' => $agency->name,
            'acronym' => $agency->acronym,
            'profile_upload' => UploadedFile::fake()->image('uploaded-replacement.jpg'),
        ])->assertSessionHasNoErrors();

        $uploadedProfile = $agency->refresh()->profile;
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.jpg$/', $uploadedProfile);
        $this->assertFileExists($directory.'/'.$uploadedProfile);
        $this->assertFileExists($directory.'/current-agency.png');
        $this->assertFileExists($directory.'/replacement-agency.png');

        $this->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSee('data-profile="'.$uploadedProfile.'"', false)
            ->assertSee('src="'.asset('assets/logoAgency/'.$uploadedProfile).'"', false);
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

    private function useIsolatedAgencyLogoDirectory(): string
    {
        $this->withoutVite();
        $this->originalPublicPath = $this->app->publicPath();
        $this->isolatedPublicPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'allshield-agency-logos-'.Str::uuid();
        $directory = $this->isolatedPublicPath.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'logoAgency';
        File::ensureDirectoryExists($directory);
        $this->app->usePublicPath($this->isolatedPublicPath);

        return $directory;
    }

    private function placeLogo(string $directory, string $filename): void
    {
        UploadedFile::fake()->image($filename)->move($directory, $filename);
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
