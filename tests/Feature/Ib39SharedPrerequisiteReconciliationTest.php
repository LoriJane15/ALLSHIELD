<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class Ib39SharedPrerequisiteReconciliationTest extends TestCase
{
    private string $originalConnection;

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'ib39-reconciliation-');
        config(['database.connections.ib39_reconciliation' => [
            'driver' => 'sqlite', 'database' => $this->databaseFile, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ]]);
        DB::setDefaultConnection('ib39_reconciliation');
        DB::purge('ib39_reconciliation');
    }

    protected function tearDown(): void
    {
        DB::disconnect('ib39_reconciliation');
        DB::setDefaultConnection($this->originalConnection);
        if (is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }
        parent::tearDown();
    }

    public function test_forward_rollback_forward_owns_only_missing_prerequisites(): void
    {
        $this->createUsersWithoutActiveFlag();
        $migration = $this->migration();

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasTable('ib39_shared_prerequisite_reconciliation'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'is_active'));
        $this->assertFalse(Schema::hasTable('audit_logs'));
        $this->assertFalse(Schema::hasTable('ib39_shared_prerequisite_reconciliation'));

        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
    }

    public function test_forward_is_a_no_op_when_both_shared_prerequisites_exist(): void
    {
        $this->createUsersWithoutActiveFlag();
        Schema::table('users', fn ($table) => $table->boolean('is_active')->default(true)->index());
        $this->createAuditLogs();
        $before = Schema::getColumnListing('users');

        $this->migration()->up();

        $this->assertSame($before, Schema::getColumnListing('users'));
        $this->assertFalse(Schema::hasTable('ib39_shared_prerequisite_reconciliation'));
    }

    public function test_rollback_refuses_before_losing_populated_new_data(): void
    {
        $this->createUsersWithoutActiveFlag();
        $migration = $this->migration();
        $migration->up();
        DB::table('audit_logs')->insert([
            'action' => 'test', 'entity_type' => 'test', 'entity_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $migration->down();
            $this->fail('Rollback accepted populated audit data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('audit logs would be lost', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasTable('ib39_shared_prerequisite_reconciliation'));
    }

    private function createUsersWithoutActiveFlag(): void
    {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('password');
            $table->timestamps();
        });
    }

    private function createAuditLogs(): void
    {
        Schema::create('audit_logs', function ($table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 120);
            $table->unsignedBigInteger('entity_id');
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_08_000002_reconcile_ib39_shared_prerequisites.php');
    }
}
