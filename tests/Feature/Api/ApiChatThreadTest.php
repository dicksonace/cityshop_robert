<?php

namespace Tests\Feature\Api;

use App\Enums\MessageType;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Voice calls write their WebRTC handshake into the messages table. Those rows
 * must never reach the app, or the buyer sees a row of blank chat bubbles.
 */
class ApiChatThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_thread_leaves_out_call_signalling(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        $this->message($conversation, $buyer, MessageType::Text, 'Is the Honda still there?');
        $this->message($conversation, $seller, MessageType::CallOffer, 'Voice call');
        $this->message($conversation, $seller, MessageType::CallIce, '');
        $this->message($conversation, $buyer, MessageType::CallAnswer, '');
        $this->message($conversation, $seller, MessageType::CallEnd, '');
        $this->message($conversation, $seller, MessageType::CallLog, 'Voice call');
        $this->message($conversation, $seller, MessageType::Image, '', [
            'image_url' => 'https://cdn.example.com/chat/honda.jpg',
        ]);

        Sanctum::actingAs($buyer);

        $types = collect($this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->json('messages'))
            ->pluck('type');

        $this->assertSame(['text', 'call_log', 'image'], $types->all());
    }

    public function test_polling_leaves_out_call_signalling_too(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        $first = $this->message($conversation, $buyer, MessageType::Text, 'Hello');
        $this->message($conversation, $seller, MessageType::CallIce, '');
        $this->message($conversation, $seller, MessageType::Text, 'Yes it is available');

        Sanctum::actingAs($buyer);

        $messages = $this->getJson("/api/v1/messages/{$conversation->id}/poll?after={$first->id}")
            ->assertOk()
            ->json('messages');

        $this->assertCount(1, $messages);
        $this->assertSame('Yes it is available', $messages[0]['body']);
    }

    public function test_the_preview_and_unread_tally_ignore_signalling(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        $this->message($conversation, $seller, MessageType::Text, 'Yes it is available');
        $this->message($conversation, $seller, MessageType::CallOffer, 'Voice call');
        $this->message($conversation, $seller, MessageType::CallIce, '');

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/messages')
            ->assertOk()
            ->assertJsonPath('data.0.latest_message.body', 'Yes it is available')
            ->assertJsonPath('data.0.latest_message.type', 'text')
            ->assertJsonPath('data.0.unread_count', 1);
    }

    public function test_the_conversation_carries_what_the_product_strip_needs(): void
    {
        [$buyer, , $conversation] = $this->conversation(withImage: true);

        Sanctum::actingAs($buyer);

        $product = $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->json('conversation.product');

        $this->assertSame('Honda', $product['name']);
        $this->assertStringStartsWith('honda-', $product['slug']);
        $this->assertEquals(45000, $product['price']);
        $this->assertStringContainsString('products/honda.jpg', $product['image_url']);
    }

    public function test_a_discounted_product_shows_the_price_the_buyer_pays(): void
    {
        [$buyer, , $conversation] = $this->conversation(discountPrice: 39000);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('conversation.product.price', fn ($price) => (float) $price === 39000.0);
    }

    /** @return array{0: User, 1: User, 2: Conversation} */
    private function conversation(bool $withImage = false, ?float $discountPrice = null): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Honda',
            'slug' => 'honda-'.uniqid(),
            'price' => 45000,
            'discount_price' => $discountPrice,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
            'is_preorder' => false,
        ]);

        if ($withImage) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => 'products/honda.jpg',
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'last_message_at' => now(),
        ]);

        return [$buyer, $seller, $conversation];
    }

    private function message(
        Conversation $conversation,
        User $sender,
        MessageType $type,
        string $body,
        ?array $metadata = null,
    ): Message {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'type' => $type,
            'body' => $body,
            'metadata' => $metadata,
        ]);
    }
}
