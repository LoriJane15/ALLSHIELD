<?php

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrPhotoType;
use App\Enums\Ib39CdrStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ib39_cdr_processings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ib39_surfaced_former_rebel_id')
                ->unique()
                ->constrained('ib39_surfaced_former_rebels')
                ->restrictOnDelete();
            $table->enum('status', array_column(Ib39CdrStatus::cases(), 'value'))
                ->default(Ib39CdrStatus::Pending->value)
                ->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->text('delay_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ib39_cdr_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cdr_processing_id')
                ->unique()
                ->constrained('ib39_cdr_processings')
                ->restrictOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->text('content')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ib39_cdr_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cdr_processing_id')
                ->constrained('ib39_cdr_processings')
                ->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('from_status', array_column(Ib39CdrStatus::cases(), 'value'))->nullable();
            $table->enum('to_status', array_column(Ib39CdrStatus::cases(), 'value'));
            $table->string('event', 50);
            $table->text('remarks')->nullable();
            $table->text('delay_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['cdr_processing_id', 'created_at'], 'ib39_cdr_history_processing_created_index');
        });

        Schema::create('ib39_cdr_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cdr_processing_id')
                ->constrained('ib39_cdr_processings')
                ->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->enum('source_type', array_column(Ib39CdrDocumentSource::cases(), 'value'))->index();
            $table->foreignId('replaces_version_id')
                ->nullable()
                ->constrained('ib39_cdr_document_versions')
                ->restrictOnDelete();
            $table->text('replacement_reason')->nullable();
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

            $table->unique(['cdr_processing_id', 'version_number'], 'ib39_cdr_document_processing_version_unique');
            $table->index(['cdr_processing_id', 'finalized_at'], 'ib39_cdr_document_processing_finalized_index');
        });

        Schema::create('ib39_cdr_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cdr_processing_id')
                ->constrained('ib39_cdr_processings')
                ->restrictOnDelete();
            $table->enum('photo_type', array_column(Ib39CdrPhotoType::cases(), 'value'));
            $table->timestamps();

            $table->unique(['cdr_processing_id', 'photo_type'], 'ib39_cdr_photo_processing_type_unique');
        });

        Schema::create('ib39_cdr_photo_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cdr_photo_id')->constrained('ib39_cdr_photos')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['cdr_photo_id', 'version_number'], 'ib39_cdr_photo_version_unique');
        });

        Schema::table('ib39_cdr_processings', function (Blueprint $table) {
            $table->foreignId('current_final_version_id')
                ->nullable()
                ->constrained('ib39_cdr_document_versions')
                ->restrictOnDelete();
        });

        Schema::table('ib39_cdr_status_histories', function (Blueprint $table) {
            $table->foreignId('document_version_id')
                ->nullable()
                ->constrained('ib39_cdr_document_versions')
                ->restrictOnDelete();
        });

        Schema::table('ib39_cdr_photos', function (Blueprint $table) {
            $table->foreignId('current_photo_version_id')
                ->nullable()
                ->constrained('ib39_cdr_photo_versions')
                ->restrictOnDelete();
        });

        $this->createControlledValueTriggers();

        $this->backfillExistingSurfacedFormerRebels();
    }

    private function createControlledValueTriggers(): void
    {
        $statuses = "'".implode("','", array_column(Ib39CdrStatus::cases(), 'value'))."'";
        $sources = "'".implode("','", array_column(Ib39CdrDocumentSource::cases(), 'value'))."'";
        $photoTypes = "'".implode("','", array_column(Ib39CdrPhotoType::cases(), 'value'))."'";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ib39_cdr_processings ADD CONSTRAINT ib39_cdr_processing_status_check CHECK (status IN ({$statuses}))");
            DB::statement("ALTER TABLE ib39_cdr_status_histories ADD CONSTRAINT ib39_cdr_history_status_check CHECK (to_status IN ({$statuses}) AND (from_status IS NULL OR from_status IN ({$statuses})))");
            DB::statement("ALTER TABLE ib39_cdr_document_versions ADD CONSTRAINT ib39_cdr_document_source_check CHECK (source_type IN ({$sources}))");
            DB::statement("ALTER TABLE ib39_cdr_photos ADD CONSTRAINT ib39_cdr_photo_type_check CHECK (photo_type IN ({$photoTypes}))");
            DB::statement("ALTER TABLE ib39_cdr_document_versions ADD CONSTRAINT ib39_cdr_replacement_reason_check CHECK (replaces_version_id IS NULL OR NULLIF(TRIM(replacement_reason), '') IS NOT NULL)");
            DB::statement('ALTER TABLE ib39_cdr_document_versions ADD CONSTRAINT ib39_cdr_document_owner_unique UNIQUE (id, cdr_processing_id)');
            DB::statement('ALTER TABLE ib39_cdr_photo_versions ADD CONSTRAINT ib39_cdr_photo_owner_unique UNIQUE (id, cdr_photo_id)');
            DB::statement('ALTER TABLE ib39_cdr_document_versions ADD CONSTRAINT ib39_cdr_replacement_owner_foreign FOREIGN KEY (replaces_version_id, cdr_processing_id) REFERENCES ib39_cdr_document_versions (id, cdr_processing_id)');
            DB::statement('ALTER TABLE ib39_cdr_processings ADD CONSTRAINT ib39_cdr_current_document_owner_foreign FOREIGN KEY (current_final_version_id, id) REFERENCES ib39_cdr_document_versions (id, cdr_processing_id)');
            DB::statement('ALTER TABLE ib39_cdr_photos ADD CONSTRAINT ib39_cdr_current_photo_owner_foreign FOREIGN KEY (current_photo_version_id, id) REFERENCES ib39_cdr_photo_versions (id, cdr_photo_id)');

            return;
        }

        foreach (['INSERT' => 'NEW.status', 'UPDATE OF status' => 'NEW.status'] as $operation => $value) {
            $suffix = str_starts_with($operation, 'INSERT') ? 'insert' : 'update';
            DB::unprepared("CREATE TRIGGER ib39_cdr_processing_status_{$suffix}
                BEFORE {$operation} ON ib39_cdr_processings
                WHEN {$value} NOT IN ({$statuses})
                BEGIN SELECT RAISE(ABORT, 'invalid CDR processing status'); END");
        }

        DB::unprepared("CREATE TRIGGER ib39_cdr_history_status_insert
            BEFORE INSERT ON ib39_cdr_status_histories
            WHEN NEW.to_status NOT IN ({$statuses})
                OR (NEW.from_status IS NOT NULL AND NEW.from_status NOT IN ({$statuses}))
            BEGIN SELECT RAISE(ABORT, 'invalid CDR history status'); END");
        DB::unprepared("CREATE TRIGGER ib39_cdr_history_status_update
            BEFORE UPDATE OF from_status, to_status ON ib39_cdr_status_histories
            WHEN NEW.to_status NOT IN ({$statuses})
                OR (NEW.from_status IS NOT NULL AND NEW.from_status NOT IN ({$statuses}))
            BEGIN SELECT RAISE(ABORT, 'invalid CDR history status'); END");

        DB::unprepared("CREATE TRIGGER ib39_cdr_document_source_insert
            BEFORE INSERT ON ib39_cdr_document_versions
            WHEN NEW.source_type NOT IN ({$sources})
            BEGIN SELECT RAISE(ABORT, 'invalid CDR document source'); END");
        DB::unprepared("CREATE TRIGGER ib39_cdr_document_source_update
            BEFORE UPDATE OF source_type ON ib39_cdr_document_versions
            WHEN NEW.source_type NOT IN ({$sources})
            BEGIN SELECT RAISE(ABORT, 'invalid CDR document source'); END");

        DB::unprepared("CREATE TRIGGER ib39_cdr_document_replacement_insert
            BEFORE INSERT ON ib39_cdr_document_versions
            WHEN NEW.replaces_version_id IS NOT NULL AND (
                TRIM(COALESCE(NEW.replacement_reason, '')) = ''
                OR NOT EXISTS (
                    SELECT 1 FROM ib39_cdr_document_versions replaced
                    WHERE replaced.id = NEW.replaces_version_id
                        AND replaced.cdr_processing_id = NEW.cdr_processing_id
                )
            )
            BEGIN SELECT RAISE(ABORT, 'invalid CDR document replacement'); END");

        DB::unprepared("CREATE TRIGGER ib39_cdr_current_document_update
            BEFORE UPDATE OF current_final_version_id ON ib39_cdr_processings
            WHEN NEW.current_final_version_id IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM ib39_cdr_document_versions version
                WHERE version.id = NEW.current_final_version_id
                    AND version.cdr_processing_id = NEW.id
            )
            BEGIN SELECT RAISE(ABORT, 'CDR current version belongs to another processing record'); END");

        DB::unprepared("CREATE TRIGGER ib39_cdr_photo_type_insert
            BEFORE INSERT ON ib39_cdr_photos
            WHEN NEW.photo_type NOT IN ({$photoTypes})
            BEGIN SELECT RAISE(ABORT, 'invalid CDR photo type'); END");
        DB::unprepared("CREATE TRIGGER ib39_cdr_photo_type_update
            BEFORE UPDATE OF photo_type ON ib39_cdr_photos
            WHEN NEW.photo_type NOT IN ({$photoTypes})
            BEGIN SELECT RAISE(ABORT, 'invalid CDR photo type'); END");

        DB::unprepared("CREATE TRIGGER ib39_cdr_current_photo_update
            BEFORE UPDATE OF current_photo_version_id ON ib39_cdr_photos
            WHEN NEW.current_photo_version_id IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM ib39_cdr_photo_versions version
                WHERE version.id = NEW.current_photo_version_id
                    AND version.cdr_photo_id = NEW.id
            )
            BEGIN SELECT RAISE(ABORT, 'CDR current photo version belongs to another photo'); END");
    }

    public function backfillExistingSurfacedFormerRebels(): void
    {
        $timestamp = now();

        DB::table('ib39_surfaced_former_rebels')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $surfacedFormerRebelId) use ($timestamp): void {
                DB::table('ib39_cdr_processings')->insertOrIgnore([
                    'ib39_surfaced_former_rebel_id' => $surfacedFormerRebelId,
                    'status' => Ib39CdrStatus::Pending->value,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $processingId = DB::table('ib39_cdr_processings')
                    ->where('ib39_surfaced_former_rebel_id', $surfacedFormerRebelId)
                    ->value('id');

                DB::table('ib39_cdr_forms')->insertOrIgnore([
                    'cdr_processing_id' => $processingId,
                    'schema_version' => 1,
                    'content' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                if (! DB::table('ib39_cdr_status_histories')
                    ->where('cdr_processing_id', $processingId)
                    ->where('event', 'system_backfill_created')
                    ->exists()) {
                    DB::table('ib39_cdr_status_histories')->insert([
                        'cdr_processing_id' => $processingId,
                        'user_id' => null,
                        'from_status' => null,
                        'to_status' => Ib39CdrStatus::Pending->value,
                        'event' => 'system_backfill_created',
                        'remarks' => null,
                        'delay_reason' => null,
                        'ip_address' => null,
                        'user_agent' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ib39_cdr_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_photo_version_id');
        });
        Schema::table('ib39_cdr_status_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_version_id');
        });
        Schema::table('ib39_cdr_processings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_final_version_id');
        });

        Schema::dropIfExists('ib39_cdr_photo_versions');
        Schema::dropIfExists('ib39_cdr_photos');
        Schema::dropIfExists('ib39_cdr_document_versions');
        Schema::dropIfExists('ib39_cdr_status_histories');
        Schema::dropIfExists('ib39_cdr_forms');
        Schema::dropIfExists('ib39_cdr_processings');
    }
};
