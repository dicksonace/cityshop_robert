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
        $receive = $this->getJson('/api/v1/wallet/qr/receive?amount=25&reason='.urlencode('Market stall fee'))
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($receive['payload']);
        $this->assertSame('CS-'.$payee->id, $receive['code']);
        $this->assertSame(25.0, (float) $receive['amount']);
        $this->assertSame('Market stall fee', $receive['reason']);

        Sanctum::actingAs($payer);
        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => $receive['payload']])
            ->assertOk()
            ->assertJsonPath('data.user.id', $payee->id)
            ->assertJsonPath('data.amount', 25)
            ->assertJsonPath('data.reason', 'Market stall fee');

        $this->postJson('/api/v1/wallet/qr/pay', [
            'payload' => $receive['payload'],
            'amount' => 25,
            'payment_pin' => '2468',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 25)
            ->assertJsonPath('data.note', 'Market stall fee')
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

    public function test_old_expired_qr_still_resolves(): void
    {
        $payer = User::factory()->create(['role' => UserRole::Buyer]);
        $payee = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Payee']);
        Sanctum::actingAs($payer);

        $body = json_encode([
            'v' => 1,
            'u' => $payee->id,
            'n' => $payee->name,
            'e' => now()->subDays(30)->getTimestamp(),
        ], JSON_UNESCAPED_UNICODE);
        $encoded = rtrim(strtr(base64_encode((string) $body), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $encoded, (string) config('app.key'));
        $payload = QrPaymentService::PREFIX.'.'.$encoded.'.'.$sig;

        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('data.user.id', $payee->id);
    }

    public function test_resolve_accepts_short_cityshop_code(): void
    {
        $payer = User::factory()->create(['role' => UserRole::Buyer]);
        $payee = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);
        Sanctum::actingAs($payer);

        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => 'CS-'.$payee->id])
            ->assertOk()
            ->assertJsonPath('data.user.id', $payee->id)
            ->assertJsonPath('data.user.name', 'Ama')
            ->assertJsonPath('data.amount', null);
    }

    public function test_resolve_accepts_mobile_number(): void
    {
        $payer = User::factory()->create(['role' => UserRole::Buyer]);
        $payee = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Kofi',
            'mobile' => '0532700209',
        ]);
        Sanctum::actingAs($payer);

        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => '0532700209'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $payee->id)
            ->assertJsonPath('data.user.name', 'Kofi');
    }

    public function test_pay_with_short_code(): void
    {
        $payer = User::factory()->create(['role' => UserRole::Buyer]);
        $payee = User::factory()->create(['role' => UserRole::Buyer]);
        PaymentPinService::set($payer, '2468');

        Wallet::create([
            'user_id' => $payer->id,
            'available_balance' => 50,
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

        Sanctum::actingAs($payer);
        $this->postJson('/api/v1/wallet/qr/pay', [
            'payload' => 'CS-'.$payee->id,
            'amount' => 10,
            'note' => 'Code add',
            'payment_pin' => '2468',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 10)
            ->assertJsonPath('wallet.available_balance', 40);

        $this->assertSame(10.0, (float) Wallet::where('user_id', $payee->id)->value('available_balance'));
    }

    public function test_cannot_resolve_own_short_code(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/qr/resolve', ['payload' => 'CS-'.$user->id])
            ->assertStatus(422);
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
