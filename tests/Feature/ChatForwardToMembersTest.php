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

    public function test_buyer_can_forward_from_direct_chat_to_group_contacts(): void
    {
        $me = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi']);
        $friend = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Ama']);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'City Unlock']);
        $outsider = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Outsider']);

        ChatService::createGroup($me, 'Buyer friends', [$friend->id]);
        $direct = ChatService::findOrCreateConversation($me, $seller);
        $message = ChatService::sendMessage($direct, $seller, 'New stock in store', MessageType::Text);

        Sanctum::actingAs($me);

        $this->getJson('/api/v1/messages/forward-targets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $friend->id);

        $this->postJson("/api/v1/messages/{$direct->id}/messages/{$message->id}/forward", [
            'member_ids' => [$friend->id],
        ])
            ->assertOk()
            ->assertJsonPath('sent', 1);

        $friendChat = ChatService::findOrCreateConversation($me, $friend);
        $this->assertTrue(
            Message::query()
                ->where('conversation_id', $friendChat->id)
                ->where('sender_id', $me->id)
                ->get()
                ->contains(fn (Message $row) => $row->body === 'New stock in store')
        );

        $this->postJson("/api/v1/messages/{$direct->id}/messages/{$message->id}/forward", [
            'member_ids' => [$outsider->id],
        ])
            ->assertStatus(422);
    }

    public function test_forward_from_direct_chat_requires_a_group_contact(): void
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
