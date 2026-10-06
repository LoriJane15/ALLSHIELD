<?php

use App\Enums\Ib39CdrDocumentSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->preflight();
        $japicSqliteTriggers = $this->sqliteJapicProcessingTriggers();

        Schema::create('ib39_cdr_final_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cdr_processing_id')->unique('ib39_cdr_final_document_processing_unique')
                ->constrained('ib39_cdr_processings')->restrictOnDelete();
            $table->enum('source_type', array_column(Ib39CdrDocumentSource::cases(), 'value'));
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->unsignedInteger('content_schema_version')->nullable();
            $table->text('content_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at');
            $table->timestamps();
        });

        Schema::table('japic_certification_processings', function (Blueprint $table): void {
            $table->foreignId('triggering_cdr_final_document_id')->nullable()
                ->constrained('ib39_cdr_final_documents', indexName: 'japic_processing_cdr_final_foreign')
                ->restrictOnDelete();
        });

        $map = $this->backfillFinalDocuments();
        $this->backfillDependants($map);
        $this->replaceChatReferenceColumn($map);

        DB::table('ib39_cdr_status_histories')
            ->whereIn('event', ['direct_final_uploaded', 'final_document_replaced'])
            ->update(['event' => 'final_document_uploaded']);

        $this->dropLegacyConstraints();
        Schema::table('ib39_cdr_status_histories', fn (Blueprint $table) => $table->dropColumn('document_version_id'));
        Schema::table('ib39_cdr_processings', fn (Blueprint $table) => $table->dropColumn('current_final_version_id'));
        Schema::table('japic_certification_processings', fn (Blueprint $table) => $table->dropColumn('triggering_cdr_document_version_id'));
        DB::table('ib39_cdr_document_versions')->whereNotNull('replaces_version_id')
            ->update(['replaces_version_id' => null]);
        Schema::drop('ib39_cdr_document_versions');
        $this->restoreSqliteGuards();
        $this->restoreSqliteJapicProcessingTriggers($japicSqliteTriggers);
    }

    public function down(): void
    {
        throw new RuntimeException('The CDR version cleanup is intentionally irreversible because obsolete versions are deleted.');
    }

    private function preflight(): void
    {
        foreach (['ib39_cdr_processings', 'ib39_cdr_document_versions', 'ib39_cdr_status_histories', 'japic_certification_processings'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("CDR version cleanup refused: required table {$table} is missing.");
            }
        }
        if (Schema::hasTable('ib39_cdr_final_documents')) {
            throw new RuntimeException('CDR version cleanup refused: final-document storage already exists.');
        }

        $invalidPointers = DB::table('ib39_cdr_processings as c')
            ->leftJoin('ib39_cdr_document_versions as v', 'v.id', '=', 'c.current_final_version_id')
            ->whereNotNull('c.current_final_version_id')
            ->where(function ($query): void {
                $query->whereNull('v.id')
                    ->orWhereColumn('v.cdr_processing_id', '<>', 'c.id')
                    ->orWhereNull('v.storage_path')
                    ->orWhere('v.storage_path', '');
            })->exists();
        if ($invalidPointers) {
            throw new RuntimeException('CDR version cleanup refused: a current final document is missing, belongs to another CDR, or has no file reference.');
        }
        if (DB::table('ib39_cdr_processings')->where('status', 'Completed')->whereNull('current_final_version_id')->exists()) {
            throw new RuntimeException('CDR version cleanup refused: a completed CDR has no selected final document.');
        }

        $unmappedJapic = DB::table('japic_certification_processings as j')
            ->leftJoin('ib39_cdr_processings as c', 'c.current_final_version_id', '=', 'j.triggering_cdr_document_version_id')
            ->whereNotNull('j.triggering_cdr_document_version_id')->whereNull('c.id')->exists();
        if ($unmappedJapic) {
            throw new RuntimeException('CDR version cleanup refused: a JAPIC intake references a missing CDR document.');
        }

        if (Schema::hasTable('chat_message_document_references')) {
            $unmappedChat = DB::table('chat_message_document_references as r')
                ->leftJoin('ib39_cdr_processings as c', 'c.current_final_version_id', '=', 'r.ib39_cdr_document_version_id')
                ->whereNotNull('r.ib39_cdr_document_version_id')->whereNull('c.id')->exists();
            if ($unmappedChat) {
                throw new RuntimeException('CDR version cleanup refused: a chat message references a missing CDR document.');
            }
        }
    }

    private function backfillFinalDocuments(): array
    {
        $map = [];
        $rows = DB::table('ib39_cdr_processings as c')
            ->join('ib39_cdr_document_versions as v', 'v.id', '=', 'c.current_final_version_id')
            ->orderBy('c.id')
            ->select('v.*')->get();

        foreach ($rows as $row) {
            $map[(int) $row->id] = DB::table('ib39_cdr_final_documents')->insertGetId([
                'cdr_processing_id' => $row->cdr_processing_id,
                'source_type' => $row->source_type,
                'storage_path' => $row->storage_path,
                'original_filename' => $row->original_filename,
                'mime_type' => $row->mime_type,
                'size_bytes' => $row->size_bytes,
                'sha256' => $row->sha256,
                'content_schema_version' => $row->content_schema_version,
                'content_snapshot' => $row->content_snapshot,
                'created_by' => $row->created_by,
                'finalized_at' => $row->finalized_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        return $map;
    }

    private function backfillDependants(array $map): void
    {
        foreach ($map as $oldId => $newId) {
            DB::table('japic_certification_processings')
                ->where('triggering_cdr_document_version_id', $oldId)
                ->update(['triggering_cdr_final_document_id' => $newId]);
        }
        if (DB::table('japic_certification_processings')->whereNotNull('triggering_cdr_document_version_id')
            ->whereNull('triggering_cdr_final_document_id')->exists()) {
            throw new RuntimeException('CDR version cleanup refused: not every JAPIC reference was preserved.');
        }
    }

    private function replaceChatReferenceColumn(array $map): void
    {
        if (! Schema::hasTable('chat_message_document_references')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::rename('chat_message_document_references', 'chat_message_document_references_legacy');
            $this->createChatReferencesTable();
            $rows = DB::table('chat_message_document_references_legacy')->get();
            foreach ($rows as $row) {
                DB::table('chat_message_document_references')->insert([
                    'id' => $row->id,
                    'chat_message_id' => $row->chat_message_id,
                    'ib39_cdr_final_document_id' => $row->ib39_cdr_document_version_id === null ? null : ($map[(int) $row->ib39_cdr_document_version_id] ?? null),
                    'japic_certification_document_version_id' => $row->japic_certification_document_version_id,
                    'pswdo_enrollment_document_id' => $row->pswdo_enrollment_document_id,
                    'ib39_fea_document_version_id' => $row->ib39_fea_document_version_id,
                    'created_at' => $row->created_at,
                ]);
            }
            Schema::drop('chat_message_document_references_legacy');
            Schema::table('chat_message_document_references', fn (Blueprint $table) => $table->unique('chat_message_id', 'chat_reference_message_unique'));

            return;
        }

        DB::statement('ALTER TABLE chat_message_document_references DROP CONSTRAINT chat_reference_one_document_check');
        Schema::table('chat_message_document_references', function (Blueprint $table): void {
            $table->foreignId('ib39_cdr_final_document_id')->nullable()
                ->constrained('ib39_cdr_final_documents', indexName: 'chat_reference_cdr_final_foreign')
                ->restrictOnDelete();
        });
        foreach ($map as $oldId => $newId) {
            DB::table('chat_message_document_references')->where('ib39_cdr_document_version_id', $oldId)
                ->update(['ib39_cdr_final_document_id' => $newId]);
        }
        Schema::table('chat_message_document_references', function (Blueprint $table): void {
            $table->dropForeign('chat_reference_cdr_foreign');
            $table->dropColumn('ib39_cdr_document_version_id');
        });
        $columns = 'ib39_cdr_final_document_id, japic_certification_document_version_id, pswdo_enrollment_document_id, ib39_fea_document_version_id';
        DB::statement(DB::getDriverName() === 'pgsql'
            ? "ALTER TABLE chat_message_document_references ADD CONSTRAINT chat_reference_one_document_check CHECK (num_nonnulls({$columns}) = 1)"
            : 'ALTER TABLE chat_message_document_references ADD CONSTRAINT chat_reference_one_document_check CHECK ((ib39_cdr_final_document_id IS NOT NULL) + (japic_certification_document_version_id IS NOT NULL) + (pswdo_enrollment_document_id IS NOT NULL) + (ib39_fea_document_version_id IS NOT NULL) = 1)');
    }

    private function createChatReferencesTable(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE chat_message_document_references (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    chat_message_id INTEGER NOT NULL,
                    ib39_cdr_final_document_id INTEGER NULL,
                    japic_certification_document_version_id INTEGER NULL,
                    pswdo_enrollment_document_id INTEGER NULL,
                    ib39_fea_document_version_id INTEGER NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT chat_reference_one_document_check CHECK (
                        (ib39_cdr_final_document_id IS NOT NULL)
                        + (japic_certification_document_version_id IS NOT NULL)
                        + (pswdo_enrollment_document_id IS NOT NULL)
                        + (ib39_fea_document_version_id IS NOT NULL) = 1
                    ),
                    CONSTRAINT chat_reference_message_foreign FOREIGN KEY (chat_message_id) REFERENCES chat_messages (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_cdr_final_foreign FOREIGN KEY (ib39_cdr_final_document_id) REFERENCES ib39_cdr_final_documents (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_japic_foreign FOREIGN KEY (japic_certification_document_version_id) REFERENCES japic_certification_document_versions (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_pswdo_foreign FOREIGN KEY (pswdo_enrollment_document_id) REFERENCES pswdo_enrollment_documents (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_fea_foreign FOREIGN KEY (ib39_fea_document_version_id) REFERENCES ib39_fea_document_versions (id) ON DELETE RESTRICT
                )
            SQL);

            return;
        }

        Schema::create('chat_message_document_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained('chat_messages')->restrictOnDelete();
            $table->foreignId('ib39_cdr_final_document_id')->nullable()->constrained('ib39_cdr_final_documents')->restrictOnDelete();
            $table->foreignId('japic_certification_document_version_id')->nullable()->constrained('japic_certification_document_versions')->restrictOnDelete();
            $table->foreignId('pswdo_enrollment_document_id')->nullable()->constrained('pswdo_enrollment_documents')->restrictOnDelete();
            $table->foreignId('ib39_fea_document_version_id')->nullable()->constrained('ib39_fea_document_versions')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    private function dropLegacyConstraints(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ib39_cdr_processings DROP CONSTRAINT IF EXISTS ib39_cdr_current_document_owner_foreign');
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS ib39_cdr_current_document_update');
        }
        Schema::table('ib39_cdr_status_histories', fn (Blueprint $table) => $table->dropForeign(['document_version_id']));
        Schema::table('ib39_cdr_processings', fn (Blueprint $table) => $table->dropForeign(['current_final_version_id']));
        Schema::table('japic_certification_processings', fn (Blueprint $table) => $table->dropForeign(['triggering_cdr_document_version_id']));
    }

    private function restoreSqliteGuards(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $statuses = "'Pending','Ongoing','Completed'";
        $sources = "'generated','uploaded'";
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS ib39_cdr_processing_status_insert BEFORE INSERT ON ib39_cdr_processings WHEN NEW.status NOT IN ({$statuses}) BEGIN SELECT RAISE(ABORT, 'invalid CDR processing status'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS ib39_cdr_processing_status_update BEFORE UPDATE OF status ON ib39_cdr_processings WHEN NEW.status NOT IN ({$statuses}) BEGIN SELECT RAISE(ABORT, 'invalid CDR processing status'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS ib39_cdr_history_status_insert BEFORE INSERT ON ib39_cdr_status_histories WHEN NEW.to_status NOT IN ({$statuses}) OR (NEW.from_status IS NOT NULL AND NEW.from_status NOT IN ({$statuses})) BEGIN SELECT RAISE(ABORT, 'invalid CDR history status'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS ib39_cdr_history_status_update BEFORE UPDATE OF from_status, to_status ON ib39_cdr_status_histories WHEN NEW.to_status NOT IN ({$statuses}) OR (NEW.from_status IS NOT NULL AND NEW.from_status NOT IN ({$statuses})) BEGIN SELECT RAISE(ABORT, 'invalid CDR history status'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS ib39_cdr_final_document_source_insert BEFORE INSERT ON ib39_cdr_final_documents WHEN NEW.source_type NOT IN ({$sources}) BEGIN SELECT RAISE(ABORT, 'invalid CDR final document source'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS ib39_cdr_final_document_source_update BEFORE UPDATE OF source_type ON ib39_cdr_final_documents WHEN NEW.source_type NOT IN ({$sources}) BEGIN SELECT RAISE(ABORT, 'invalid CDR final document source'); END");
    }

    /** @return array<int, object{name: string, sql: string}> */
    private function sqliteJapicProcessingTriggers(): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }

        return DB::table('sqlite_master')->where('type', 'trigger')
            ->where('tbl_name', 'japic_certification_processings')
            ->whereNotNull('sql')->get(['name', 'sql'])->all();
    }

    /** @param array<int, object{name: string, sql: string}> $triggers */
    private function restoreSqliteJapicProcessingTriggers(array $triggers): void
    {
        foreach ($triggers as $trigger) {
            if (! DB::table('sqlite_master')->where('type', 'trigger')->where('name', $trigger->name)->exists()) {
                DB::unprepared($trigger->sql);
            }
        }
    }
};
