<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversation_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('chat_messages')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['chat_conversation_id', 'user_id'], 'chat_reads_conversation_user_unique');
            $table->index(['user_id', 'chat_conversation_id'], 'chat_reads_user_conversation_index');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE chat_message_document_references (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    chat_message_id INTEGER NOT NULL,
                    ib39_cdr_document_version_id INTEGER NULL,
                    japic_certification_document_version_id INTEGER NULL,
                    pswdo_enrollment_document_id INTEGER NULL,
                    ib39_fea_document_version_id INTEGER NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT chat_reference_one_document_check CHECK (
                        (ib39_cdr_document_version_id IS NOT NULL)
                        + (japic_certification_document_version_id IS NOT NULL)
                        + (pswdo_enrollment_document_id IS NOT NULL)
                        + (ib39_fea_document_version_id IS NOT NULL) = 1
                    ),
                    CONSTRAINT chat_reference_message_foreign FOREIGN KEY (chat_message_id) REFERENCES chat_messages (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_cdr_foreign FOREIGN KEY (ib39_cdr_document_version_id) REFERENCES ib39_cdr_document_versions (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_japic_foreign FOREIGN KEY (japic_certification_document_version_id) REFERENCES japic_certification_document_versions (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_pswdo_foreign FOREIGN KEY (pswdo_enrollment_document_id) REFERENCES pswdo_enrollment_documents (id) ON DELETE RESTRICT,
                    CONSTRAINT chat_reference_fea_foreign FOREIGN KEY (ib39_fea_document_version_id) REFERENCES ib39_fea_document_versions (id) ON DELETE RESTRICT
                )
            SQL);
            DB::statement('CREATE UNIQUE INDEX chat_reference_message_unique ON chat_message_document_references (chat_message_id)');
        } else {
            Schema::create('chat_message_document_references', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('chat_message_id')->unique('chat_reference_message_unique')->constrained('chat_messages')->restrictOnDelete();
                $table->foreignId('ib39_cdr_document_version_id')->nullable()->constrained('ib39_cdr_document_versions', indexName: 'chat_reference_cdr_foreign')->restrictOnDelete();
                $table->foreignId('japic_certification_document_version_id')->nullable()->constrained('japic_certification_document_versions', indexName: 'chat_reference_japic_foreign')->restrictOnDelete();
                $table->foreignId('pswdo_enrollment_document_id')->nullable()->constrained('pswdo_enrollment_documents', indexName: 'chat_reference_pswdo_foreign')->restrictOnDelete();
                $table->foreignId('ib39_fea_document_version_id')->nullable()->constrained('ib39_fea_document_versions', indexName: 'chat_reference_fea_foreign')->restrictOnDelete();
                $table->timestamp('created_at');
            });

            DB::statement(<<<'SQL'
                ALTER TABLE chat_message_document_references
                ADD CONSTRAINT chat_reference_one_document_check CHECK (
                    (ib39_cdr_document_version_id IS NOT NULL)
                    + (japic_certification_document_version_id IS NOT NULL)
                    + (pswdo_enrollment_document_id IS NOT NULL)
                    + (ib39_fea_document_version_id IS NOT NULL) = 1
                )
            SQL);
        }

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->index(
                ['chat_conversation_id', 'sender_id', 'id'],
                'chat_messages_unread_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropIndex('chat_messages_unread_lookup_index');
        });

        Schema::dropIfExists('chat_message_document_references');
        Schema::dropIfExists('chat_conversation_reads');
    }
};
