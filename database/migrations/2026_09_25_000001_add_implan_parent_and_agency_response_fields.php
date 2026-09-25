<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->allowSubmittedImplementationStatus();

        Schema::create('implementation_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lgu_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->enum('status', ['not yet started', 'submitted', 'ongoing', 'verified', 'for verification'])
                ->default('not yet started');
            $table->date('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('implementations', function (Blueprint $table) {
            $table->foreignId('implementation_plan_id')->nullable()->after('id')
                ->constrained('implementation_plans')->cascadeOnDelete();
        });

        // Existing rows are historical LGU baseline rows. Give each one a parent
        // without attempting to infer or manufacture multi-row groupings.
        DB::table('implementations')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $planId = DB::table('implementation_plans')->insertGetId([
                    'lgu_user_id' => $row->lgu_user_id,
                    'title' => 'IMPLAN #'.$row->id,
                    'status' => $row->status,
                    'submitted_at' => $row->uploaded_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                DB::table('implementations')->where('id', $row->id)->update([
                    'implementation_plan_id' => $planId,
                ]);
            }
        });

        Schema::table('agency_implan_responses', function (Blueprint $table) {
            $table->text('program')->nullable();
            $table->text('beneficiaries')->nullable();
            $table->text('outcome')->nullable();
            $table->text('resources')->nullable();
            $table->text('support')->nullable();
            $table->text('duration')->nullable();
            $table->string('type_gov')->nullable();
            $table->string('sources')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('remarks')->nullable();
        });

        Schema::table('implementation_files', function (Blueprint $table) {
            $table->foreignId('agency_implan_response_id')->nullable()
                ->constrained('agency_implan_responses')->cascadeOnDelete();
        });

        Schema::table('implementation_photos', function (Blueprint $table) {
            $table->foreignId('agency_implan_response_id')->nullable()
                ->constrained('agency_implan_responses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('implementation_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_implan_response_id');
        });

        Schema::table('implementation_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_implan_response_id');
        });

        Schema::table('agency_implan_responses', function (Blueprint $table) {
            $table->dropColumn([
                'program', 'beneficiaries', 'outcome', 'resources', 'support',
                'duration', 'type_gov', 'sources', 'action_taken', 'remarks',
            ]);
        });

        Schema::table('implementations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('implementation_plan_id');
        });

        Schema::dropIfExists('implementation_plans');
    }

    /**
     * Expand the existing row status constraint without rewriting historical
     * values. PostgreSQL is the production path; SQLite fresh tests already
     * receive this value from the original table migration.
     */
    private function allowSubmittedImplementationStatus(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE implementations MODIFY status ENUM('not yet started', 'submitted', 'ongoing', 'verified', 'for verification') NOT NULL DEFAULT 'not yet started'");

            return;
        }

        if ($driver !== 'pgsql') {
            throw new RuntimeException("Unsupported database driver [{$driver}] for IMPLAN status expansion.");
        }

        $constraints = DB::select(<<<'SQL'
            SELECT constraint_row.conname, pg_get_constraintdef(constraint_row.oid) AS definition
            FROM pg_constraint AS constraint_row
            INNER JOIN pg_class AS table_row ON table_row.oid = constraint_row.conrelid
            INNER JOIN pg_namespace AS schema_row ON schema_row.oid = table_row.relnamespace
            WHERE schema_row.nspname = current_schema()
              AND table_row.relname = 'implementations'
              AND constraint_row.contype = 'c'
            SQL);

        $statusConstraints = array_filter(
            $constraints,
            fn (object $constraint): bool => preg_match('/\bstatus\b/i', $constraint->definition) === 1
        );

        if (count($statusConstraints) !== 1) {
            throw new RuntimeException('Expected exactly one PostgreSQL check constraint for implementations.status.');
        }

        $constraintName = reset($statusConstraints)->conname;
        $quotedConstraint = '"'.str_replace('"', '""', $constraintName).'"';

        DB::statement("ALTER TABLE implementations DROP CONSTRAINT {$quotedConstraint}");
        DB::statement(<<<'SQL'
            ALTER TABLE implementations
            ADD CONSTRAINT implementations_status_check
            CHECK (status IN ('not yet started', 'submitted', 'ongoing', 'verified', 'for verification'))
            NOT VALID
            SQL);
        DB::statement('ALTER TABLE implementations VALIDATE CONSTRAINT implementations_status_check');
    }
};
