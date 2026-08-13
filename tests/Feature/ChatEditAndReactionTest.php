<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatEditAndReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sender_can_edit_own_text_within_15_minutes(): void
    {
        [$buyer, $seller, $conversation, $message] = $this->directText();

        $this->actingAs($buyer)
            ->patchJson(route('chat.messages.update', [$conversation, $message]), [
                'body' => 'Chat check if you add text Edit',
            ])
            ->assertOk()
            ->assertJsonPath('message.body', 'Chat check if you add text Edit')
            ->assertJsonPath('message.can_edit', true);

        $this->assertNotNull($message->fresh()->metadata['edited_at'] ?? null);
    }

    public function test_read_text_can_still_be_edited_within_window(): void
    {
        [$buyer, , $conversation, $message] = $this->directText();
        $message->update(['read_at' => now()]);

        $this->actingAs($buyer)
            ->patchJson(route('chat.messages.update', [$conversation, $message]), [
                'body' => 'Still editable after read',
            ])
            ->assertOk()
            ->assertJsonPath('message.body', 'Still editable after read');
    }

    public function test_old_text_cannot_be_edited(): void
    {
        [$buyer, , $conversation, $message] = $this->directText();
        $message->forceFill(['created_at' => now()->subMinutes(20)])->save();

        $this->actingAs($buyer)
            ->patchJson(route('chat.messages.update', [$conversation, $message]), [
                'body' => 'Too late',
            ])
            ->assertStatus(422);
    }

    public function test_anyone_in_chat_can_react_with_any_emoji(): void
    {
        [$buyer, $seller, $conversation, $message] = $this->directText();

        $this->actingAs($seller)
            ->postJson(route('chat.messages.react', [$conversation, $message]), [
                'emoji' => '😂',
            ])
            ->assertOk()
            ->assertJsonPath('message.reactions.0.emoji', '😂')
            ->assertJsonPath('message.reactions.0.count', 1)
            ->assertJsonPath('message.reactions.0.mine', true);

        $both = $this->actingAs($buyer)
            ->postJson(route('chat.messages.react', [$conversation, $message]), [
                'emoji' => '🔥',
            ])
            ->assertOk()
            ->json('message.reactions');
        $this->assertCount(2, $both);
        $this->assertEqualsCanonicalizing(['😂', '🔥'], array_column($both, 'emoji'));

        $this->actingAs($seller)
            ->postJson(route('chat.messages.react', [$conversation, $message]), [
                'emoji' => '😂',
            ])
            ->assertOk()
            ->assertJsonCount(1, 'message.reactions')
            ->assertJsonPath('message.reactions.0.emoji', '🔥');
    }

    public function test_api_can_edit_and_react(): void
    {
        [$buyer, $seller, $conversation, $message] = $this->directText();

        Sanctum::actingAs($buyer);
        $this->patchJson("/api/v1/messages/{$conversation->id}/messages/{$message->id}", [
            'body' => 'Edited from app',
        ])
            ->assertOk()
            ->assertJsonPath('message.body', 'Edited from app');
        $this->assertNotEmpty(
            $this->patchJson("/api/v1/messages/{$conversation->id}/messages/{$message->id}", [
                'body' => 'Edited from app again',
            ])->json('message.edited_at')
        );

        Sanctum::actingAs($seller);
        $this->postJson("/api/v1/messages/{$conversation->id}/messages/{$message->id}/react", [
            'emoji' => '🙏',
        ])
            ->assertOk()
            ->assertJsonPath('message.reactions.0.emoji', '🙏')
            ->assertJsonPath('message.reactions.0.mine', true);
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation, 3: Message}
     */
    private function directText(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi amoah']);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Robert']);
        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'type' => MessageType::Text,
            'body' => 'Original text',
            'metadata' => [],
        ]);

        return [$buyer, $seller, $conversation, $message];
    }
}
