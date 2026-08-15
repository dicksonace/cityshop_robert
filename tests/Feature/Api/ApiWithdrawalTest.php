<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Channels\SmsChannel;
use App\Notifications\AdminWithdrawalRequestedNotification;
use App\Notifications\WithdrawalRequestedNotification;
use App\Services\PaymentPinService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            ->assertJsonPath('summary.default_account_name', null)
            ->assertJsonPath('summary.banks.0.id', 'absa')
            ->assertJsonPath('summary.withdrawal_fee.amount', 10)
            ->assertJsonPath('summary.withdrawal_fee.momo_amount', 0)
            ->assertJsonPath('summary.withdrawal_fee.applies_to', 'bank')
            ->assertJsonPath('summary.withdrawal_fee.bank_tiers.0.min', 10)
            ->assertJsonPath('summary.withdrawal_fee.bank_tiers.0.max', 999.99)
            ->assertJsonPath('summary.withdrawal_fee.bank_tiers.0.fee', 10)
            ->assertJsonPath('summary.withdrawal_fee.bank_tiers.1.min', 1000)
            ->assertJsonPath('summary.withdrawal_fee.bank_tiers.1.max', 25000)
            ->assertJsonPath('summary.withdrawal_fee.bank_tiers.1.fee', 20)
            ->assertJsonCount(0, 'data');
    }

    public function test_a_request_debits_the_available_balance_and_writes_the_ledger(): void
    {
        Notification::fake();
        $adminA = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin One']);
        $adminB = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin Two']);
        $buyer = $this->buyerWithBalance(500);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 120,
            'network' => 'mtn',
        ]))->assertCreated();

        $response->assertJsonPath('data.amount', 120)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Processing')
            ->assertJsonPath('data.network_label', 'MTN Mobile Money')
            ->assertJsonPath('data.payout_type', 'momo')
            ->assertJsonPath('wallet.available_balance', 380);

        $withdrawal = Withdrawal::where('user_id', $buyer->id)->sole();
        $this->assertSame('0539790093', $withdrawal->momo_number);
        $this->assertSame('momo', $withdrawal->payout_channel);
        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);
        $this->assertSame(380.0, (float) $buyer->wallet->fresh()->available_balance);

        $entry = WalletTransaction::where('user_id', $buyer->id)->sole();
        $this->assertSame(WalletTransactionType::Withdrawal, $entry->type);
        $this->assertSame(-120.0, (float) $entry->amount);
        $this->assertSame("WD-{$withdrawal->id}", $entry->reference);

        Notification::assertSentTo($buyer, WithdrawalRequestedNotification::class);
        Notification::assertSentTo($adminA, AdminWithdrawalRequestedNotification::class);
        Notification::assertSentTo($adminB, AdminWithdrawalRequestedNotification::class);
        Notification::assertSentTo($adminA, AdminWithdrawalRequestedNotification::class, function ($notification, $channels) {
            return in_array('mail', $channels, true)
                && in_array(SmsChannel::class, $channels, true);
        });
    }

    public function test_bank_withdrawal_is_accepted(): void
    {
        $buyer = $this->buyerWithBalance(500);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 80,
            'payout_type' => 'bank',
            'network' => 'gcb',
            'momo_number' => '1234567890',
            'account_name' => 'Kofi Amoah',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.payout_type', 'bank')
            ->assertJsonPath('data.network_label', 'GCB')
            ->assertJsonPath('data.momo_number', '1234567890')
            ->assertJsonPath('data.fee', 10)
            ->assertJsonPath('data.total_debited', 90)
            ->assertJsonPath('wallet.available_balance', 410);

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $buyer->id,
            'network' => 'gcb',
            'payout_channel' => 'bank',
            'momo_number' => '1234567890',
            'fee' => 10,
        ]);
    }

    public function test_bank_withdrawal_uses_higher_fee_band_for_large_amounts(): void
    {
        $buyer = $this->buyerWithBalance(20000);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 15000,
            'payout_type' => 'bank',
            'network' => 'gcb',
            'momo_number' => '1234567890',
            'account_name' => 'Kofi Amoah',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.fee', 20)
            ->assertJsonPath('data.total_debited', 15020)
            ->assertJsonPath('wallet.available_balance', 4980);
    }

    public function test_bank_withdrawal_uses_higher_fee_above_first_band(): void
    {
        $buyer = $this->buyerWithBalance(6000);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 5000,
            'payout_type' => 'bank',
            'network' => 'gcb',
            'momo_number' => '1234567890',
            'account_name' => 'Kofi Amoah',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.fee', 20)
            ->assertJsonPath('data.total_debited', 5020);
    }

    public function test_bank_withdrawal_from_one_thousand_uses_twenty_cedi_fee(): void
    {
        $buyer = $this->buyerWithBalance(2000);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 1500,
            'payout_type' => 'bank',
            'network' => 'gcb',
            'momo_number' => '1234567890',
            'account_name' => 'Kofi Amoah',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.fee', 20)
            ->assertJsonPath('data.total_debited', 1520);
    }

    public function test_bank_withdrawal_of_exactly_one_thousand_uses_twenty_cedi_fee(): void
    {
        $buyer = $this->buyerWithBalance(1200);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 1000,
            'payout_type' => 'bank',
            'network' => 'gcb',
            'momo_number' => '1234567890',
            'account_name' => 'Kofi Amoah',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.fee', 20)
            ->assertJsonPath('data.total_debited', 1020);
    }

    public function test_the_minimum_is_ten_cedis(): void
    {
        Sanctum::actingAs($this->buyerWithBalance(500));

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 9.99,
        ]))->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_an_unknown_network_is_rejected(): void
    {
        Sanctum::actingAs($this->buyerWithBalance(500));

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 50,
            'network' => 'vodafone',
        ]))->assertStatus(422)->assertJsonValidationErrors('network');
    }

    public function test_a_buyer_cannot_withdraw_more_than_the_available_balance(): void
    {
        $buyer = $this->buyerWithBalance(40);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 60,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient available balance.');

        $this->assertSame(0, Withdrawal::where('user_id', $buyer->id)->count());
        $this->assertSame(40.0, (float) $buyer->wallet->fresh()->available_balance);
    }

    public function test_pending_balance_cannot_be_withdrawn(): void
    {
        $buyer = $this->buyerWithBalance(10, pending: 900);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 500,
        ]))->assertStatus(422)->assertJsonPath('message', 'Insufficient available balance.');
    }

    public function test_another_withdrawal_is_allowed_while_one_is_processing(): void
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

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 50,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.amount', 50);

        $this->assertDatabaseCount('withdrawals', 2);

        $this->getJson('/api/v1/wallet/withdrawals')
            ->assertOk()
            ->assertJsonPath('summary.has_pending', true)
            ->assertJsonPath('summary.available_balance', 450);
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

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 50,
        ]))->assertCreated();
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
            ->assertJsonPath('data.1.status_label', 'Completed')
            ->assertJsonPath('data.1.network_label', 'Telecel Cash');
    }

    public function test_wallet_history_keeps_one_receipt_that_becomes_completed(): void
    {
        $buyer = $this->buyerWithBalance(500);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/wallet/withdraw', $this->withdrawPayload([
            'amount' => 12,
        ]))->assertCreated();

        $withdrawal = Withdrawal::where('user_id', $buyer->id)->sole();

        $this->getJson('/api/v1/wallet/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'withdrawal')
            ->assertJsonPath('data.0.type_label', 'Withdrawal · Processing')
            ->assertJsonPath('data.0.reference', 'WD-'.$withdrawal->id)
            ->assertJsonPath('data.0.amount', -12);

        app(\App\Services\WithdrawalPayoutService::class)->markAsPaid($withdrawal->fresh(), 'manual');

        $this->assertSame(
            1,
            WalletTransaction::where('user_id', $buyer->id)->where('reference', 'WD-'.$withdrawal->id)->count(),
        );
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $buyer->id,
            'type' => WalletTransactionType::WithdrawalCompleted->value,
        ]);

        $this->getJson('/api/v1/wallet/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'withdrawal')
            ->assertJsonPath('data.0.type_label', 'Withdrawal · Completed')
            ->assertJsonPath('data.0.reference', 'WD-'.$withdrawal->id)
            ->assertJsonPath('data.0.amount', -12);

        $this->getJson('/api/v1/wallet/withdrawals')
            ->assertOk()
            ->assertJsonPath('data.0.status_label', 'Completed');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function withdrawPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 50,
            'payout_type' => 'momo',
            'momo_number' => '0539790093',
            'account_name' => 'Kofi Amoah',
            'network' => 'mtn',
            'payment_pin' => '2468',
        ], $overrides);
    }

    private function buyerWithBalance(float $available, float $pending = 0): User
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        WalletService::ensure($buyer);
        PaymentPinService::set($buyer, '2468');

        Wallet::where('user_id', $buyer->id)->update([
            'available_balance' => $available,
            'pending_balance' => $pending,
        ]);

        return $buyer->fresh();
    }
}
