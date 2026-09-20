<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE japic_certification_histories DROP CONSTRAINT IF EXISTS japic_history_document_fk');
        DB::statement(<<<'SQL'
            ALTER TABLE japic_certification_histories
            ADD CONSTRAINT japic_history_document_fk
            FOREIGN KEY (document_version_id)
            REFERENCES japic_certification_document_versions (id)
            ON DELETE SET NULL
        SQL);

        DB::statement('ALTER TABLE ib39_cdr_photos DROP CONSTRAINT IF EXISTS ib39_cdr_photos_photo_type_check');
        DB::statement('ALTER TABLE ib39_cdr_photos DROP CONSTRAINT IF EXISTS ib39_cdr_photo_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE ib39_cdr_photos
            ADD CONSTRAINT ib39_cdr_photo_type_check
            CHECK (photo_type = 'fr_photo')
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE japic_certification_histories DROP CONSTRAINT IF EXISTS japic_history_document_fk');
        DB::statement(<<<'SQL'
            ALTER TABLE japic_certification_histories
            ADD CONSTRAINT japic_history_document_fk
            FOREIGN KEY (document_version_id)
            REFERENCES japic_certification_document_versions (id)
            ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE ib39_cdr_photos
            ADD CONSTRAINT ib39_cdr_photos_photo_type_check
            CHECK (photo_type = 'fr_photo')
        SQL);
    }
};
