<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerAnnouncementAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function buyer(string $name = 'Buyer One'): User
    {
        return User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => $name,
        ]);
    }

    public function test_admin_can_message_one_buyer(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $other = $this->buyer('Other Buyer');

        $this->actingAs($admin)
            ->post(route('admin.buyer-announcements.store'), [
                'audience' => 'one',
                'title' => 'Delivery tip',
                'body' => 'Please keep your phone on for delivery.',
                'buyer_ids' => [$buyer->id],
                'send_email' => false,
            ])
            ->assertRedirect(route('admin.buyer-announcements.index'));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $buyer->id,
            'type' => 'admin_message',
            'title' => 'Delivery tip',
        ]);

        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $other->id,
            'type' => 'admin_message',
        ]);
    }

    public function test_admin_can_message_all_buyers(): void
    {
        $admin = $this->admin();
        $a = $this->buyer('Buyer A');
        $b = $this->buyer('Buyer B');
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->actingAs($admin)
            ->post(route('admin.buyer-announcements.store'), [
                'audience' => 'all',
                'title' => 'Promo weekend',
                'body' => 'Free delivery this weekend.',
                'buyer_ids' => [],
                'send_email' => false,
            ])
            ->assertRedirect(route('admin.buyer-announcements.index'));

        $this->assertSame(2, AppNotification::where('type', 'admin_message')->count());
        $this->assertDatabaseHas('app_notifications', ['user_id' => $a->id, 'title' => 'Promo weekend']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $b->id, 'title' => 'Promo weekend']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $seller->id, 'type' => 'admin_message']);
    }

    public function test_buyer_notifications_page_uses_shop_layout_flag(): void
    {
        $buyer = $this->buyer();

        AppNotification::create([
            'user_id' => $buyer->id,
            'type' => 'admin_message',
            'title' => 'Hello buyer',
            'body' => 'Welcome to CityShop',
            'data' => [],
        ]);

        $this->actingAs($buyer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('chat/notifications')
                ->where('layout', 'shop')
                ->has('notifications', 1));
    }
}
