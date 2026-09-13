<?php

namespace Tests\Feature;

use App\Models\ChatConversationRead;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatConversationService;
use App\Services\ChatUnreadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatUnreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_counts_are_persistent_per_user_and_exclude_own_messages(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $firstMessage = $this->message($conversation->id, $first->id, 'From first');
        $secondMessage = $this->message($conversation->id, $second->id, 'From second');
        $this->message($conversation->id, $first->id, 'Another from first');

        $service = app(ChatUnreadService::class);
        $this->assertSame(1, $service->summary($first)['total']);
        $this->assertSame(2, $service->summary($second)['total']);

        $this->actingAs($second)->postJson(route('chat.conversations.read', $conversation), [
            'through_id' => $firstMessage->id,
        ])->assertOk()->assertJsonPath('unread.total', 1);

        $this->assertDatabaseHas('chat_conversation_reads', [
            'chat_conversation_id' => $conversation->id,
            'user_id' => $second->id,
            'last_read_message_id' => $firstMessage->id,
        ]);
        auth()->logout();
        $this->actingAs($second)->getJson(route('chat.unread'))
            ->assertOk()->assertJsonPath('unread.total', 1);

        $this->actingAs($first)->postJson(route('chat.conversations.read', $conversation), [
            'through_id' => $secondMessage->id,
        ])->assertOk()->assertJsonPath('unread.total', 0);
    }

    public function test_read_cursor_is_monotonic_and_rejects_a_message_from_another_conversation(): void
    {
        [$first, $second, $third] = User::factory()->count(3)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $other = app(ChatConversationService::class)->start($first, $third->id);
        $earlier = $this->message($conversation->id, $second->id, 'Earlier');
        $later = $this->message($conversation->id, $second->id, 'Later');
        $foreign = $this->message($other->id, $third->id, 'Foreign');

        $this->actingAs($first)->postJson(route('chat.conversations.read', $conversation), [
            'through_id' => $later->id,
        ])->assertOk();
        $this->postJson(route('chat.conversations.read', $conversation), [
            'through_id' => $earlier->id,
        ])->assertOk();
        $this->assertSame($later->id, ChatConversationRead::query()->sole()->last_read_message_id);

        $this->postJson(route('chat.conversations.read', $conversation), [
            'through_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('through_id');
        $this->assertSame($later->id, ChatConversationRead::query()->sole()->last_read_message_id);
    }

    public function test_page_and_summary_polling_do_not_mark_a_conversation_read(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $message = $this->message($conversation->id, $second->id, 'Still unread');

        $this->actingAs($first)->get(route('chat.conversations.show', $conversation))->assertOk();
        $this->getJson(route('chat.messages.index', [$conversation, 'after_id' => 0]))
            ->assertOk()->assertJsonPath('messages.0.id', $message->id);
        $this->getJson(route('chat.unread'))->assertOk()->assertJsonPath('unread.total', 1);

        $this->assertDatabaseCount('chat_conversation_reads', 0);
    }

    public function test_summary_caps_only_display_text_and_uses_one_aggregate_query(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        foreach (range(1, 100) as $number) {
            $this->message($conversation->id, $second->id, "Unread {$number}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $summary = app(ChatUnreadService::class)->summary($first);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(100, $summary['total']);
        $this->assertSame('99+', $summary['total_text']);
        $this->assertSame('99+', $summary['conversations'][$conversation->id]['count_text']);
        $this->assertSame(1, $queryCount);
    }

    private function message(int $conversationId, int $senderId, string $body): ChatMessage
    {
        return ChatMessage::query()->forceCreate([
            'chat_conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'body' => $body,
        ]);
    }
}
