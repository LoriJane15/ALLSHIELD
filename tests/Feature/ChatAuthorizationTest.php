<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChatConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ChatAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_supported_active_role_can_open_the_shared_chat(): void
    {
        foreach (array_keys(config('shield.roles')) as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('chat.index'))
                ->assertOk()
                ->assertSee('data-chat', false);
        }
    }

    public function test_guest_and_inactive_accounts_cannot_use_chat(): void
    {
        $this->get(route('chat.index'))->assertRedirect(route('login'));

        $inactive = User::factory()->create(['is_active' => false]);
        $this->actingAs($inactive)->get(route('chat.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_recipient_selector_excludes_self_inactive_and_config_unsupported_accounts(): void
    {
        $actor = User::factory()->role('lgu')->create(['name' => 'Current Chat User']);
        $eligible = User::factory()->role('admin')->create(['name' => 'Eligible Recipient']);
        $inactive = User::factory()->role('afp')->create(['name' => 'Inactive Recipient', 'is_active' => false]);
        $configuredOut = User::factory()->role('mblrc')->create(['name' => 'Configured Out Recipient']);

        Config::set('shield.roles', array_diff_key(config('shield.roles'), ['mblrc' => true]));

        $this->actingAs($actor)->get(route('chat.index'))
            ->assertOk()
            ->assertSee($eligible->name)
            ->assertDontSee('<option value="'.$actor->id.'"', false)
            ->assertDontSee($inactive->name)
            ->assertDontSee($configuredOut->name)
            ->assertDontSee($eligible->email)
            ->assertDontSee($eligible->username);
    }

    public function test_self_inactive_and_unsupported_recipients_are_rejected_generically(): void
    {
        $actor = User::factory()->role('lgu')->create();
        $inactive = User::factory()->role('admin')->create(['is_active' => false]);
        $configuredOut = User::factory()->role('mblrc')->create();

        $this->actingAs($actor)->post(route('chat.conversations.store'), ['recipient_id' => $actor->id])
            ->assertSessionHasErrors(['recipient_id']);
        $this->post(route('chat.conversations.store'), ['recipient_id' => $inactive->id])
            ->assertSessionHasErrors(['recipient_id']);

        Config::set('shield.roles', array_diff_key(config('shield.roles'), ['mblrc' => true]));
        $this->post(route('chat.conversations.store'), ['recipient_id' => $configuredOut->id])
            ->assertSessionHasErrors(['recipient_id']);

        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_only_participants_can_access_conversation_and_message_routes(): void
    {
        $first = User::factory()->role('lgu')->create();
        $second = User::factory()->role('admin')->create();
        $outsider = User::factory()->role('super_admin')->create();
        $conversation = app(ChatConversationService::class)->start($first, $second->id);

        $this->actingAs($first)->get(route('chat.conversations.show', $conversation))->assertOk();
        $this->actingAs($second)->get(route('chat.conversations.show', $conversation))->assertOk();

        $this->actingAs($outsider)->get(route('chat.conversations.show', $conversation))->assertNotFound();
        $this->get(route('chat.messages.index', [$conversation, 'after_id' => 0]))->assertNotFound();
        $this->post(route('chat.messages.store', $conversation), ['body' => 'Private'])->assertNotFound();
        $this->postJson(route('chat.conversations.read', $conversation), ['through_id' => 1])->assertNotFound();
    }

    public function test_super_administrator_has_no_private_conversation_override(): void
    {
        $users = User::factory()->count(2)->create();
        $conversation = app(ChatConversationService::class)->start($users[0], $users[1]->id);
        $superAdmin = User::factory()->role('super_admin')->create();

        $this->actingAs($superAdmin)->get(route('chat.conversations.show', $conversation))->assertNotFound();
    }
}
