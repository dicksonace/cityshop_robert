<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PaymentPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiPaymentPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_set_four_digit_payment_pin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/payment-pin', [
            'pin' => '2468',
            'pin_confirmation' => '2468',
        ])
            ->assertOk()
            ->assertJsonPath('user.has_payment_pin', true);

        $this->assertTrue(PaymentPinService::hasPin($user->fresh()));
        $this->assertTrue(Hash::check('2468', (string) $user->fresh()->payment_pin));
    }

    public function test_rejects_trivial_payment_pin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/payment-pin', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ])->assertUnprocessable();
    }

    public function test_buyer_can_change_payment_pin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        PaymentPinService::set($user, '2468');
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile/payment-pin', [
            'current_pin' => '2468',
            'pin' => '3691',
            'pin_confirmation' => '3691',
        ])
            ->assertOk()
            ->assertJsonPath('user.has_payment_pin', true);

        $this->assertTrue(Hash::check('3691', (string) $user->fresh()->payment_pin));
    }

    public function test_buyer_can_reset_payment_pin_via_email_code(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'buyer@example.com',
        ]);
        PaymentPinService::set($user, '2468');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/payment-pin/forgot')
            ->assertOk()
            ->assertJsonStructure(['email_hint']);

        Notification::assertSentTo($user, \App\Notifications\PaymentPinResetCodeNotification::class);

        $notification = Notification::sent($user, \App\Notifications\PaymentPinResetCodeNotification::class)->first();
        $code = $notification->code;

        $this->postJson('/api/v1/profile/payment-pin/reset', [
            'code' => $code,
            'pin' => '5820',
            'pin_confirmation' => '5820',
        ])
            ->assertOk()
            ->assertJsonPath('user.has_payment_pin', true);

        $this->assertTrue(Hash::check('5820', (string) $user->fresh()->payment_pin));
    }

    public function test_me_exposes_has_payment_pin_not_raw_pin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        PaymentPinService::set($user, '2468');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.has_payment_pin', true)
            ->assertJsonMissingPath('user.payment_pin');
    }
}
