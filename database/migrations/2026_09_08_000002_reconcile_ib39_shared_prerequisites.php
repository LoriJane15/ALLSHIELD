<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MARKER = 'ib39_shared_prerequisite_reconciliation';

    public function up(): void
    {
        $createdActive = ! Schema::hasColumn('users', 'is_active');
        $createdAudit = ! Schema::hasTable('audit_logs');

        if (! $createdActive && ! $createdAudit) {
            return;
        }

        if ($createdActive) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->index();
            });
        }

        if ($createdAudit) {
            Schema::create('audit_logs', function (Blueprint $table): void {
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
                $table->index(['entity_type', 'entity_id']);
                $table->index(['user_id', 'created_at']);
            });
        }

        Schema::create(self::MARKER, function (Blueprint $table): void {
            $table->boolean('created_users_is_active');
            $table->boolean('created_audit_logs');
        });

        DB::table(self::MARKER)->insert([
            'created_users_is_active' => $createdActive,
            'created_audit_logs' => $createdAudit,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::MARKER)) {
            return;
        }

        $marker = DB::table(self::MARKER)->first();
        if (! $marker) {
            throw new RuntimeException('RCSP/39th IB reconciliation rollback refused: ownership marker is missing.');
        }

        if ($marker->created_audit_logs && DB::table('audit_logs')->exists()) {
            throw new RuntimeException('39th IB reconciliation rollback refused: audit logs would be lost.');
        }

        if ($marker->created_users_is_active && DB::table('users')->where('is_active', false)->exists()) {
            throw new RuntimeException('39th IB reconciliation rollback refused: inactive-account state would be lost.');
        }

        if ($marker->created_audit_logs && Schema::hasTable('audit_logs')) {
            Schema::drop('audit_logs');
        }

        if ($marker->created_users_is_active && Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }

        Schema::drop(self::MARKER);
    }
};
