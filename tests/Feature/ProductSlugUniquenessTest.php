<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSlugUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_name_from_different_sellers_gets_distinct_slugs(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller]);

        $first = Product::create([
            'seller_id' => $sellerA->id,
            'name' => 'Electric bike',
            'price' => 150,
            'quantity' => 1,
            'status' => 'approved',
        ]);

        $second = Product::create([
            'seller_id' => $sellerB->id,
            'name' => 'Electric bike',
            'price' => 200,
            'quantity' => 1,
            'status' => 'approved',
        ]);

        $this->assertSame('electric-bike', $first->slug);
        $this->assertSame('electric-bike-1', $second->slug);
        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_same_seller_repeat_names_also_get_suffixes(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $first = Product::create([
            'seller_id' => $seller->id,
            'name' => 'HP Elitebook',
            'price' => 150,
            'quantity' => 1,
            'status' => 'approved',
        ]);

        $second = Product::create([
            'seller_id' => $seller->id,
            'name' => 'HP Elitebook',
            'price' => 160,
            'quantity' => 1,
            'status' => 'approved',
        ]);

        $this->assertSame('hp-elitebook', $first->slug);
        $this->assertSame('hp-elitebook-1', $second->slug);
    }
}
