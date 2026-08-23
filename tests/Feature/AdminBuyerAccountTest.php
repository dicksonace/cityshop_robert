<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBuyerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_block_unblock_and_delete_buyer_releasing_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer,
            'email' => 'buyer@example.com',
            'mobile' => '0241234567',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/buyers/{$buyer->id}/block", [
            'reason' => 'Fraudulent activity reported',
        ])->assertOk();

        $buyer->refresh();
        $this->assertTrue($buyer->isBlocked());

        $this->postJson('/api/v1/auth/login', [
            'login' => 'buyer@example.com',
            'password' => 'password',
            'portal' => 'buyer',
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/buyers/{$buyer->id}/unblock")
            ->assertOk();

        $buyer->refresh();
        $this->assertFalse($buyer->isBlocked());

        $this->deleteJson("/api/v1/admin/buyers/{$buyer->id}", [
            'reason' => 'Buyer requested removal',
            'confirm_email' => 'buyer@example.com',
        ])->assertOk();

        $this->assertSoftDeleted('users', ['id' => $buyer->id]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Buyer',
            'mobile' => '0241234567',
            'email' => 'buyer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();
    }
}
