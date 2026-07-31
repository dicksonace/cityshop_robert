<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_require_auth(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_buyer_sees_own_notifications_with_counts(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);

        AppNotificationService::send($buyer, 'order', 'Order placed', 'Order CS-1 was created.', ['order_id' => 7]);
        AppNotificationService::send($other, 'order', 'Not yours', 'Should not leak.');

        Sanctum::actingAs($buyer);

        $response = $this->getJson('/api/v1/notifications')->assertOk();

        $response->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Order placed')
            ->assertJsonPath('notifications.0.data.order_id', 7)
            ->assertJsonPath('unread_count', 1);

        $this->getJson('/api/v1/notifications/counts')
            ->assertOk()
            ->assertJsonPath('unread_notifications', 1);
    }

    public function test_buyer_can_mark_one_and_all_as_read(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $first = AppNotificationService::send($buyer, 'order_status', 'Shipped', 'On the way.');
        AppNotificationService::send($buyer, 'payment', 'Payment confirmed', 'Paid.');

        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertNotNull($first->fresh()->read_at);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, AppNotification::where('user_id', $buyer->id)->whereNull('read_at')->count());
    }

    public function test_buyer_cannot_mark_another_users_notification_read(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);
        $theirs = AppNotificationService::send($other, 'order', 'Theirs');

        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/notifications/{$theirs->id}/read")->assertForbidden();
        $this->assertNull($theirs->fresh()->read_at);
    }
}
