<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatForwardToMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_member_can_forward_a_message_to_selected_members(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi']);
        $ama = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);
        $yaw = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Yaw']);
        $outsider = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Outsider']);

        $group = ChatService::createGroup($me, 'City unlock ventures', [$ama->id, $yaw->id]);
        $original = ChatService::sendMessage($group, $me, 'Check this receipt', MessageType::Text);

        Sanctum::actingAs($me);

        $this->postJson("/api/v1/messages/{$group->id}/messages/{$original->id}/forward", [
            'member_ids' => [$ama->id, $outsider->id],
        ])
            ->assertOk()
            ->assertJsonPath('sent', 1);

        $direct = ChatService::findOrCreateConversation($me, $ama);
        $this->assertTrue(
            Message::query()
                ->where('conversation_id', $direct->id)
                ->where('sender_id', $me->id)
                ->where('type', MessageType::Text)
                ->get()
                ->contains(fn (Message $message) => $message->body === 'Check this receipt')
        );

        $this->postJson("/api/v1/messages/{$group->id}/messages/{$original->id}/forward", [
            'member_ids' => [$outsider->id],
        ])
            ->assertStatus(422);
    }

    public function test_forward_is_not_available_in_direct_chats(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer]);
        $peer = User::factory()->create(['role' => UserRole::Seller]);
        $direct = ChatService::findOrCreateConversation($me, $peer);
        $message = ChatService::sendMessage($direct, $me, 'Hello', MessageType::Text);

        Sanctum::actingAs($me);

        $this->postJson("/api/v1/messages/{$direct->id}/messages/{$message->id}/forward", [
            'member_ids' => [$peer->id],
        ])
            ->assertStatus(422);
    }
}
