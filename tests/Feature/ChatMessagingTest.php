<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ChatMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverse_order_starts_return_one_canonical_conversation(): void
    {
        [$first, $second] = User::factory()->count(2)->create()->sortByDesc('id')->values();
        $service = app(ChatConversationService::class);

        $one = $service->start($first, $second->id);
        $two = $service->start($second, $first->id);

        $this->assertTrue($one->is($two));
        $this->assertLessThan($one->user_two_id, $one->user_one_id);
        $this->assertDatabaseCount('chat_conversations', 1);
    }

    public function test_message_body_is_trimmed_validated_and_sender_is_derived_from_authentication(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);

        $this->actingAs($first)->postJson(route('chat.messages.store', $conversation), ['body' => '   '])
            ->assertUnprocessable();
        $this->postJson(route('chat.messages.store', $conversation), ['body' => str_repeat('x', 5001)])
            ->assertUnprocessable();
        $this->postJson(route('chat.messages.store', $conversation), ['body' => ['not-a-string']])
            ->assertUnprocessable();

        $this->postJson(route('chat.messages.store', $conversation), [
            'body' => '  Valid private message  ',
            'sender_id' => $second->id,
            'chat_conversation_id' => 9999,
        ])->assertCreated()->assertJsonPath('message.body', 'Valid private message');

        $message = ChatMessage::query()->sole();
        $this->assertSame($first->id, $message->sender_id);
        $this->assertSame($conversation->id, $message->chat_conversation_id);
    }

    public function test_both_participants_retain_complete_paginated_history(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        foreach (range(1, 55) as $number) {
            $actor = $number % 2 ? $first : $second;
            $this->actingAs($actor)->postJson(route('chat.messages.store', $conversation), [
                'body' => "Message {$number}",
            ])->assertCreated();
        }

        $page = $this->actingAs($first)->get(route('chat.conversations.show', $conversation));
        $page->assertOk()->assertSee('Message 55')->assertDontSee('>Message 1<', false)->assertSee('Load older messages');

        $oldestVisible = ChatMessage::query()->where('chat_conversation_id', $conversation->id)->orderByDesc('id')->skip(49)->value('id');
        $older = $this->actingAs($second)->getJson(route('chat.messages.index', [
            $conversation, 'before_id' => $oldestVisible,
        ]));
        $older->assertOk()->assertJsonCount(5, 'messages')->assertJsonPath('messages.0.body', 'Message 1');
    }

    public function test_polling_returns_only_new_messages_in_order_with_allowlisted_fields(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $otherConversation = app(ChatConversationService::class)->start($first, User::factory()->create()->id);

        $this->actingAs($first)->postJson(route('chat.messages.store', $conversation), ['body' => 'Existing'])->assertCreated();
        $cursor = ChatMessage::query()->latest('id')->value('id');
        $this->actingAs($second)->postJson(route('chat.messages.store', $conversation), ['body' => 'New one'])->assertCreated();
        $this->actingAs($first)->postJson(route('chat.messages.store', $conversation), ['body' => 'New two'])->assertCreated();
        $this->postJson(route('chat.messages.store', $otherConversation), ['body' => 'Unrelated'])->assertCreated();

        $response = $this->actingAs($second)->getJson(route('chat.messages.index', [
            $conversation, 'after_id' => $cursor,
        ]));

        $response->assertOk()->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.body', 'New one')
            ->assertJsonPath('messages.1.body', 'New two');
        $this->assertSame(
            ['body', 'document_reference', 'id', 'is_mine', 'sender_name', 'sent_at_display', 'sent_at_iso'],
            collect($response->json('messages.0'))->keys()->sort()->values()->all()
        );
        $response->assertJsonPath('messages.0.document_reference', null);
        foreach (['private', 'no-store', 'no-cache', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_message_html_is_escaped_and_private_responses_are_not_cached(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $payload = '<script>alert("chat")</script>';
        $this->actingAs($first)->postJson(route('chat.messages.store', $conversation), ['body' => $payload])->assertCreated();

        $response = $this->actingAs($second)->get(route('chat.conversations.show', $conversation));
        $response->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;chat&quot;)&lt;/script&gt;', false)
            ->assertDontSee($first->email)
            ->assertDontSee($first->username);
        foreach (['private', 'no-store', 'no-cache', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_sender_message_uses_the_readable_sender_bubble_without_the_conflicting_background_utility(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($sender, $recipient->id);

        $created = $this->actingAs($sender)->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Visible to sender',
        ])->assertCreated()->assertJsonPath('message.is_mine', true);

        $created->assertJsonPath('message.body', 'Visible to sender');
        $page = $this->get(route('chat.conversations.show', $conversation))->assertOk();
        $page->assertSee('Visible to sender')
            ->assertSee('shield-chat__message mb-3 is-mine', false)
            ->assertDontSee('shield-chat__bubble rounded p-3 bg-white border', false);
    }

    public function test_history_remains_visible_but_sending_stops_after_participant_deactivation(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $this->actingAs($first)->postJson(route('chat.messages.store', $conversation), ['body' => 'Preserved'])->assertCreated();

        $second->update(['is_active' => false]);

        $this->actingAs($first)->get(route('chat.conversations.show', $conversation))
            ->assertOk()->assertSee('Preserved')->assertSee('conversation is read-only');
        $this->postJson(route('chat.messages.store', $conversation), ['body' => 'Blocked'])->assertForbidden();
        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_no_message_edit_or_delete_routes_exist(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $this->actingAs($first)->postJson(route('chat.messages.store', $conversation), ['body' => 'Immutable'])->assertCreated();
        $message = ChatMessage::query()->sole();

        $this->patch("/chat/conversations/{$conversation->id}/messages/{$message->id}", ['body' => 'Changed'])->assertNotFound();
        $this->delete("/chat/conversations/{$conversation->id}/messages/{$message->id}")->assertNotFound();

        foreach ([
            fn () => $message->update(['body' => 'Changed']),
            fn () => $message->delete(),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('An immutable chat message was changed.');
            } catch (LogicException $exception) {
                $this->assertSame('Chat messages are immutable.', $exception->getMessage());
            }
        }

        $this->assertSame('Immutable', $message->fresh()->body);
    }
}
