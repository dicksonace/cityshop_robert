<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiUserBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_block_and_unblock_another_user(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);

        Sanctum::actingAs($me);

        $this->postJson('/api/v1/blocks', ['user_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $me->id,
            'blocked_id' => $other->id,
        ]);

        $this->postJson('/api/v1/messages', ['user_id' => $other->id])
            ->assertForbidden();

        $this->deleteJson("/api/v1/blocks/{$other->id}")
            ->assertOk();

        $this->assertDatabaseMissing('user_blocks', [
            'blocker_id' => $me->id,
            'blocked_id' => $other->id,
        ]);
    }
}
