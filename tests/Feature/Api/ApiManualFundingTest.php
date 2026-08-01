<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiManualFundingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_funding_requires_auth(): void
    {
        $this->getJson('/api/v1/wallet/manual-funding')->assertUnauthorized();
    }

    public function test_payload_carries_the_accounts_the_app_renders(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Buyer]));

        $response = $this->getJson('/api/v1/wallet/manual-funding')->assertOk();

        $response->assertJsonPath('enabled', true)
            ->assertJsonStructure([
                'enabled',
                'instructions',
                'paystack_configured',
                'accounts' => [['type', 'label', 'account_name', 'account_number', 'network']],
                'requests',
            ]);

        $networks = collect($response->json('accounts'))->pluck('network');
        $this->assertTrue($networks->contains('mtn'));
    }

    public function test_buyer_sees_only_their_own_recent_requests(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);

        $mine = WalletTopUpRequest::create([
            'user_id' => $buyer->id,
            'amount' => 250,
            'payment_reference' => 'MP260731.1214.A12345',
            'network' => 'mtn',
            'proof_path' => 'wallet-top-up-proofs/mine.png',
            'status' => WalletTopUpStatus::Pending,
        ]);

        WalletTopUpRequest::create([
            'user_id' => $other->id,
            'amount' => 90,
            'payment_reference' => 'MP260731.0900.B99999',
            'network' => 'telecel',
            'proof_path' => 'wallet-top-up-proofs/theirs.png',
            'status' => WalletTopUpStatus::Approved,
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/wallet/manual-funding')
            ->assertOk()
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.id', $mine->id)
            ->assertJsonPath('requests.0.amount', 250)
            ->assertJsonPath('requests.0.status', 'pending')
            ->assertJsonPath('requests.0.payment_reference', 'MP260731.1214.A12345');
    }
}
