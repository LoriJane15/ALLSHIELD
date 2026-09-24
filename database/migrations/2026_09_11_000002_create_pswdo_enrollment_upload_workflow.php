<?php

use App\Enums\Ib39CdrStatus;
use App\Enums\JapicCertificationStatus;
use App\Enums\PswdoEnrollmentDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENROLLMENTS = 'pswdo_enrollments';

    private const DOCUMENTS = 'pswdo_enrollment_documents';

    public function up(): void
    {
        if (! DB::connection()->pretending()
            && (! Schema::hasTable('japic_certification_processings')
            || ! Schema::hasTable('japic_certification_document_versions'))) {
            if (Schema::hasTable('migrations') && DB::table('migrations')
                ->where('migration', '2026_09_09_000001_create_or_reconcile_japic_certification_workflow')->exists()) {
                throw new RuntimeException('PSWDO migration refused: the applied JAPIC prerequisite schema is missing.');
            }

            return;
        }
        $this->preflight();

        DB::transaction(function (): void {
            $this->changeRole(true);
            $this->createTables();
            $this->createImmutabilityGuards();
            $this->backfillEligibleEnrollments();
        });
    }

    public function down(): void
    {
        if ((Schema::hasTable(self::DOCUMENTS) && DB::table(self::DOCUMENTS)->exists())
            || (Schema::hasTable(self::ENROLLMENTS) && DB::table(self::ENROLLMENTS)->exists())) {
            throw new RuntimeException('PSWDO migration rollback refused: enrollment records would be lost.');
        }
        if (Schema::hasTable('users') && DB::table('users')->where('role', 'pswdo')->exists()) {
            throw new RuntimeException('PSWDO migration rollback refused: PSWDO user roles would be lost.');
        }

        DB::transaction(function (): void {
            $this->dropImmutabilityGuards();
            Schema::dropIfExists(self::DOCUMENTS);
            Schema::dropIfExists(self::ENROLLMENTS);
            $this->changeRole(false);
        });
    }

    private function preflight(): void
    {
        foreach ([
            self::ENROLLMENTS => ['id', 'ib39_surfaced_former_rebel_id', 'lock_version', 'created_at', 'updated_at'],
            self::DOCUMENTS => ['id', 'pswdo_enrollment_id', 'document_type', 'storage_path', 'original_filename', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by', 'correct_document_type_confirmed', 'belongs_to_fr_confirmed', 'final_signed_confirmed', 'uploaded_at', 'created_at', 'updated_at'],
        ] as $table => $columns) {
            if (Schema::hasTable($table) && array_diff($columns, Schema::getColumnListing($table)) !== []) {
                throw new RuntimeException("PSWDO migration refused: incompatible table {$table} requires manual reconciliation.");
            }
        }
    }

    private function createTables(): void
    {
        if (! Schema::hasTable(self::ENROLLMENTS)) {
            Schema::create(self::ENROLLMENTS, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ib39_surfaced_former_rebel_id')->unique('pswdo_enrollment_fr_unique')
                    ->constrained('ib39_surfaced_former_rebels')->restrictOnDelete();
                $table->unsignedInteger('lock_version')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable(self::DOCUMENTS)) {
            Schema::create(self::DOCUMENTS, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pswdo_enrollment_id')->constrained(self::ENROLLMENTS)->restrictOnDelete();
                $table->enum('document_type', array_column(PswdoEnrollmentDocumentType::cases(), 'value'));
                $table->string('storage_path', 500);
                $table->string('original_filename');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size_bytes');
                $table->string('sha256', 64);
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->boolean('correct_document_type_confirmed');
                $table->boolean('belongs_to_fr_confirmed');
                $table->boolean('final_signed_confirmed');
                $table->timestamp('uploaded_at');
                $table->timestamps();
                $table->unique(['pswdo_enrollment_id', 'document_type'], 'pswdo_document_type_unique');
                $table->unique(['pswdo_enrollment_id', 'sha256'], 'pswdo_document_hash_unique');
            });
        }
    }

    private function backfillEligibleEnrollments(): void
    {
        if (! Schema::hasTable('ib39_cdr_processings') || ! Schema::hasTable('japic_certification_processings')) {
            return;
        }
        $now = now();
        $ids = DB::table('ib39_surfaced_former_rebels as fr')
            ->join('ib39_cdr_processings as cdr', 'cdr.ib39_surfaced_former_rebel_id', '=', 'fr.id')
            ->join('ib39_cdr_document_versions as cdr_final', function ($join): void {
                $join->on('cdr_final.id', '=', 'cdr.current_final_version_id')
                    ->on('cdr_final.cdr_processing_id', '=', 'cdr.id');
            })
            ->join('japic_certification_processings as japic', 'japic.ib39_surfaced_former_rebel_id', '=', 'fr.id')
            ->join('japic_certification_document_versions as japic_final', function ($join): void {
                $join->on('japic_final.id', '=', 'japic.current_final_version_id')
                    ->on('japic_final.processing_id', '=', 'japic.id');
            })
            ->leftJoin('ib39_fr_cancellations as cancellation', 'cancellation.ib39_surfaced_former_rebel_id', '=', 'fr.id')
            ->whereNull('fr.deleted_at')->whereNull('cancellation.id')
            ->where('cdr.status', Ib39CdrStatus::Completed->value)
            ->where('japic.status', JapicCertificationStatus::Completed->value)
            ->pluck('fr.id');

        foreach ($ids->chunk(500) as $chunk) {
            DB::table(self::ENROLLMENTS)->insertOrIgnore($chunk->map(fn ($id): array => [
                'ib39_surfaced_former_rebel_id' => $id,
                'lock_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    private function createImmutabilityGuards(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS pswdo_documents_no_update BEFORE UPDATE ON '.self::DOCUMENTS." BEGIN SELECT RAISE(ABORT, 'PSWDO final documents are immutable'); END");
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS pswdo_documents_no_delete BEFORE DELETE ON '.self::DOCUMENTS." BEGIN SELECT RAISE(ABORT, 'PSWDO final documents are immutable'); END");
        }
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['pswdo_documents_no_update' => 'UPDATE', 'pswdo_documents_no_delete' => 'DELETE'] as $name => $event) {
                $exists = DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', $name)->exists();
                if (! $exists) {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON ".self::DOCUMENTS." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PSWDO final documents are immutable'");
                }
            }
        }
    }

    private function dropImmutabilityGuards(): void
    {
        foreach (['pswdo_documents_no_update', 'pswdo_documents_no_delete'] as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
        }
    }

    private function changeRole(bool $add): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }
        if (DB::getDriverName() === 'sqlite') {
            $definition = (string) DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->value('sql');
            $pattern = '/check\s*\(\s*["`]?role["`]?\s+in\s*\(([^)]*)\)\s*\)/i';
            if (! preg_match($pattern, $definition, $match)) {
                if (preg_match('/check\s*\([^)]*(?:["`]?role["`]?|\brole\b)/i', $definition)) {
                    throw new RuntimeException('PSWDO migration refused: users.role has an unrecognized restrictive SQLite constraint.');
                }

                return;
            }
            preg_match_all("/'((?:''|[^'])*)'/", $match[1], $values);
            $roles = array_map(fn (string $role): string => str_replace("''", "'", $role), $values[1]);
            if ($roles === []) {
                throw new RuntimeException('PSWDO migration refused: the SQLite users.role constraint could not be parsed.');
            }
            $roles = $add ? array_values(array_unique([...$roles, 'pswdo'])) : array_values(array_filter($roles, fn ($role): bool => $role !== 'pswdo'));
            $replacement = 'check ("role" in ('.implode(', ', array_map(fn ($role): string => DB::getPdo()->quote($role), $roles)).'))';
            $changed = preg_replace($pattern, $replacement, $definition, 1, $count);
            if ($count !== 1 || ! is_string($changed)) {
                throw new RuntimeException('PSWDO migration refused: the SQLite users.role constraint could not be changed safely.');
            }
            $version = (int) DB::selectOne('PRAGMA schema_version')->schema_version;
            DB::unprepared('PRAGMA writable_schema = ON');
            DB::table('sqlite_master')->where('type', 'table')->where('name', 'users')->update(['sql' => $changed]);
            DB::unprepared('PRAGMA writable_schema = OFF');
            DB::unprepared('PRAGMA schema_version = '.($version + 1));

            return;
        }
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $type = (string) DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'users')->where('COLUMN_NAME', 'role')->value('COLUMN_TYPE');
            if (! preg_match('/^enum\((.*)\)$/i', $type, $match)) {
                return;
            }
            preg_match_all("/'((?:''|[^'])*)'/", $match[1], $values);
            $roles = array_map(fn (string $role): string => str_replace("''", "'", $role), $values[1]);
            $roles = $add ? array_values(array_unique([...$roles, 'pswdo'])) : array_values(array_filter($roles, fn ($role): bool => $role !== 'pswdo'));
            DB::statement("ALTER TABLE users MODIFY role ENUM('".implode("','", array_map(fn ($role): string => str_replace("'", "''", $role), $roles))."') NOT NULL");
        }
    }
};
