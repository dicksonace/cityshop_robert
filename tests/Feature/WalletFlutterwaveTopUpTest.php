<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\UserRole;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletFlutterwaveTopUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cityunlock.net',
            'services.flutterwave.public_key' => 'FLWPUBK-cityshop-live-X',
            'services.flutterwave.secret_key' => 'FLWSECK-cityshop-live-X',
            'services.flutterwave.webhook_hash' => 'CityUnlockFlwWh2026',
        ]);
        $this->app->forgetInstance(FlutterwaveService::class);
    }

    public function test_buyer_flutterwave_recharge_sends_ghana_phone_like_rmb_wallet(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Ama Mensah',
            'email' => null,
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

        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/cityshop123'],
            ], 200),
        ]);

        $this->actingAs($buyer)
            ->postJson(route('wallet.add-funds.flutterwave'), [
                'amount' => 20,
                'method' => 'momo',
            ])
            ->assertOk()
            ->assertJsonPath('authorization_url', 'https://checkout.flutterwave.com/cityshop123')
            ->assertJsonPath('gateway', 'flutterwave');

        Http::assertSent(function ($request) use ($buyer) {
            $payload = $request->data();

            return str_contains($request->url(), '/payments')
                && ($payload['currency'] ?? null) === 'GHS'
                && ($payload['customer']['email'] ?? null) === $buyer->billingEmail()
                && ($payload['customer']['phone_number'] ?? null) === '233248620718'
                && str_starts_with((string) ($payload['tx_ref'] ?? ''), 'CITYSHOP-')
                && ($payload['meta']['type'] ?? null) === 'wallet_topup';
        });
    }
}
