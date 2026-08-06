<?php

namespace Tests\Feature\Api;

use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiChatReplyProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_to_product_includes_product_preview(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'CRV Shop',
            'store_name' => 'CRV Shop',
            'slug' => 'crv-shop',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Toyota crv',
            'slug' => 'toyota-crv-reply',
            'price' => 20000,
            'quantity' => 1,
            'status' => 'approved',
        ]);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
        ]);

        $productMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'type' => MessageType::Product,
            'body' => 'Toyota crv',
            'metadata' => [
                'product' => [
                    'id' => $product->id,
                    'name' => 'Toyota crv',
                    'slug' => 'toyota-crv-reply',
                    'price' => 20000,
                    'image_url' => '/storage/products/crv.jpg',
                ],
            ],
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/v1/messages/{$conversation->id}/send", [
            'body' => 'ok',
            'reply_to_id' => $productMessage->id,
        ])->assertCreated();

        $response
            ->assertJsonPath('message.body', 'ok')
            ->assertJsonPath('message.reply_to.type', 'product')
            ->assertJsonPath('message.reply_to.product.name', 'Toyota crv')
            ->assertJsonPath('message.reply_to.product.slug', 'toyota-crv-reply');
    }
}
