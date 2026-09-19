<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\BuyerAccountService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuyerSelfDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_delete_account_when_wallet_is_clear(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'leave@example.com',
            'mobile' => '0248000111',
        ]);
        WalletService::ensure($buyer);

        $this->actingAs($buyer)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted($buyer);
    }

    public function test_buyer_cannot_delete_account_with_wallet_balance(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Wallet::create([
            'user_id' => $buyer->id,
            'available_balance' => 25.50,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
            'rmb_balance' => 0,
        ]);

        $this->actingAs($buyer)
            ->from('/settings/profile')
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertRedirect('/settings/profile')
            ->assertSessionHasErrors('account');

        $this->assertNotNull($buyer->fresh());
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_buyer_cannot_delete_account_with_processing_withdrawal(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        WalletService::ensure($buyer);
        Withdrawal::create([
            'user_id' => $buyer->id,
            'amount' => 20,
            'fee' => 0,
            'momo_number' => '0248000111',
            'account_name' => 'Buyer',
            'network' => 'mtn',
            'payout_channel' => 'momo',
            'status' => WithdrawalStatus::Processing,
        ]);

        $status = app(BuyerAccountService::class)->selfDeletionStatus($buyer);
        $this->assertFalse($status['can_delete']);
        $this->assertNotEmpty($status['blockers']);

        Sanctum::actingAs($buyer);
        $this->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');

        $this->assertNotNull($buyer->fresh());
    }

    public function test_api_buyer_can_delete_account_and_reregister(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'gone@example.com',
            'mobile' => '0248000222',
        ]);
        WalletService::ensure($buyer);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/profile/deletion')
            ->assertOk()
            ->assertJsonPath('can_delete', true);

        $this->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('message', 'Your CityShop account has been deleted.');

        $this->assertSoftDeleted($buyer);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Buyer',
            'mobile' => '0248000222',
            'email' => 'gone@example.com',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();
    }

    public function test_seller_cannot_delete_account_from_api(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/profile/deletion')->assertForbidden();
        $this->deleteJson('/api/v1/profile', ['password' => 'password'])->assertForbidden();
        $this->assertNotNull($seller->fresh());
    }
}
