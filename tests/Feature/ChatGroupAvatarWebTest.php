<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatGroupAvatarWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_inbox_includes_group_avatar_for_sellers(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'City Unlock']);
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Robert']);
        $third = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);

        $conversation = ChatService::createGroup($seller, 'City unlock ventures', [$buyer->id, $third->id]);
        $conversation->forceFill(['avatar' => 'group-avatars/city-unlock.png'])->save();

        $this->actingAs($seller)
            ->getJson(route('chat.index'))
            ->assertOk()
            ->assertJsonPath('conversations.0.id', $conversation->id)
            ->assertJsonPath('conversations.0.avatar', 'group-avatars/city-unlock.png')
            ->assertJsonPath('conversations.0.other.avatar', 'group-avatars/city-unlock.png');

        $this->actingAs($seller)
            ->getJson(route('chat.poll', $conversation))
            ->assertOk()
            ->assertJsonPath('other.avatar', 'group-avatars/city-unlock.png');
    }
}
