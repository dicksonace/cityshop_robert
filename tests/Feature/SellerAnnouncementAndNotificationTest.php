<?php

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerAnnouncementAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function approvedSeller(string $store = 'Notify Store'): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => $store,
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
        StoreCustomization::create([
            'seller_profile_id' => $profile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        return compact('seller', 'profile');
    }

    public function test_admin_can_message_one_seller(): void
    {
        $admin = $this->admin();
        ['seller' => $seller, 'profile' => $profile] = $this->approvedSeller();
        $other = $this->approvedSeller('Other Store');

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'audience' => 'one',
                'title' => 'Account review',
                'body' => 'Please update your MoMo details.',
                'seller_ids' => [$profile->id],
                'send_email' => false,
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $seller->id,
            'type' => 'admin_message',
            'title' => 'Account review',
        ]);

        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $other['seller']->id,
            'type' => 'admin_message',
        ]);
    }

    public function test_admin_can_message_all_sellers(): void
    {
        $admin = $this->admin();
        $a = $this->approvedSeller('Store A');
        $b = $this->approvedSeller('Store B');

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'audience' => 'all',
                'title' => 'Platform update',
                'body' => 'New delivery rules start Monday.',
                'seller_ids' => [],
                'send_email' => false,
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $this->assertSame(2, AppNotification::where('type', 'admin_message')->count());
        $this->assertDatabaseHas('app_notifications', ['user_id' => $a['seller']->id, 'title' => 'Platform update']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $b['seller']->id, 'title' => 'Platform update']);
    }

    public function test_seller_notifications_page_uses_seller_layout_flag(): void
    {
        ['seller' => $seller] = $this->approvedSeller();

        AppNotification::create([
            'user_id' => $seller->id,
            'type' => 'new_order',
            'title' => 'New order received',
            'body' => 'Order CS-1: Phone',
            'data' => ['order_id' => 1],
        ]);

        $this->actingAs($seller)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('chat/notifications')
                ->where('layout', 'seller')
                ->has('notifications', 1));
    }
}
