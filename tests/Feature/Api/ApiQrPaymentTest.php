<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentPinService;
use App\Services\QrPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiQrPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_code_and_pay_with_pin(): void
    {
        $payer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Payer']);
        $payee = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Payee']);
        PaymentPinService::set($payer, '2468');

        Wallet::create([
            'user_id' => $payer->id,
            'available_balance' => 200,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);
        Wallet::create([
            'user_id' => $payee->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        Sanctum::actingAs($payee);
        $receive = $this->getJson('/api/v1/wallet/qr/receive?amount=25')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($receive['payload']);
        $this->assertSame(25.0, (float) $receive['amount']);

        Sanctum::actingAs($payer);
        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => $receive['payload']])
            ->assertOk()
            ->assertJsonPath('data.user.id', $payee->id)
            ->assertJsonPath('data.amount', 25);

        $this->postJson('/api/v1/wallet/qr/pay', [
            'payload' => $receive['payload'],
            'amount' => 25,
            'payment_pin' => '2468',
            'note' => 'Market stall',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 25)
            ->assertJsonPath('wallet.available_balance', 175);

        $this->assertSame(25.0, (float) Wallet::where('user_id', $payee->id)->value('available_balance'));

        $this->assertDatabaseHas('messages', [
            'type' => 'transfer',
            'sender_id' => $payer->id,
        ]);
        $this->assertTrue(
            \App\Models\Conversation::query()
                ->where(function ($q) use ($payer, $payee) {
                    $q->where('buyer_id', $payer->id)->where('seller_id', $payee->id);
                })
                ->orWhere(function ($q) use ($payer, $payee) {
                    $q->where('buyer_id', $payee->id)->where('seller_id', $payer->id);
                })
                ->exists()
        );

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $payee->id,
            'type' => 'payment',
            'title' => 'QR payment received',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $payer->id,
            'type' => 'payment',
            'title' => 'QR payment sent',
        ]);
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => 'not-a-code'])
            ->assertStatus(422);
    }

    public function test_cannot_pay_own_qr(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        PaymentPinService::set($user, '2468');
        Wallet::create([
            'user_id' => $user->id,
            'available_balance' => 50,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        $code = QrPaymentService::receiveCode($user);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => $code['payload']])
            ->assertStatus(422);
    }
}
