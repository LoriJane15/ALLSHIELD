<?php

namespace Tests\Feature;

use App\Models\GovAgency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TopbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_vertical_topbar_uses_each_accounts_public_storage_image_and_preserves_fallback(): void
    {
        Storage::fake('public');

        $first = User::factory()->role('lgu')->create([
            'name' => 'First LGU Account',
            'logo' => 'logos/first-account.png',
        ]);
        $second = User::factory()->role('mblrc')->create([
            'name' => 'Second Account',
            'logo' => 'logos/second-account.png',
        ]);
        $withoutImage = User::factory()->role('pswdo')->create([
            'name' => 'Fallback Account',
            'logo' => null,
        ]);
        Storage::disk('public')->put($first->logo, 'first image');
        Storage::disk('public')->put($second->logo, 'second image');

        $firstUrl = Storage::disk('public')->url($first->logo);
        $secondUrl = Storage::disk('public')->url($second->logo);

        $this->actingAs($first)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('src="'.$firstUrl.'"', false)
            ->assertDontSee('src="'.$secondUrl.'"', false)
            ->assertSee('First LGU Account')
            ->assertSee('user-status-dot', false);

        $this->actingAs($second)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('src="'.$secondUrl.'"', false)
            ->assertDontSee('src="'.$firstUrl.'"', false);

        $this->actingAs($withoutImage)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('src="'.asset('assets/img/kc-logo.svg').'"', false)
            ->assertSee('Fallback Account')
            ->assertSee('user-avatar-img', false)
            ->assertSee('user-status-dot', false);
    }

    public function test_government_agency_topbar_reuses_the_linked_agency_profile(): void
    {
        $agency = GovAgency::create([
            'name' => 'Department of Test Services',
            'acronym' => 'DTS',
            'profile' => 'DTS.png',
        ]);
        $user = User::factory()->govAgency($agency->id)->create([
            'name' => 'DTS Account',
            'logo' => null,
        ]);

        $this->actingAs($user)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('src="'.asset('assets/logoAgency/DTS.png').'"', false)
            ->assertSee('DTS Account');
    }

    public function test_horizontal_topbar_replaces_messages_with_notifications_but_keeps_sidebar_messages(): void
    {
        $user = User::factory()->role('admin')->create();

        $this->actingAs($user)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('notification-indicator-btn', false)
            ->assertSee('mdi-bell-outline', false)
            ->assertSee('title="Notifications"', false)
            ->assertDontSee('title="Private Messages"', false)
            ->assertSee('<span class="menu-title">Messages</span>', false)
            ->assertSee('data-chat-nav-badge', false)
            ->assertDontSee('data-notification-badge', false);
    }

    public function test_notification_badge_uses_only_the_authenticated_users_real_unread_records(): void
    {
        $viewer = User::factory()->role('japic')->create();
        $other = User::factory()->role('japic')->create();
        $this->insertNotification($viewer);
        $this->insertNotification($other);
        $this->insertNotification($viewer, read: true);

        $this->actingAs($viewer)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('href="'.route('japic.dashboard').'#notifications-heading"', false)
            ->assertSee('data-notification-badge', false)
            ->assertSee('aria-label="1 unread notifications"', false);

        DB::table('notifications')
            ->where('notifiable_type', $viewer->getMorphClass())
            ->where('notifiable_id', $viewer->id)
            ->update(['read_at' => now()]);

        $this->actingAs($viewer->fresh())->get(route('chat.index'))
            ->assertOk()
            ->assertDontSee('data-notification-badge', false);
    }

    public function test_topbar_fails_closed_when_an_isolated_database_has_no_notifications_table(): void
    {
        $user = User::factory()->role('admin')->create();
        Schema::drop('notifications');

        $this->actingAs($user)->get(route('chat.index'))
            ->assertOk()
            ->assertSee('notification-indicator-btn', false)
            ->assertDontSee('data-notification-badge', false);
    }

    private function insertNotification(User $user, bool $read = false): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\TopbarNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => '{}',
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
