<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatReplyToMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_reply_to_a_video_message(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Kofi amoah']);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Seller']);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        $video = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'type' => MessageType::Video,
            'body' => '',
            'metadata' => [
                'video_url' => 'https://cdn.example.com/chat/clip.mp4',
            ],
        ]);

        $this->actingAs($seller)
            ->postJson(route('chat.messages.store', $conversation), [
                'body' => 'Got your video, thanks.',
                'reply_to_id' => $video->id,
            ])
            ->assertOk()
            ->assertJsonPath('message.body', 'Got your video, thanks.')
            ->assertJsonPath('message.reply_to.id', $video->id)
            ->assertJsonPath('message.reply_to.body', 'Video')
            ->assertJsonPath('message.reply_to.sender_name', 'Kofi amoah');
    }

    public function test_seller_can_reply_to_a_product_card(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'name' => 'Buyer']);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        $productMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'type' => MessageType::Product,
            'body' => 'City Switch',
            'metadata' => [
                'product' => [
                    'id' => 1,
                    'name' => 'City Switch',
                    'slug' => 'city-switch',
                    'price' => 100,
                ],
            ],
        ]);

        $this->actingAs($seller)
            ->postJson(route('chat.messages.store', $conversation), [
                'body' => 'Yes, still available.',
                'reply_to_id' => $productMessage->id,
            ])
            ->assertOk()
            ->assertJsonPath('message.reply_to.body', 'City Switch');
    }
}
