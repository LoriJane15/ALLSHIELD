<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_schema_has_required_constraints_and_retrieval_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('chat_conversations', [
            'id', 'user_one_id', 'user_two_id', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('chat_messages', [
            'id', 'chat_conversation_id', 'sender_id', 'body', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('chat_messages', 'updated_at'));
        $this->assertTrue(Schema::hasColumns('chat_conversation_reads', [
            'id', 'chat_conversation_id', 'user_id', 'last_read_message_id', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('chat_message_document_references', [
            'id', 'chat_message_id', 'ib39_cdr_document_version_id',
            'japic_certification_document_version_id', 'pswdo_enrollment_document_id',
            'ib39_fea_document_version_id', 'created_at',
        ]));

        $conversationIndexes = collect(Schema::getIndexes('chat_conversations'))->pluck('name');
        $this->assertContains('chat_conversations_user_pair_unique', $conversationIndexes);
        $this->assertContains('chat_conversations_user_one_updated_index', $conversationIndexes);
        $this->assertContains('chat_conversations_user_two_updated_index', $conversationIndexes);
        $this->assertContains('chat_messages_conversation_id_index', collect(Schema::getIndexes('chat_messages'))->pluck('name'));
        $this->assertContains('chat_messages_unread_lookup_index', collect(Schema::getIndexes('chat_messages'))->pluck('name'));
        $this->assertContains('chat_reads_conversation_user_unique', collect(Schema::getIndexes('chat_conversation_reads'))->pluck('name'));
        $this->assertContains('chat_reference_message_unique', collect(Schema::getIndexes('chat_message_document_references'))->pluck('name'));

        $conversationForeignKeys = collect(Schema::getForeignKeys('chat_conversations'));
        $messageForeignKeys = collect(Schema::getForeignKeys('chat_messages'));
        $this->assertCount(2, $conversationForeignKeys);
        $this->assertCount(2, $messageForeignKeys);
        $this->assertSame(['users'], $conversationForeignKeys->pluck('foreign_table')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['chat_conversations', 'users'], $messageForeignKeys->pluck('foreign_table')->all());
        $this->assertCount(3, Schema::getForeignKeys('chat_conversation_reads'));
        $this->assertCount(5, Schema::getForeignKeys('chat_message_document_references'));
    }

    public function test_database_unique_constraint_rejects_a_duplicate_canonical_pair(): void
    {
        $users = User::factory()->count(2)->create()->sortBy('id')->values();
        ChatConversation::query()->create([
            'user_one_id' => $users[0]->id,
            'user_two_id' => $users[1]->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('chat_conversations')->insert([
            'user_one_id' => $users[0]->id,
            'user_two_id' => $users[1]->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_document_reference_requires_exactly_one_concrete_target(): void
    {
        $users = User::factory()->count(2)->create()->sortBy('id')->values();
        $conversation = ChatConversation::query()->create([
            'user_one_id' => $users[0]->id,
            'user_two_id' => $users[1]->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $users[0]->id,
            'body' => 'Constraint check',
        ]);

        $this->expectException(QueryException::class);
        DB::table('chat_message_document_references')->insert([
            'chat_message_id' => $message->id,
            'created_at' => now(),
        ]);
    }
}
