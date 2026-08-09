<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\SellerFollow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSellerFollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_follow_and_unfollow_seller(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/following/toggle', ['seller_id' => $seller->id])
            ->assertOk()
            ->assertJsonPath('following', true);

        $this->assertDatabaseHas('seller_follows', [
            'follower_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        $this->getJson('/api/v1/following')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/following/status?seller_id='.$seller->id)
            ->assertOk()
            ->assertJsonPath('following', true);

        $this->postJson('/api/v1/following/toggle', ['seller_id' => $seller->id])
            ->assertOk()
            ->assertJsonPath('following', false);

        $this->assertDatabaseMissing('seller_follows', [
            'follower_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
    }

    public function test_seller_can_list_followers(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        SellerFollow::create([
            'follower_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/followers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_cannot_follow_self_or_non_seller(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $otherBuyer = User::factory()->create(['role' => UserRole::Buyer]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/following/toggle', ['seller_id' => $buyer->id])
            ->assertStatus(422);

        $this->postJson('/api/v1/following/toggle', ['seller_id' => $otherBuyer->id])
            ->assertStatus(422);
    }
}
