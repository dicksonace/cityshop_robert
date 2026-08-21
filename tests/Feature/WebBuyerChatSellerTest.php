<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebBuyerChatSellerTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_start_web_chat_with_seller_from_product(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Phone Shop']);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Phone Shop',
            'business_name' => 'Phone Shop',
            'slug' => 'phone-shop',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'iPhone 12',
            'slug' => 'iphone-12-'.uniqid(),
            'price' => 1200,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
            'is_preorder' => false,
        ]);

        $response = $this->actingAs($buyer)->postJson(route('chat.store'), [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('conversation.seller_id', $seller->id)
            ->assertJsonPath('conversation.buyer_id', $buyer->id)
            ->assertJsonPath('conversation.other.name', 'Phone Shop')
            ->assertJsonPath('attach_product.id', $product->id)
            ->assertJsonPath('attach_product.name', 'iPhone 12');

        $this->assertDatabaseHas('conversations', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
    }

    public function test_buyer_can_chat_seller_without_seller_profile_row(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Bare Seller']);

        $this->actingAs($buyer)
            ->postJson(route('chat.store'), ['seller_id' => $seller->id])
            ->assertOk()
            ->assertJsonPath('conversation.other.name', 'Bare Seller');
    }

    public function test_buyer_can_open_web_chat_inbox_json(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Demo',
            'slug' => 'demo-store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->actingAs($buyer)->postJson(route('chat.store'), [
            'seller_id' => $seller->id,
        ])->assertOk();

        $this->actingAs($buyer)
            ->getJson(route('chat.index'))
            ->assertOk()
            ->assertJsonCount(1, 'conversations');
    }

    public function test_format_conversation_survives_missing_peer_user(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller, 'name' => 'Gone Seller']);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        $seller->delete();

        $this->actingAs($buyer)
            ->getJson(route('chat.index'))
            ->assertOk()
            ->assertJsonPath('conversations.0.other.name', 'Deleted account');

        $this->actingAs($buyer)
            ->postJson(route('chat.store'), ['seller_id' => $seller->id])
            ->assertStatus(404);
    }
}
