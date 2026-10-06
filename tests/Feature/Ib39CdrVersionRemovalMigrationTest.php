<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class Ib39CdrVersionRemovalMigrationTest extends TestCase
{
    private string $originalConnection;

    private string $databaseFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'cdr-final-migration-');
        config(['database.connections.cdr_final_migration_test' => [
            'driver' => 'sqlite', 'database' => $this->databaseFile, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ]]);
        DB::setDefaultConnection('cdr_final_migration_test');
        DB::purge('cdr_final_migration_test');

        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (! str_contains($file, '2026_09_30_000001')) {
                (require $file)->up();
            }
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('cdr_final_migration_test');
        DB::setDefaultConnection($this->originalConnection);
        if (is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }
        parent::tearDown();
    }

    public function test_current_final_and_dependants_are_preserved_before_version_table_is_removed(): void
    {
        $this->legacyCdr(true);
        $beforeHistory = DB::table('ib39_cdr_status_histories')->count();
        $beforeComments = DB::table('ib39_cdr_comments')->count();

        $this->migration()->up();

        $this->assertTrue(Schema::hasTable('ib39_cdr_final_documents'));
        $this->assertFalse(Schema::hasTable('ib39_cdr_document_versions'));
        $this->assertFalse(Schema::hasColumn('ib39_cdr_processings', 'current_final_version_id'));
        $this->assertFalse(Schema::hasColumn('ib39_cdr_status_histories', 'document_version_id'));
        $this->assertFalse(Schema::hasColumn('japic_certification_processings', 'triggering_cdr_document_version_id'));
        $this->assertFalse(Schema::hasColumn('chat_message_document_references', 'ib39_cdr_document_version_id'));

        $final = DB::table('ib39_cdr_final_documents')->sole();
        $this->assertSame('private/cdr/current.pdf', $final->storage_path);
        $this->assertSame('current.pdf', $final->original_filename);
        $this->assertSame('application/pdf', $final->mime_type);
        $this->assertSame(22, $final->size_bytes);
        $this->assertSame(str_repeat('b', 64), $final->sha256);
        $this->assertSame(1, $final->created_by);
        $this->assertSame('2026-09-02 10:00:00', $final->finalized_at);
        $this->assertSame($final->id, DB::table('japic_certification_processings')->value('triggering_cdr_final_document_id'));
        $this->assertSame($final->id, DB::table('chat_message_document_references')->value('ib39_cdr_final_document_id'));
        $this->assertSame($beforeHistory, DB::table('ib39_cdr_status_histories')->count());
        $this->assertSame($beforeComments, DB::table('ib39_cdr_comments')->count());
        $this->assertSame('Final remark', DB::table('ib39_cdr_comments')->value('text'));
        $this->assertSame('final_document_uploaded', DB::table('ib39_cdr_status_histories')->where('id', 1)->value('event'));
    }

    public function test_ambiguous_chat_reference_refuses_migration_before_creating_final_storage(): void
    {
        $this->legacyCdr(false);
        DB::table('chat_message_document_references')->insert([
            'chat_message_id' => 1, 'ib39_cdr_document_version_id' => 1, 'created_at' => now(),
        ]);

        try {
            $this->migration()->up();
            $this->fail('A chat reference to an unselected CDR version was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('chat message references', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('ib39_cdr_final_documents'));
        $this->assertSame(2, DB::table('ib39_cdr_document_versions')->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_30_000001_replace_ib39_cdr_versions_with_final_documents.php');
    }

    private function legacyCdr(bool $referenceCurrent): void
    {
        $time = '2026-09-02 10:00:00';
        foreach ([1 => '39th_ib', 2 => 'japic'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'username' => 'cdr-migration-'.$id,
                'name' => 'Migration User '.$id, 'password' => 'hash', 'role' => $role,
                'is_active' => 1, 'created_at' => $time, 'updated_at' => $time]);
        }
        DB::table('municipalities')->insert(['id' => 1, 'name' => 'Migration Municipality', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_surfaced_former_rebels')->insert(['id' => 1, 'reference_number' => 'CDR-MIGRATION',
            'first_name' => 'Migration', 'last_name' => 'Subject', 'category' => 'Regular Member',
            'province' => 'Davao del Sur', 'municipality_id' => 1, 'surfaced_at' => '2026-09-01',
            'possessed_firearms' => 0, 'created_by' => 1, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_processings')->insert(['id' => 1, 'ib39_surfaced_former_rebel_id' => 1,
            'status' => 'Completed', 'completed_at' => $time, 'completed_by' => 1,
            'created_at' => $time, 'updated_at' => $time]);
        foreach ([1 => 'old.pdf', 2 => 'current.pdf'] as $id => $filename) {
            DB::table('ib39_cdr_document_versions')->insert(['id' => $id, 'cdr_processing_id' => 1,
                'version_number' => $id, 'source_type' => 'uploaded',
                'replaces_version_id' => $id === 1 ? null : 1,
                'replacement_reason' => $id === 1 ? null : 'Corrected final copy',
                'storage_path' => 'private/cdr/'.$filename, 'original_filename' => $filename,
                'mime_type' => 'application/pdf', 'size_bytes' => $id === 1 ? 11 : 22,
                'sha256' => str_repeat($id === 1 ? 'a' : 'b', 64), 'created_by' => 1,
                'finalized_at' => $time, 'created_at' => $time, 'updated_at' => $time]);
        }
        DB::table('ib39_cdr_processings')->where('id', 1)->update(['current_final_version_id' => 2]);
        DB::table('ib39_cdr_status_histories')->insert(['id' => 1, 'cdr_processing_id' => 1,
            'user_id' => 1, 'from_status' => 'Ongoing', 'to_status' => 'Completed',
            'event' => 'final_document_replaced', 'document_version_id' => 2,
            'created_at' => $time, 'updated_at' => $time]);
        DB::table('ib39_cdr_comments')->insert(['cdr_processing_id' => 1, 'user_id' => 1,
            'author_role' => '39th_ib', 'text' => 'Final remark', 'created_at' => $time, 'updated_at' => $time]);
        DB::table('japic_certification_processings')->insert(['id' => 1,
            'ib39_surfaced_former_rebel_id' => 1, 'triggering_cdr_document_version_id' => 2,
            'status' => 'Pending', 'received_at' => $time, 'due_at' => '2026-09-16 10:00:00',
            'lock_version' => 0, 'created_at' => $time, 'updated_at' => $time]);
        DB::table('chat_conversations')->insert(['id' => 1, 'user_one_id' => 1, 'user_two_id' => 2,
            'created_at' => $time, 'updated_at' => $time]);
        DB::table('chat_messages')->insert(['id' => 1, 'chat_conversation_id' => 1, 'sender_id' => 1,
            'body' => 'Please review', 'created_at' => $time]);
        if ($referenceCurrent) {
            DB::table('chat_message_document_references')->insert([
                'chat_message_id' => 1, 'ib39_cdr_document_version_id' => 2, 'created_at' => $time,
            ]);
        }
    }
}
