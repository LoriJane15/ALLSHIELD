<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\User;
use App\Notifications\JapicCertificationIntakeNotification;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\JapicCertificationIntakeNotifier;
use App\Services\JapicCertificationIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JapicIntakeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifier_targets_only_active_japic_users_with_safe_idempotent_payload(): void
    {
        $active = User::factory()->role('japic')->create();
        User::factory()->role('japic')->create(['is_active' => false]);
        User::factory()->role('admin')->create();
        $processing = $this->eligibleProcessing();
        $notifier = app(JapicCertificationIntakeNotifier::class);
        $notifier->notify($processing);
        $notifier->notify($processing);

        $this->assertDatabaseCount('notifications', 1);
        $row = DB::table('notifications')->sole();
        $this->assertSame($active->id, $row->notifiable_id);
        $this->assertSame(JapicCertificationIntakeNotification::class, $row->type);
        $this->assertSame(36, strlen($row->id));
        foreach (['Secure Intake', 'private', 'control_number', 'rcsp', 'cancellation'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, strtolower($row->data));
        }
    }

    public function test_new_intake_schedules_after_commit_once_and_zero_recipients_succeeds(): void
    {
        [$cdr] = $this->eligibleCdr();
        $service = app(JapicCertificationIntakeService::class);
        $first = $service->createForCompletedCdr($cdr);
        $second = $service->createForCompletedCdr($cdr->fresh());
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('japic_certification_processings', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notifications_migration_accepts_exact_live_schema_rows_and_additional_indexes(): void
    {
        $migration = require database_path('migrations/2026_09_09_000003_ensure_database_notifications_table_exists.php');
        $before = DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql');
        $migration->up();
        $this->assertSame($before, DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql'));

        $this->createLiveNotificationsTable();
        $row = $this->syntheticNotification();
        DB::table('notifications')->insert($row);
        DB::statement('CREATE INDEX notifications_harmless_probe_index ON notifications(type)');
        $schemaBefore = DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql');
        $outsideBefore = $this->nonNotificationFingerprint();
        $migration->up();
        $migration->up();

        $this->assertSame($schemaBefore, DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql'));
        $this->assertSame($row, (array) DB::table('notifications')->sole());
        $this->assertSame(['notifications_harmless_probe_index', 'notifications_notifiable_type_notifiable_id_index'],
            DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'notifications')->whereNotNull('sql')->orderBy('name')->pluck('name')->all());
        $this->assertSame($outsideBefore, $this->nonNotificationFingerprint());
        $migration->down();
        $this->assertTrue(Schema::hasTable('notifications'));
    }

    public function test_notifications_migration_reconciles_empty_wrong_index_and_missing_primary_key(): void
    {
        $migration = require database_path('migrations/2026_09_09_000003_ensure_database_notifications_table_exists.php');

        $this->createLiveNotificationsTable(indexColumns: ['type', 'notifiable_id']);
        $migration->up();
        $this->assertCanonicalNotificationsTable();

        $this->createLiveNotificationsTable(primary: false);
        $migration->up();
        $this->assertCanonicalNotificationsTable();

        $this->createLiveNotificationsTable(includeData: false);
        $migration->up();
        $this->assertCanonicalNotificationsTable();
    }

    public function test_notifications_migration_refuses_populated_incompatible_tables_before_mutation(): void
    {
        foreach ([
            fn () => $this->createLiveNotificationsTable(indexColumns: ['type', 'notifiable_id']),
            fn () => $this->createLiveNotificationsTable(primary: false),
            fn () => $this->createLiveNotificationsTable(includeData: false),
        ] as $create) {
            $create();
            DB::table('notifications')->insert($this->syntheticNotification(includeData: Schema::hasColumn('notifications', 'data')));
            $before = DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql');

            try {
                (require database_path('migrations/2026_09_09_000003_ensure_database_notifications_table_exists.php'))->up();
                $this->fail('Populated incompatible notifications table was accepted.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('populated notifications table', $exception->getMessage());
            }

            $this->assertSame($before, DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql'));
            $this->assertSame(1, DB::table('notifications')->count());
        }
    }

    public function test_notifications_migration_accepts_safe_sqlite_text_declarations(): void
    {
        $migration = require database_path('migrations/2026_09_09_000003_ensure_database_notifications_table_exists.php');
        $this->createLiveNotificationsTable(idType: 'text', stringType: 'text');
        $row = $this->syntheticNotification();
        DB::table('notifications')->insert($row);
        $before = DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql');

        $migration->up();

        $this->assertSame($before, DB::table('sqlite_master')->where('type', 'table')->where('name', 'notifications')->value('sql'));
        $this->assertSame($row, (array) DB::table('notifications')->sole());
    }

    public function test_notifications_migration_creates_missing_table_and_is_idempotent(): void
    {
        $migration = require database_path('migrations/2026_09_09_000003_ensure_database_notifications_table_exists.php');
        Schema::drop('notifications');
        $migration->up();
        $migration->up();
        $this->assertCanonicalNotificationsTable();
        $migration->down();
        $this->assertTrue(Schema::hasTable('notifications'));
    }

    private function createLiveNotificationsTable(
        string $idType = 'varchar',
        string $stringType = 'varchar',
        bool $primary = true,
        bool $includeData = true,
        array $indexColumns = ['notifiable_type', 'notifiable_id'],
    ): void {
        Schema::dropIfExists('notifications');
        $data = $includeData ? ', "data" text not null' : '';
        $primarySql = $primary ? ', primary key ("id")' : '';
        DB::statement("CREATE TABLE \"notifications\" (\"id\" {$idType} not null, \"type\" {$stringType} not null, \"notifiable_type\" {$stringType} not null, \"notifiable_id\" integer not null{$data}, \"read_at\" datetime, \"created_at\" datetime, \"updated_at\" datetime{$primarySql})");
        DB::statement('CREATE INDEX notifications_notifiable_type_notifiable_id_index ON notifications ('.implode(', ', $indexColumns).')');
    }

    private function syntheticNotification(bool $includeData = true): array
    {
        return array_filter([
            'id' => '00000000-0000-4000-8000-000000000003',
            'type' => 'SyntheticProbe',
            'notifiable_type' => 'Synthetic\\Probe',
            'notifiable_id' => 2147483647,
            'data' => $includeData ? '{}' : null,
            'read_at' => null,
            'created_at' => '2000-01-01 00:00:00',
            'updated_at' => '2000-01-01 00:00:00',
        ], fn ($value, $key) => $key !== 'data' || $includeData, ARRAY_FILTER_USE_BOTH);
    }

    private function assertCanonicalNotificationsTable(): void
    {
        $columns = collect(Schema::getColumns('notifications'))->keyBy('name');
        $indexes = collect(Schema::getIndexes('notifications'));
        $this->assertSame(['created_at', 'data', 'id', 'notifiable_id', 'notifiable_type', 'read_at', 'type', 'updated_at'], $columns->keys()->sort()->values()->all());
        $this->assertFalse($columns['id']['nullable']);
        $this->assertTrue($indexes->contains(fn (array $index) => $index['primary'] && $index['unique'] && $index['columns'] === ['id']));
        $this->assertTrue($indexes->contains(fn (array $index) => ! $index['primary'] && ! $index['unique'] && $index['columns'] === ['notifiable_type', 'notifiable_id']));
    }

    private function nonNotificationFingerprint(): string
    {
        $schema = DB::table('sqlite_master')->whereNotIn('tbl_name', ['notifications', 'migrations'])->orderBy('type')->orderBy('name')->get(['type', 'name', 'tbl_name', 'sql']);
        $counts = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT IN ('notifications', 'migrations') ORDER BY name"))
            ->mapWithKeys(fn ($table) => [$table->name => DB::table($table->name)->count()]);

        return hash('sha256', serialize([$schema->toArray(), $counts->all()]));
    }

    private function eligibleProcessing()
    {
        [$cdr] = $this->eligibleCdr();

        return app(JapicCertificationIntakeService::class)->createForCompletedCdr($cdr);
    }

    private function eligibleCdr(): array
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Notify '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create(['first_name' => 'Secure', 'last_name' => 'Intake',
            'category' => Ib39FrCategory::RegularMember->value, 'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality, 'surfaced_at' => now()->toDateString(), 'possessed_firearms' => false], $actor);
        $cdr = $record->cdrProcessing;
        $version = DB::table('ib39_cdr_document_versions')->insertGetId(['cdr_processing_id' => $cdr->id, 'version_number' => 1,
            'source_type' => 'generated', 'storage_path' => 'private/generated.pdf', 'original_filename' => 'final.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('f', 64), 'created_by' => $actor->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'current_final_version_id' => $version]);

        return [$cdr, $record];
    }
}
