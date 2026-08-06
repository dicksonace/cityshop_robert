<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiDeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_register_and_remove_a_device_token(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
            'device_name' => 'Pixel',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $buyer->id,
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ]);

        $this->deleteJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc',
        ])->assertOk();

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm-token-abc',
        ]);
    }

    public function test_app_notification_triggers_fcm_when_configured(): void
    {
        config(['services.fcm.server_key' => 'test-server-key']);
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['success' => 1], 200),
        ]);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        DeviceToken::create([
            'user_id' => $buyer->id,
            'token' => 'device-1',
            'platform' => 'android',
        ]);

        AppNotificationService::send($buyer, 'order', 'Order update', 'Your order shipped');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/fcm/send'
                && $request['to'] === 'device-1'
                && $request['notification']['title'] === 'Order update';
        });
    }

    public function test_push_is_skipped_without_fcm_server_key(): void
    {
        config(['services.fcm.server_key' => null]);
        Http::fake();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        DeviceToken::create([
            'user_id' => $buyer->id,
            'token' => 'device-1',
            'platform' => 'android',
        ]);

        PushNotificationService::sendToUser($buyer, 'Hello', 'World');

        Http::assertNothingSent();
    }
}
