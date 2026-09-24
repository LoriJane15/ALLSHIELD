<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChatConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_navigation_renders_in_vertical_and_horizontal_layouts(): void
    {
        foreach (['lgu', 'admin', 'afp'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('chat.index'))
                ->assertOk()
                ->assertSee('Messages')
                ->assertSee('icon-bubbles', false)
                ->assertSee('data-chat-nav-badge', false)
                ->assertSee('data-chat', false);
        }
    }

    public function test_conversation_unread_badge_and_optional_document_selector_markers_render(): void
    {
        $first = User::factory()->role('lgu')->create();
        $second = User::factory()->role('admin')->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);

        $this->actingAs($first)->get(route('chat.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('data-conversation-unread', false)
            ->assertSee('data-read-url', false);
    }

    public function test_clear_conversation_and_message_empty_states_render(): void
    {
        $first = User::factory()->role('lgu')->create();
        $second = User::factory()->role('admin')->create();

        $this->actingAs($first)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('You have no conversations yet.')
            ->assertSee('Select an account to start a private conversation.');

        $conversation = app(ChatConversationService::class)->start($first, $second->id);
        $this->get(route('chat.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('No messages yet. Send the first message.');
    }

    public function test_all_supported_roles_receive_the_same_chat_interface_markers(): void
    {
        foreach (array_keys(config('shield.roles')) as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('chat.index'))
                ->assertOk()
                ->assertSee('data-start-conversation', false)
                ->assertSee('data-conversation-list', false)
                ->assertSee('Only you and the selected account can view its messages.');
        }
    }
}
