<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id'], 'chat_conversations_user_pair_unique');
            $table->index(['user_one_id', 'updated_at'], 'chat_conversations_user_one_updated_index');
            $table->index(['user_two_id', 'updated_at'], 'chat_conversations_user_two_updated_index');
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at');

            $table->index(['chat_conversation_id', 'id'], 'chat_messages_conversation_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
