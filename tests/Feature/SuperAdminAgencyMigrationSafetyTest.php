<?php

namespace Tests\Feature;

use App\Models\GovAgency;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SuperAdminAgencyMigrationSafetyTest extends TestCase
{
    private string $originalConnection;

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        $file = tempnam(sys_get_temp_dir(), 'agency-b2-');
        if ($file === false) {
            throw new RuntimeException('Unable to create the disposable agency migration database.');
        }
        $this->databaseFile = $file;

        config(['database.connections.agency_migration_safety' => [
            'driver' => 'sqlite',
            'database' => $this->databaseFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ]]);
        DB::setDefaultConnection('agency_migration_safety');
        DB::purge('agency_migration_safety');

        $this->migrateBaseline();
    }

    protected function tearDown(): void
    {
        DB::disconnect('agency_migration_safety');
        DB::setDefaultConnection($this->originalConnection);

        if (isset($this->databaseFile) && is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }

        parent::tearDown();
    }

    public function test_forward_migration_preserves_all_rows_columns_keys_indexes_and_triggers(): void
    {
        $this->insertRepresentativeRows();
        $before = $this->completeSnapshot();

        $this->migration()->up();

        foreach (['gov_agencies', 'users', 'agency_implan_responses', 'implementation_taggings'] as $table) {
            $this->assertSame($before['rows'][$table], $this->rows($table, $before['column_names'][$table]));
        }

        foreach (['users', 'agency_implan_responses', 'implementation_taggings'] as $table) {
            $this->assertSame($before['columns'][$table], $this->columns($table));
            $this->assertSame($before['objects'][$table], $this->objects($table));
        }

        $baseAgencyColumns = collect($this->columns('gov_agencies'))
            ->reject(fn (array $column): bool => in_array($column['name'], ['name_canonical', 'acronym_canonical'], true))
            ->values()->all();
        $this->assertSame($before['columns']['gov_agencies'], $baseAgencyColumns);
        $this->assertSame($before['objects']['gov_agencies'], collect($this->objects('gov_agencies'))
            ->reject(fn (array $object): bool => in_array($object['name'], [
                'gov_agencies_name_canonical_unique',
                'gov_agencies_acronym_canonical_unique',
            ], true))->values()->all());

        $this->assertAgencyDeleteAction('users', 'RESTRICT');
        $this->assertAgencyDeleteAction('agency_implan_responses', 'RESTRICT');
        $this->assertAgencyDeleteAction('implementation_taggings', 'RESTRICT');
        $this->assertUnrelatedForeignKeysUnchanged($before['foreign_keys']);
        $this->assertSame($before['sequences'], $this->sequences());
        $this->assertSame('ok', DB::scalar('PRAGMA quick_check'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(0, DB::table('sqlite_master')->where('type', 'table')->where('name', 'like', '__b2_%')->count());
    }

    public function test_database_restricts_each_dependency_and_allows_unreferenced_deletion_and_null_assignment(): void
    {
        $this->insertDeletionFixtures();
        $this->migration()->up();

        foreach ([101, 102, 103] as $agencyId) {
            $this->assertQueryRejected(fn () => DB::table('gov_agencies')->where('id', $agencyId)->delete());
            $this->assertDatabaseHas('gov_agencies', ['id' => $agencyId]);
        }

        $this->assertSame(1, DB::table('users')->where('gov_agency_id', 101)->count());
        $this->assertSame(1, DB::table('agency_implan_responses')->where('gov_agency_id', 102)->count());
        $this->assertSame(1, DB::table('implementation_taggings')->where('gov_agency_id', 103)->count());
        $this->assertSame(1, DB::table('gov_agencies')->where('id', 104)->delete());
        $this->assertDatabaseMissing('gov_agencies', ['id' => 104]);

        DB::table('users')->insert($this->userRow(109, 'nullable-agency', 'admin', null));
        $this->assertDatabaseHas('users', ['id' => 109, 'gov_agency_id' => null]);
    }

    public function test_database_rejects_trimmed_case_insensitive_name_and_acronym_collisions(): void
    {
        $this->insertAgency(1, 'Department of Testing', 'DOT');
        $this->insertAgency(2, 'Another Department', 'AD');
        $this->migration()->up();

        $this->assertQueryRejected(fn () => $this->insertAgency(3, '  department of testing  ', 'UNIQUE'));
        $this->assertQueryRejected(fn () => $this->insertAgency(4, 'Unique Department', '  dot  '));
        $this->assertQueryRejected(fn () => DB::table('gov_agencies')->where('id', 2)->update([
            'name' => 'DEPARTMENT OF TESTING',
        ]));
        $this->assertQueryRejected(fn () => DB::table('gov_agencies')->where('id', 2)->update([
            'acronym' => ' Dot ',
        ]));

        $this->assertSame('Another Department', DB::table('gov_agencies')->where('id', 2)->value('name'));
        $this->assertSame('AD', DB::table('gov_agencies')->where('id', 2)->value('acronym'));
        $this->assertSame(2, DB::table('gov_agencies')->count());
    }

    public function test_duplicate_name_precondition_fails_before_schema_or_row_changes(): void
    {
        $this->insertAgency(1, 'Duplicate Name', 'ONE');
        $this->insertAgency(2, ' duplicate name ', 'TWO');

        $this->assertPreflightRefusal('duplicate canonical agency names');
    }

    public function test_duplicate_acronym_precondition_fails_before_schema_or_row_changes(): void
    {
        $this->insertAgency(1, 'First Agency', 'DUP');
        $this->insertAgency(2, 'Second Agency', ' dup ');

        $this->assertPreflightRefusal('duplicate canonical agency acronyms');
    }

    public function test_blank_and_untrimmed_identity_preconditions_fail_closed(): void
    {
        $this->insertAgency(1, 'Valid Name', '');
        $this->assertPreflightRefusal('blank agency acronyms');

        $this->resetBaseline();
        $this->insertAgency(1, ' Valid Name ', 'VALID');
        $this->assertPreflightRefusal('untrimmed agency names');
    }

    public function test_dangling_user_reference_precondition_fails_before_schema_or_row_changes(): void
    {
        DB::table('users')->insert($this->userRow(1, 'dangling-user', 'gov_agency', 999));

        $this->assertPreflightRefusal('dangling user agency references');
    }

    public function test_dangling_response_reference_precondition_fails_before_schema_or_row_changes(): void
    {
        $this->insertImplementationFixture();
        $this->withoutForeignKeys(function (): void {
            DB::table('agency_implan_responses')->insert([
                'id' => 1,
                'gov_agency_id' => 999,
                'implementation_id' => 1,
                'response_status' => 'pending',
                'created_at' => '2026-01-02 03:04:05',
                'updated_at' => '2026-01-02 03:04:05',
            ]);
        });

        $this->assertPreflightRefusal('dangling response agency references');
    }

    public function test_dangling_tagging_reference_precondition_fails_before_schema_or_row_changes(): void
    {
        $this->insertImplementationFixture();
        $this->withoutForeignKeys(function (): void {
            DB::table('implementation_taggings')->insert([
                'id' => 1,
                'implementation_id' => 1,
                'gov_agency_id' => 999,
                'status' => 'Pending',
                'created_at' => '2026-01-02 03:04:05',
                'updated_at' => '2026-01-02 03:04:05',
            ]);
        });

        $this->assertPreflightRefusal('dangling tagging agency references');
    }

    public function test_forward_result_is_deterministic_for_the_same_synthetic_baseline(): void
    {
        $this->insertRepresentativeRows();
        $this->migration()->up();
        $first = $this->postMigrationSchemaSnapshot();

        $secondFile = tempnam(sys_get_temp_dir(), 'agency-b2-repeat-');
        if ($secondFile === false) {
            $this->fail('Unable to create the second disposable migration database.');
        }

        try {
            config(['database.connections.agency_migration_repeat' => [
                'driver' => 'sqlite',
                'database' => $secondFile,
                'prefix' => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => 5000,
            ]]);
            DB::setDefaultConnection('agency_migration_repeat');
            DB::purge('agency_migration_repeat');
            $this->migrateBaseline();
            $this->insertRepresentativeRows();
            $this->migration()->up();

            $this->assertSame($first, $this->postMigrationSchemaSnapshot());
        } finally {
            DB::disconnect('agency_migration_repeat');
            DB::setDefaultConnection('agency_migration_safety');
            if (is_file($secondFile)) {
                unlink($secondFile);
            }
        }
    }

    public function test_rollback_refuses_without_weakening_the_forward_schema(): void
    {
        $this->insertRepresentativeRows();
        $migration = $this->migration();
        $migration->up();
        $before = $this->postMigrationSchemaSnapshot();

        try {
            $migration->down();
            $this->fail('The migration accepted an integrity-weakening rollback.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rollback refused', strtolower($exception->getMessage()));
            $this->assertStringContainsString('corrective forward migration', strtolower($exception->getMessage()));
        }

        $this->assertSame($before, $this->postMigrationSchemaSnapshot());
        $this->assertAgencyDeleteAction('users', 'RESTRICT');
        $this->assertAgencyDeleteAction('agency_implan_responses', 'RESTRICT');
        $this->assertAgencyDeleteAction('implementation_taggings', 'RESTRICT');
    }

    public function test_controller_uses_matching_canonical_rules_for_create_and_update(): void
    {
        $this->migration()->up();
        $superAdmin = User::factory()->role('super_admin')->create();
        $first = GovAgency::create(['name' => 'Canonical Agency', 'acronym' => 'CAN']);
        $second = GovAgency::create(['name' => 'Second Agency', 'acronym' => 'SECOND']);

        $this->actingAs($superAdmin)
            ->post(route('super_admin.agencies.store'), [
                'name' => ' canonical agency ',
                'acronym' => 'NEW',
                'profile' => null,
            ])->assertSessionHasErrors('name')->assertSessionMissing('success');

        $this->post(route('super_admin.agencies.store'), [
            'name' => 'New Agency',
            'acronym' => ' can ',
            'profile' => null,
        ])->assertSessionHasErrors('acronym')->assertSessionMissing('success');

        $this->put(route('super_admin.agencies.update', $first), [
            'name' => ' CANONICAL AGENCY ',
            'acronym' => ' can ',
            'profile' => null,
        ])->assertSessionHasNoErrors();
        $this->assertSame('CANONICAL AGENCY', $first->refresh()->name);
        $this->assertSame('can', $first->acronym);

        $this->put(route('super_admin.agencies.update', $second), [
            'name' => 'canonical agency',
            'acronym' => 'SECOND',
            'profile' => null,
        ])->assertSessionHasErrors('name');
        $this->assertSame('Second Agency', $second->refresh()->name);

        $this->put(route('super_admin.agencies.update', $second), [
            'name' => 'Second Agency',
            'acronym' => ' CAN ',
            'profile' => null,
        ])->assertSessionHasErrors('acronym');
        $this->assertSame('SECOND', $second->refresh()->acronym);
    }

    public function test_controller_trims_identifiers_and_translates_database_uniqueness_races(): void
    {
        $this->migration()->up();
        $superAdmin = User::factory()->role('super_admin')->create();

        $this->actingAs($superAdmin)
            ->post(route('super_admin.agencies.store'), [
                'name' => '  Trimmed Agency  ',
                'acronym' => '  TRIM  ',
                'profile' => 'trim.png',
            ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gov_agencies', [
            'name' => 'Trimmed Agency',
            'acronym' => 'TRIM',
            'profile' => 'trim.png',
        ]);

        DB::statement(<<<'SQL'
            CREATE TRIGGER b2_name_race BEFORE INSERT ON gov_agencies
            WHEN NEW.name = 'Race Agency'
            BEGIN
                SELECT RAISE(ABORT, 'gov_agencies_name_canonical_unique');
            END
        SQL);
        $this->post(route('super_admin.agencies.store'), [
            'name' => 'Race Agency',
            'acronym' => 'RACE',
            'profile' => null,
        ])->assertSessionHasErrors('name')->assertSessionMissing('success');
        $this->assertDatabaseMissing('gov_agencies', ['name' => 'Race Agency']);

        $target = GovAgency::query()->where('acronym', 'TRIM')->sole();
        DB::statement(<<<'SQL'
            CREATE TRIGGER b2_acronym_race BEFORE UPDATE ON gov_agencies
            WHEN NEW.acronym = 'UPDATE-RACE'
            BEGIN
                SELECT RAISE(ABORT, 'gov_agencies_acronym_canonical_unique');
            END
        SQL);
        $this->put(route('super_admin.agencies.update', $target), [
            'name' => 'Trimmed Agency',
            'acronym' => 'UPDATE-RACE',
            'profile' => 'changed.png',
        ])->assertSessionHasErrors('acronym')->assertSessionMissing('success');
        $this->assertDatabaseHas('gov_agencies', [
            'id' => $target->id,
            'acronym' => 'TRIM',
            'profile' => 'trim.png',
        ]);
    }

    public function test_interface_guidance_and_isolation_are_explicit(): void
    {
        $this->migration()->up();
        $superAdmin = User::factory()->role('super_admin')->create();

        $this->actingAs($superAdmin)
            ->get(route('super_admin.agencies.index'))
            ->assertOk()
            ->assertSeeText('Must be unique regardless of capitalization or surrounding spaces.')
            ->assertSee('name="name"', false)
            ->assertSee('name="acronym"', false)
            ->assertSee('name="profile"', false)
            ->assertDontSee('name="name_canonical"', false)
            ->assertDontSee('name="acronym_canonical"', false);

        $workspace = str_replace('\\', '/', realpath(base_path()) ?: base_path());
        $database = str_replace('\\', '/', realpath($this->databaseFile) ?: $this->databaseFile);
        $this->assertStringNotContainsString($workspace.'/', $database);
        $this->assertNotSame(realpath(database_path('database.sqlite')), realpath($this->databaseFile));
    }

    public function test_migration_source_has_no_business_record_rewrite_or_destructive_target_cascade(): void
    {
        $source = file_get_contents(database_path('migrations/2026_09_15_000001_harden_government_agency_integrity.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString('cascadeOnDelete', $source);
        $this->assertDoesNotMatchRegularExpression('/DB::table\([^)]*\)\s*->[^;]*(?:update|delete)\s*\(/s', $source);
        $this->assertDoesNotMatchRegularExpression('/UPDATE\s+gov_agencies|DELETE\s+FROM\s+(?:gov_agencies|users|agency_implan_responses|implementation_taggings)/i', $source);
        $this->assertStringContainsString('on delete restrict', strtolower($source));
        $this->assertStringContainsString('rollback refused', strtolower($source));
    }

    private function migrateBaseline(): void
    {
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2025_01_01_000010_create_location_tables.php',
            '2025_01_01_000040_create_rcsp_tables.php',
            '2025_01_01_000050_create_implementation_tables.php',
            '2026_09_08_000001_harden_rcsp_workflow.php',
            '2026_09_08_000002_reconcile_ib39_shared_prerequisites.php',
            '2026_09_12_000001_create_private_chat_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Schema::create('chat_conversation_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('chat_messages')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['chat_conversation_id', 'user_id'], 'chat_reads_conversation_user_unique');
        });
    }

    private function resetBaseline(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach (DB::table('sqlite_master')->where('type', 'table')->where('name', 'not like', 'sqlite_%')->pluck('name') as $table) {
            DB::statement('DROP TABLE "'.str_replace('"', '""', $table).'"');
        }
        DB::statement('PRAGMA foreign_keys = ON');
        $this->migrateBaseline();
    }

    private function insertRepresentativeRows(): void
    {
        $this->insertAgency(10, 'First Synthetic Agency', 'FSA', 'first.png');
        $this->insertAgency(20, 'Second Synthetic Agency', 'SSA');
        $this->insertAgency(30, 'Unreferenced Synthetic Agency', 'USA');
        DB::statement('CREATE INDEX gov_agencies_profile_index ON gov_agencies (profile)');

        DB::table('users')->insert([
            $this->userRow(1, 'synthetic-super-admin', 'super_admin', null),
            $this->userRow(2, 'synthetic-agency-user', 'gov_agency', 10, false),
            $this->userRow(3, 'synthetic-lgu-user', 'lgu', null),
        ]);
        DB::table('implementations')->insert([
            'id' => 1,
            'lgu_user_id' => 3,
            'status' => 'not yet started',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-02 03:04:05',
        ]);
        DB::table('agency_implan_responses')->insert([
            'id' => 100,
            'gov_agency_id' => 10,
            'implementation_id' => 1,
            'response_status' => 'rejected',
            'rejection_reason' => 'Synthetic response reason',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-02 03:04:05',
        ]);
        DB::table('implementation_taggings')->insert([
            'id' => 200,
            'implementation_id' => 1,
            'gov_agency_id' => 20,
            'status' => 'Rejected',
            'reason' => 'Synthetic tagging reason',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-02 03:04:05',
        ]);
        DB::statement('CREATE INDEX implementation_taggings_status_index ON implementation_taggings (status)');
    }

    private function insertDeletionFixtures(): void
    {
        foreach ([101, 102, 103, 104] as $id) {
            $this->insertAgency($id, "Deletion Agency {$id}", "DA{$id}");
        }
        DB::table('users')->insert([
            $this->userRow(1, 'delete-lgu-user', 'lgu', null),
            $this->userRow(2, 'delete-agency-user', 'gov_agency', 101, false),
        ]);
        DB::table('implementations')->insert([
            'id' => 1,
            'lgu_user_id' => 1,
            'status' => 'not yet started',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-02 03:04:05',
        ]);
        DB::table('agency_implan_responses')->insert([
            'id' => 1,
            'gov_agency_id' => 102,
            'implementation_id' => 1,
            'response_status' => 'pending',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-02 03:04:05',
        ]);
        DB::table('implementation_taggings')->insert([
            'id' => 1,
            'implementation_id' => 1,
            'gov_agency_id' => 103,
            'status' => 'Pending',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-02 03:04:05',
        ]);
    }

    private function insertImplementationFixture(): void
    {
        DB::table('users')->insert($this->userRow(1, 'synthetic-lgu', 'lgu', null));
        DB::table('implementations')->insert([
            'id' => 1,
            'lgu_user_id' => 1,
            'status' => 'not yet started',
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-01-02 03:04:05',
        ]);
    }

    private function insertAgency(int $id, string $name, string $acronym, ?string $profile = null): void
    {
        DB::table('gov_agencies')->insert([
            'id' => $id,
            'name' => $name,
            'acronym' => $acronym,
            'profile' => $profile,
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ]);
    }

    /** @return array<string, mixed> */
    private function userRow(int $id, string $username, string $role, ?int $agencyId, bool $active = true): array
    {
        return [
            'id' => $id,
            'username' => $username,
            'name' => 'Synthetic User '.$id,
            'email' => null,
            'password' => 'not-a-real-login-hash',
            'role' => $role,
            'gov_agency_id' => $agencyId,
            'is_active' => $active,
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ];
    }

    private function withoutForeignKeys(Closure $operation): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            $operation();
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
        $this->assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
    }

    private function assertPreflightRefusal(string $message): void
    {
        $before = $this->completeSnapshot();

        try {
            $this->migration()->up();
            $this->fail('The migration accepted incompatible agency data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, strtolower($exception->getMessage()));
        }

        $this->assertSame($before, $this->completeSnapshot());
        $this->assertFalse(Schema::hasColumn('gov_agencies', 'name_canonical'));
        $this->assertFalse(Schema::hasColumn('gov_agencies', 'acronym_canonical'));
        $this->assertSame(0, DB::table('sqlite_master')->where('type', 'table')->where('name', 'like', '__b2_%')->count());
    }

    private function assertQueryRejected(Closure $operation): void
    {
        try {
            $operation();
            $this->fail('The database accepted an operation forbidden by agency integrity constraints.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $before */
    private function assertUnrelatedForeignKeysUnchanged(array $before): void
    {
        foreach (['agency_implan_responses', 'implementation_taggings'] as $table) {
            $original = collect($before[$table])->reject(fn (array $key): bool => $key['from'] === 'gov_agency_id')->values()->all();
            $current = collect($this->foreignKeys($table))->reject(fn (array $key): bool => $key['from'] === 'gov_agency_id')->values()->all();
            $this->assertSame($original, $current);
        }
    }

    private function assertAgencyDeleteAction(string $table, string $expected): void
    {
        $keys = collect($this->foreignKeys($table))
            ->where('from', 'gov_agency_id')->where('table', 'gov_agencies')->values();
        $this->assertCount(1, $keys);
        $this->assertSame($expected, $keys->first()['on_delete']);
    }

    /** @return array<string, mixed> */
    private function completeSnapshot(): array
    {
        $snapshot = [
            'rows' => [],
            'column_names' => [],
            'columns' => [],
            'objects' => [],
            'foreign_keys' => [],
            'sequences' => $this->sequences(),
        ];
        foreach (['gov_agencies', 'users', 'agency_implan_responses', 'implementation_taggings'] as $table) {
            $columns = $this->columns($table);
            $names = array_column(array_filter($columns, fn (array $column): bool => $column['hidden'] === 0), 'name');
            $snapshot['column_names'][$table] = $names;
            $snapshot['columns'][$table] = $columns;
            $snapshot['rows'][$table] = $this->rows($table, $names);
            $snapshot['objects'][$table] = $this->objects($table);
            $snapshot['foreign_keys'][$table] = $this->foreignKeys($table);
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function postMigrationSchemaSnapshot(): array
    {
        $snapshot = [];
        foreach (['gov_agencies', 'users', 'agency_implan_responses', 'implementation_taggings'] as $table) {
            $snapshot[$table] = [
                'columns' => $this->columns($table),
                'objects' => $this->objects($table),
                'foreign_keys' => $this->foreignKeys($table),
            ];
        }

        return $snapshot;
    }

    /** @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, array $columns): array
    {
        return DB::table($table)->select($columns)->orderBy('id')->get()
            ->map(fn (object $row): array => (array) $row)->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function columns(string $table): array
    {
        return array_map(fn (object $column): array => [
            'cid' => (int) $column->cid,
            'name' => $column->name,
            'type' => mb_strtolower($column->type),
            'notnull' => (int) $column->notnull,
            'default' => $column->dflt_value,
            'pk' => (int) $column->pk,
            'hidden' => (int) $column->hidden,
        ], DB::select('PRAGMA table_xinfo("'.$table.'")'));
    }

    /** @return array<int, array<string, mixed>> */
    private function objects(string $table): array
    {
        return DB::table('sqlite_master')->where('tbl_name', $table)
            ->whereIn('type', ['index', 'trigger'])->whereNotNull('sql')
            ->orderBy('type')->orderBy('name')->get(['type', 'name', 'sql'])
            ->map(fn (object $object): array => (array) $object)->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function foreignKeys(string $table): array
    {
        $keys = array_map(fn (object $key): array => [
            'from' => $key->from,
            'table' => $key->table,
            'to' => $key->to,
            'on_update' => $key->on_update,
            'on_delete' => $key->on_delete,
            'match' => $key->match,
        ], DB::select('PRAGMA foreign_key_list("'.$table.'")'));
        usort($keys, fn (array $left, array $right): int => [$left['from'], $left['table']] <=> [$right['from'], $right['table']]);

        return $keys;
    }

    /** @return array<string, int> */
    private function sequences(): array
    {
        return DB::table('sqlite_sequence')->whereIn('name', [
            'gov_agencies', 'users', 'agency_implan_responses', 'implementation_taggings',
        ])->orderBy('name')->pluck('seq', 'name')->map(fn (mixed $value): int => (int) $value)->all();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_15_000001_harden_government_agency_integrity.php');
    }
}
