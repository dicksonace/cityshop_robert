<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerWalletWithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_request_momo_withdrawal_like_seller(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'name' => 'Kofi Buyer',
            'mobile' => '0244111222',
        ]);

        Wallet::create([
            'user_id' => $buyer->id,
            'available_balance' => 150,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        $this->actingAs($buyer)
            ->post(route('wallet.withdraw'), [
                'amount' => 50,
                'momo_number' => '0244111222',
                'account_name' => 'Kofi Buyer',
                'network' => 'mtn',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $buyer->id,
            'amount' => 50,
            'momo_number' => '0244111222',
            'network' => 'mtn',
            'status' => WithdrawalStatus::Pending->value,
        ]);

        $this->assertEquals(100.0, (float) Wallet::where('user_id', $buyer->id)->value('available_balance'));
    }

    public function test_buyer_wallet_page_includes_withdrawal_section(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Wallet::create([
            'user_id' => $buyer->id,
            'available_balance' => 80,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 20,
            'momo_number' => '0555000111',
            'account_name' => 'Buyer',
            'network' => 'telecel',
            'status' => WithdrawalStatus::Pending,
        ]);

        $this->actingAs($buyer)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/wallet')
                ->where('hasPendingWithdrawal', true)
                ->has('withdrawals.data', 1)
                ->where('wallet.available_balance', '80.00'));
    }
}
