<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\StatusView;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiStatusViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_status_viewers(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Kofi']);
        $viewer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);

        $status = UserStatus::query()->create([
            'user_id' => $owner->id,
            'type' => 'text',
            'body' => 'Hello',
            'background_color' => '#EA580C',
            'expires_at' => now()->addDay(),
        ]);

        StatusView::query()->create([
            'user_status_id' => $status->id,
            'viewer_id' => $viewer->id,
            'viewed_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/status/{$status->id}/views")
            ->assertOk()
            ->assertJsonPath('data.view_count', 1)
            ->assertJsonPath('data.viewers.0.id', $viewer->id)
            ->assertJsonPath('data.viewers.0.name', 'Ama');
    }

    public function test_non_owner_cannot_list_status_viewers(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Seller]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);

        $status = UserStatus::query()->create([
            'user_id' => $owner->id,
            'type' => 'text',
            'body' => 'Hello',
            'background_color' => '#EA580C',
            'expires_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/status/{$status->id}/views")
            ->assertForbidden();
    }
}
