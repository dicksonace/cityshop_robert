<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ChatService;
use App\Services\UserBlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatGroupAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_group_admin_can_remove_another_member(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Group Admin']);
        $member = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Robert']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Asare Kwame']);

        $group = ChatService::createGroup($admin, 'City unlock ventures', [$member->id, $buyer->id]);

        Sanctum::actingAs($member);
        $this->deleteJson("/api/v1/messages/{$group->id}/members/{$buyer->id}")
            ->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/messages/{$group->id}/members/{$buyer->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Member removed.');

        $this->assertFalse($group->fresh()->involves($buyer));
    }

    public function test_member_can_still_leave_group(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Seller]);
        $member = User::factory()->create(['role' => UserRole::Buyer]);

        $group = ChatService::createGroup($admin, 'Test group', [$member->id]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/messages/{$group->id}/leave")
            ->assertOk()
            ->assertJsonPath('message', 'You left the group.');

        $this->assertFalse($group->fresh()->involves($member));
    }

    public function test_group_admin_can_block_buyer_and_remove_them(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Kofi amoah']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Asare Kwame']);

        $group = ChatService::createGroup($admin, 'City unlock ventures', [$buyer->id]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/messages/{$group->id}/members/{$buyer->id}/block")
            ->assertOk()
            ->assertJsonPath('message', 'Buyer blocked and removed from the group.');

        $this->assertFalse($group->fresh()->involves($buyer));
        $this->assertTrue(UserBlockService::iBlocked($admin, $buyer));
    }

    public function test_non_admin_cannot_block_group_member(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Seller]);
        $member = User::factory()->create(['role' => UserRole::Buyer]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $group = ChatService::createGroup($admin, 'Test group', [$member->id, $buyer->id]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/messages/{$group->id}/members/{$buyer->id}/block")
            ->assertStatus(403);
    }
}
