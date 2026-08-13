<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiWalletTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_require_auth(): void
    {
        $this->getJson('/api/v1/wallet/transactions')->assertUnauthorized();
    }

    public function test_buyer_sees_own_ledger_newest_first_with_running_balances(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);

        $wallet = WalletService::ensure($buyer);
        $wallet->update(['available_balance' => 200, 'pending_balance' => 0]);

        WalletTransactionService::record(
            userId: $buyer->id,
            type: WalletTransactionType::FundAdded,
            amount: 300,
            description: 'Funds added via momo',
            reference: 'TOP-ABC123',
        );

        WalletTransactionService::record(
            userId: $buyer->id,
            type: WalletTransactionType::OrderPayment,
            amount: -100,
            description: 'Order payment (Checkout CS-1)',
        );

        WalletTransactionService::record(
            userId: $other->id,
            type: WalletTransactionType::FundAdded,
            amount: 50,
            description: 'Not yours',
        );

        Sanctum::actingAs($buyer);

        $response = $this->getJson('/api/v1/wallet/transactions')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.type', 'order_payment')
            ->assertJsonPath('data.0.type_label', 'Order Payment')
            ->assertJsonPath('data.0.amount', -100)
            ->assertJsonPath('data.0.balance_before', 300)
            ->assertJsonPath('data.0.balance_after', 200)
            ->assertJsonPath('data.1.type_label', 'Funds Credited')
            ->assertJsonPath('data.1.reference', 'TOP-ABC123')
            ->assertJsonPath('data.1.balance_before', 0)
            ->assertJsonPath('data.1.balance_after', 300);
    }

    public function test_ledger_is_paginated(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        WalletService::ensure($buyer);

        foreach (range(1, 5) as $i) {
            WalletTransactionService::record(
                userId: $buyer->id,
                type: WalletTransactionType::FundAdded,
                amount: 10,
                description: "Top up {$i}",
            );
        }

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/wallet/transactions?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 2);
    }
}
