<?php

namespace Tests\Feature;

use App\Enums\LivestreamStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Livestream;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LivestreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_seller_can_go_live_and_buyers_can_see_it(): void
    {
        [$seller] = $this->approvedSeller();
        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/livestreams/start', ['title' => 'Evening deals'])
            ->assertCreated()
            ->assertJsonPath('livestream.title', 'Evening deals');

        $this->assertDatabaseHas('livestreams', [
            'seller_id' => $seller->id,
            'status' => LivestreamStatus::Live->value,
            'title' => 'Evening deals',
        ]);

        $this->getJson('/api/v1/livestreams')
            ->assertOk()
            ->assertJsonPath('data.0.store_slug', $seller->sellerProfile->slug)
            ->assertJsonPath('data.0.title', 'Evening deals');

        $this->getJson('/api/v1/livestreams/'.$seller->sellerProfile->slug)
            ->assertOk()
            ->assertJsonPath('data.room.room_name', Livestream::query()->first()->room_name);

        $this->getJson('/api/v1/stores/'.$seller->sellerProfile->slug)
            ->assertOk()
            ->assertJsonPath('data.is_live', true)
            ->assertJsonPath('data.livestream.room.room_name', Livestream::query()->first()->room_name);
    }

    public function test_buyer_cannot_start_a_livestream(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/livestreams/start')
            ->assertForbidden();
    }

    public function test_ending_live_hides_the_store_from_live_now(): void
    {
        [$seller] = $this->approvedSeller();
        Sanctum::actingAs($seller);
        $this->postJson('/api/v1/livestreams/start')->assertCreated();

        $this->postJson('/api/v1/livestreams/end')->assertOk();

        $this->getJson('/api/v1/livestreams')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** @return array{0: User} */
    private function approvedSeller(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Live Store',
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

        return [$seller->fresh()];
    }
}
