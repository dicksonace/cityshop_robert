<?php

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletPaystackTopUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cityunlock.net',
            'services.paystack.secret_key' => 'sk_test_cityshop',
            'services.paystack.public_key' => 'pk_test_cityshop',
        ]);
        $this->app->forgetInstance(PaystackService::class);
    }

    public function test_billing_email_is_unique_per_cityshop_account(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => null,
            'mobile' => '0248620718',
        ]);

        $this->assertSame('cs'.$buyer->id.'@pay.cityunlock.net', $buyer->billingEmail());
        $this->assertStringNotContainsString('.local', $buyer->billingEmail());
    }

    public function test_billing_email_does_not_send_shared_gmail_to_paystack(): void
    {
        $shared = 'cityunlock.com@gmail.com';
        $kofi = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Kofi amoah',
            'email' => $shared,
            'mobile' => '0539790093',
        ]);

        $this->assertSame($shared, $kofi->contactEmail());
        $this->assertSame('cs'.$kofi->id.'@pay.cityunlock.net', $kofi->billingEmail());
        $this->assertNotSame($kofi->contactEmail(), $kofi->billingEmail());
    }

    public function test_contact_email_ignores_invalid_and_local_addresses(): void
    {
        $invalid = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'not-an-email',
            'mobile' => '0532700209',
        ]);
        $local = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => '0241112222@pay.cityshop.local',
            'mobile' => '0241112222',
        ]);

        $this->assertNull($invalid->contactEmail());
        $this->assertNull($local->contactEmail());
    }

    public function test_buyer_web_recharge_returns_paystack_url(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Kofi amoah',
            'email' => null,
            'mobile' => '0248620718',
        ]);

        $this->fakePaystackInitialize('https://checkout.paystack.com/buyer123', $buyer);

        $this->actingAs($buyer)
            ->postJson(route('wallet.add-funds'), [
                'amount' => 10,
                'method' => 'momo',
            ])
            ->assertOk()
            ->assertJsonPath('authorization_url', 'https://checkout.paystack.com/buyer123')
            ->assertJsonPath('email', 'cs'.$buyer->id.'@pay.cityunlock.net');

        Http::assertSent(function ($request) use ($buyer) {
            $payload = $request->data();

            return str_contains($request->url(), '/transaction/initialize')
                && ($payload['currency'] ?? null) === 'GHS'
                && ($payload['email'] ?? null) === 'cs'.$buyer->id.'@pay.cityunlock.net'
                && str_starts_with((string) ($payload['callback_url'] ?? ''), 'https://')
                && (int) ($payload['amount'] ?? 0) >= 1000
                && ($payload['metadata']['type'] ?? null) === 'wallet_topup'
                && ($payload['metadata']['account_name'] ?? null) === 'Kofi amoah';
        });
    }

    public function test_shared_gmail_syncs_kofi_name_to_paystack(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Kofi amoah',
            'email' => 'cityunlock.com@gmail.com',
            'mobile' => '0539790093',
        ]);

        $this->fakePaystackInitialize('https://checkout.paystack.com/kofi123', $buyer);

        $this->actingAs($buyer)
            ->postJson(route('wallet.add-funds'), [
                'amount' => 10,
                'method' => 'momo',
            ])
            ->assertOk()
            ->assertJsonPath('email', 'cs'.$buyer->id.'@pay.cityunlock.net');

        Http::assertSent(function ($request) use ($buyer) {
            if (! str_contains($request->url(), '/customer')) {
                return false;
            }

            $payload = $request->data();

            return ($payload['email'] ?? null) === 'cs'.$buyer->id.'@pay.cityunlock.net'
                && ($payload['first_name'] ?? null) === 'Kofi'
                && ($payload['last_name'] ?? null) === 'amoah';
        });
    }

    public function test_seller_web_recharge_returns_paystack_url(): void
    {
        $seller = $this->approvedSeller();

        $this->fakePaystackInitialize('https://checkout.paystack.com/seller123', $seller);

        $this->actingAs($seller)
            ->postJson(route('seller.wallet.add-funds'), [
                'amount' => 25,
                'method' => 'card',
            ])
            ->assertOk()
            ->assertJsonPath('authorization_url', 'https://checkout.paystack.com/seller123');

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/transaction/initialize')
                && ($payload['metadata']['role'] ?? null) === 'seller'
                && ($payload['metadata']['method'] ?? null) === 'card'
                && str_contains((string) ($payload['callback_url'] ?? ''), '/seller/wallet/callback');
        });
    }

    public function test_recharge_surfaces_paystack_error_message(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => false,
                'message' => 'Invalid email address',
            ], 400),
        ]);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $this->actingAs($buyer)
            ->postJson(route('wallet.add-funds'), [
                'amount' => 20,
                'method' => 'momo',
            ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Invalid email address');
    }

    public function test_api_recharge_returns_paystack_url(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'buyer.app@example.com',
        ]);
        $this->fakePaystackInitialize('https://checkout.paystack.com/app123', $buyer);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/paystack/initialize', [
            'amount' => 15,
            'method' => 'momo',
        ])
            ->assertOk()
            ->assertJsonPath('authorization_url', 'https://checkout.paystack.com/app123')
            ->assertJsonPath('currency', 'GHS');
    }

    private function fakePaystackInitialize(string $url, ?User $user = null): void
    {
        $responses = [
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => $url,
                    'access_code' => 'access_test',
                    'reference' => 'TOP-TEST',
                ],
            ], 200),
        ];

        if ($user) {
            $email = 'cs'.$user->id.'@pay.cityunlock.net';
            $responses['https://api.paystack.co/customer/'.$email] = Http::response([
                'status' => false,
                'message' => 'Customer not found',
            ], 404);
            $responses['https://api.paystack.co/customer'] = Http::response([
                'status' => true,
                'message' => 'Customer created',
                'data' => ['email' => $email],
            ], 200);
        }

        Http::fake($responses);
    }

    private function approvedSeller(): User
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'email' => 'eskimlitcenter1@gmail.com',
            'mobile' => '0532700209',
        ]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'City Unlock',
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

        return $seller;
    }
}
