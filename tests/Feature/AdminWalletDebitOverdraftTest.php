<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWalletDebitOverdraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_debit_past_zero_and_deposit_pays_it_down(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Robert Asare']);
        WalletService::ensure($buyer)->update(['available_balance' => 75]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/wallet-funding', [
            'user_id' => $buyer->id,
            'action' => 'debit',
            'currency' => 'GHS',
            'amount' => 200,
            'note' => 'Clawback',
        ])
            ->assertOk()
            ->assertJsonPath('available_balance', -125);

        $this->assertStringContainsString('overdrawn', (string) $response->json('message'));

        $this->assertEqualsWithDelta(
            -125.0,
            (float) WalletService::ensure($buyer->fresh())->available_balance,
            0.01,
        );

        WalletService::creditFromVerifiedTopUp(
            $buyer->id,
            200,
            'TEST-OVERDRAFT-TOPUP-1',
            'manual',
            notify: false,
        );

        $this->assertEqualsWithDelta(
            75.0,
            (float) WalletService::ensure($buyer->fresh())->available_balance,
            0.01,
        );
    }
}
