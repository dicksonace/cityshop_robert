<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/wallet/withdrawals')->assertUnauthorized();
        $this->postJson('/api/v1/wallet/withdraw')->assertUnauthorized();
    }

    public function test_summary_reports_what_the_screen_needs(): void
    {
        $buyer = $this->buyerWithBalance(340.50);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/wallet/withdrawals')
            ->assertOk()
            ->assertJsonPath('summary.available_balance', 340.5)
            ->assertJsonPath('summary.minimum', 10)
            ->assertJsonPath('summary.has_pending', false)
            ->assertJsonPath('summary.default_momo_number', $buyer->mobile)
            ->assertJsonPath('summary.default_account_name', $buyer->name)
            ->assertJsonCount(0, 'data');
    }

    public function test_a_request_debits_the_available_balance_and_writes_the_ledger(): void
    {
        $buyer = $this->buyerWithBalance(500);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 120,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
        ])->assertCreated();

        $response->assertJsonPath('data.amount', 120)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Processing')
            ->assertJsonPath('data.network_label', 'MTN Mobile Money')
            ->assertJsonPath('wallet.available_balance', 380);

        $withdrawal = Withdrawal::where('user_id', $buyer->id)->sole();
        $this->assertSame('0539790093', $withdrawal->momo_number);
        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);
        $this->assertSame(380.0, (float) $buyer->wallet->fresh()->available_balance);

        $entry = WalletTransaction::where('user_id', $buyer->id)->sole();
        $this->assertSame(WalletTransactionType::Withdrawal, $entry->type);
        $this->assertSame(-120.0, (float) $entry->amount);
        $this->assertSame("WD-{$withdrawal->id}", $entry->reference);
    }

    public function test_the_minimum_is_ten_cedis(): void
    {
        Sanctum::actingAs($this->buyerWithBalance(500));

        $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 9.99,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_an_unknown_network_is_rejected(): void
    {
        Sanctum::actingAs($this->buyerWithBalance(500));

        $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 50,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'vodafone',
        ])->assertStatus(422)->assertJsonValidationErrors('network');
    }

    public function test_a_buyer_cannot_withdraw_more_than_the_available_balance(): void
    {
        $buyer = $this->buyerWithBalance(40);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 60,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient available balance.');

        $this->assertSame(0, Withdrawal::where('user_id', $buyer->id)->count());
        $this->assertSame(40.0, (float) $buyer->wallet->fresh()->available_balance);
    }

    public function test_pending_balance_cannot_be_withdrawn(): void
    {
        $buyer = $this->buyerWithBalance(10, pending: 900);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 500,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
        ])->assertStatus(422)->assertJsonPath('message', 'Insufficient available balance.');
    }

    public function test_only_one_request_can_be_open_at_a_time(): void
    {
        $buyer = $this->buyerWithBalance(500);

        Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 30,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
            'status' => WithdrawalStatus::Processing,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 50,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'You already have a withdrawal in processing. Please wait for it to complete.',
            );

        $this->getJson('/api/v1/wallet/withdrawals')
            ->assertOk()
            ->assertJsonPath('summary.has_pending', true);
    }

    public function test_a_settled_request_does_not_block_the_next_one(): void
    {
        $buyer = $this->buyerWithBalance(500);

        Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 30,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'telecel',
            'status' => WithdrawalStatus::Paid,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', [
            'amount' => 50,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
        ])->assertCreated();
    }

    public function test_history_is_newest_first_and_private_to_the_buyer(): void
    {
        $buyer = $this->buyerWithBalance(500);
        $other = $this->buyerWithBalance(500);

        Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 25,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'telecel',
            'status' => WithdrawalStatus::Paid,
        ]);
        Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 75,
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
            'status' => WithdrawalStatus::Rejected,
            'rejection_reason' => 'Name did not match the MoMo account.',
        ]);
        Withdrawal::create([
            'user_id' => $other->id,
            'amount' => 99,
            'momo_number' => '0201111111',
            'account_name' => 'Someone Else',
            'network' => 'mtn',
            'status' => WithdrawalStatus::Paid,
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/wallet/withdrawals')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.amount', 75)
            ->assertJsonPath('data.0.status_label', 'Rejected')
            ->assertJsonPath('data.0.rejection_reason', 'Name did not match the MoMo account.')
            ->assertJsonPath('data.1.amount', 25)
            ->assertJsonPath('data.1.status_label', 'Paid out')
            ->assertJsonPath('data.1.network_label', 'Telecel Cash');
    }

    private function buyerWithBalance(float $available, float $pending = 0): User
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        WalletService::ensure($buyer);

        Wallet::where('user_id', $buyer->id)->update([
            'available_balance' => $available,
            'pending_balance' => $pending,
        ]);

        return $buyer->fresh();
    }
}
