<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\UserRole;
use App\Models\KorapayPayment;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\KorapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletKorapayTopUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cityunlock.net',
            'services.korapay.public_key' => 'pk_live_cityshop',
            'services.korapay.secret_key' => 'sk_live_cityshop',
        ]);
        $this->app->forgetInstance(KorapayService::class);
    }

    private function verifiedBuyer(): User
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Ama Mensah',
            'mobile' => '0248620718',
        ]);
        KycVerification::create([
            'user_id' => $buyer->id,
            'ghana_card_number' => 'GHA-123456789-0',
            'full_name' => $buyer->name,
            'front_path' => 'kyc/front/a.jpg',
            'back_path' => 'kyc/back/b.jpg',
            'status' => KycStatus::Approved,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        return $buyer;
    }

    public function test_buyer_korapay_recharge_returns_checkout_link_and_stores_metadata(): void
    {
        $buyer = $this->verifiedBuyer();

        Http::fake([
            'https://api.korapay.com/merchant/api/v1/charges/initialize' => Http::response([
                'status' => true,
                'message' => 'Charge created successfully',
                'data' => ['checkout_url' => 'https://checkout.korapay.com/abc/pay'],
            ], 200),
        ]);

        $response = $this->actingAs($buyer)
            ->postJson(route('wallet.add-funds.korapay'), [
                'amount' => 20,
                'method' => 'momo',
            ])
            ->assertOk()
            ->assertJsonPath('authorization_url', 'https://checkout.korapay.com/abc/pay')
            ->assertJsonPath('gateway', 'korapay');

        $reference = $response->json('reference');
        $record = KorapayPayment::where('reference', $reference)->firstOrFail();
        $this->assertSame('wallet_topup', $record->meta['type']);
        $this->assertSame($buyer->id, $record->user_id);

        Http::assertSent(function ($request) use ($reference) {
            $payload = $request->data();

            return str_contains($request->url(), '/charges/initialize')
                && $request->hasHeader('Authorization', 'Bearer sk_live_cityshop')
                && ($payload['currency'] ?? null) === 'GHS'
                && ($payload['reference'] ?? null) === $reference
                && str_ends_with((string) ($payload['notification_url'] ?? ''), '/webhooks/korapay')
                && str_contains((string) ($payload['redirect_url'] ?? ''), '/wallet/korapay/callback');
        });
    }

    public function test_korapay_webhook_credits_wallet_once_after_verifying_charge(): void
    {
        $buyer = $this->verifiedBuyer();

        KorapayPayment::create([
            'reference' => 'KORA123456789',
            'user_id' => $buyer->id,
            'amount' => 20.39,
            'meta' => [
                'type' => 'wallet_topup',
                'user_id' => $buyer->id,
                'method' => 'momo',
                'wallet_credit' => 20,
                'gateway_fee' => 0.39,
                'expected_amount' => 20.39,
            ],
        ]);

        Http::fake([
            'https://api.korapay.com/merchant/api/v1/charges/KORA123456789' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'KORA123456789',
                    'status' => 'success',
                    'amount' => '20.39',
                    'currency' => 'GHS',
                ],
            ], 200),
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => ['reference' => 'KORA123456789', 'status' => 'success', 'amount' => 20.39, 'currency' => 'GHS'],
        ];

        $this->postJson('/webhooks/korapay', $payload, ['x-korapay-signature' => 'anything'])->assertOk();
        $this->postJson('/webhooks/korapay', $payload, ['x-korapay-signature' => 'anything'])->assertOk();

        $this->assertSame(1, WalletTransaction::where('reference', 'KORA123456789')->count());
        $this->assertEqualsWithDelta(20.0, (float) $buyer->wallet()->first()->available_balance, 0.001);
    }

    public function test_korapay_webhook_without_signature_is_rejected(): void
    {
        $this->postJson('/webhooks/korapay', ['event' => 'charge.success', 'data' => ['reference' => 'X']])
            ->assertStatus(400);
    }

    public function test_korapay_callback_credits_wallet_for_owner(): void
    {
        $buyer = $this->verifiedBuyer();

        KorapayPayment::create([
            'reference' => 'KORA999',
            'user_id' => $buyer->id,
            'amount' => 50,
            'meta' => [
                'type' => 'wallet_topup',
                'user_id' => $buyer->id,
                'method' => 'momo',
                'wallet_credit' => 50,
                'expected_amount' => 50,
            ],
        ]);

        Http::fake([
            'https://api.korapay.com/merchant/api/v1/charges/KORA999' => Http::response([
                'status' => true,
                'data' => ['reference' => 'KORA999', 'status' => 'success', 'amount' => '50.00', 'currency' => 'GHS'],
            ], 200),
        ]);

        $this->actingAs($buyer)
            ->get(route('wallet.korapay.callback', ['reference' => 'KORA999']))
            ->assertRedirect(route('wallet.index'));

        $this->assertSame(1, WalletTransaction::where('reference', 'KORA999')->count());
    }

    public function test_admin_app_lists_korapay_payments_and_recheck_credits_stuck_payment(): void
    {
        $buyer = $this->verifiedBuyer();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $payment = KorapayPayment::create([
            'reference' => 'KORA777',
            'user_id' => $buyer->id,
            'amount' => 30,
            'meta' => [
                'type' => 'wallet_topup',
                'user_id' => $buyer->id,
                'method' => 'momo',
                'wallet_credit' => 30,
                'expected_amount' => 30,
            ],
        ]);

        Http::fake([
            'https://api.korapay.com/merchant/api/v1/charges/KORA777' => Http::response([
                'status' => true,
                'data' => ['reference' => 'KORA777', 'status' => 'success', 'amount' => '30.00', 'currency' => 'GHS'],
            ], 200),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/korapay-payments?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.reference', 'KORA777')
            ->assertJsonPath('data.0.purpose', 'Wallet recharge')
            ->assertJsonPath('data.0.user.name', 'Ama Mensah');

        $this->postJson("/api/v1/admin/korapay-payments/{$payment->id}/recheck")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame(1, WalletTransaction::where('reference', 'KORA777')->count());

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/admin/korapay-payments')->assertForbidden();
    }
}
