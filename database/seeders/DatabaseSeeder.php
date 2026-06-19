<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CategorySpecService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::create(['key' => 'commission_rate', 'value' => '10']);

        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@cityshop.com',
            'mobile' => '0200000000',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $categorySpecs = config('category_specs', []);
        $categoryNames = [
            'electronics' => 'Electronics',
            'fashion' => 'Fashion',
            'home-garden' => 'Home & Garden',
            'phones-tablets' => 'Phones & Tablets',
            'computers' => 'Computers',
            'appliances' => 'Appliances',
            'beauty' => 'Beauty',
            'sports' => 'Sports',
        ];

        foreach ($categoryNames as $slug => $name) {
            $config = $categorySpecs[$slug] ?? null;
            Category::create([
                'name' => $name,
                'slug' => $slug,
                'icon' => $config['icon'] ?? null,
                'spec_schema' => $config ? ['fields' => $config['fields']] : null,
                'is_active' => true,
            ]);
        }

        $seller = User::create([
            'name' => 'Ace Electronics',
            'first_name' => 'Ace',
            'last_name' => 'Asare',
            'email' => 'seller@cityshop.com',
            'mobile' => '0240000001',
            'whatsapp' => '0240000001',
            'password' => Hash::make('password'),
            'role' => UserRole::Seller,
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'digital_address' => 'GA-123-4567',
            'residential_address' => '123 Oxford Street, Osu',
            'ghana_card_number' => 'GHA-123456789-0',
        ]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'Ace Electronics',
            'store_name' => 'Ace Electronics',
            'slug' => 'ace-electronics',
            'is_business_registered' => true,
            'business_registration_number' => 'BN123456',
            'business_address' => '123 Oxford Street, Osu, Accra',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'rating' => 4.8,
            'total_sales' => 150,
        ]);

        Wallet::create([
            'user_id' => $seller->id,
            'available_balance' => 5000,
            'pending_balance' => 1200,
            'total_earnings' => 25000,
            'withdrawn_amount' => 18800,
        ]);

        User::create([
            'name' => 'John Buyer',
            'email' => 'buyer@cityshop.com',
            'mobile' => '0240000002',
            'password' => Hash::make('password'),
            'role' => UserRole::Buyer,
        ]);

        $buyer = User::where('email', 'buyer@cityshop.com')->first();

        Wallet::create([
            'user_id' => $buyer->id,
            'available_balance' => 500,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        $products = [
            [
                'name' => '13 inch Apple MacBook Pro 2016 Core i5',
                'price' => 3800,
                'discount_price' => null,
                'quantity' => 5,
                'brand' => 'Apple',
                'is_preorder' => true,
                'free_shipping' => true,
                'in_ghana' => false,
                'rating' => 4.5,
                'category_id' => 5,
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&h=600&fit=crop',
            ],
            [
                'name' => '15 inch Apple MacBook Pro 2017 Core i7',
                'price' => 4500,
                'discount_price' => 4200,
                'quantity' => 3,
                'brand' => 'Apple',
                'is_preorder' => true,
                'free_shipping' => true,
                'in_ghana' => false,
                'rating' => 4.7,
                'category_id' => 5,
                'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop',
            ],
            [
                'name' => 'Portable Rechargeable Fan USB',
                'price' => 85,
                'quantity' => 50,
                'brand' => 'Generic',
                'is_preorder' => false,
                'free_shipping' => true,
                'in_ghana' => true,
                'rating' => 4.2,
                'category_id' => 1,
                'image' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&h=600&fit=crop',
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra 256GB',
                'price' => 12500,
                'discount_price' => 11800,
                'quantity' => 8,
                'brand' => 'Samsung',
                'is_preorder' => false,
                'free_shipping' => true,
                'in_ghana' => true,
                'rating' => 4.9,
                'category_id' => 4,
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600&h=600&fit=crop',
            ],
        ];

        foreach ($products as $data) {
            $image = $data['image'];
            unset($data['image']);

            $product = Product::create([
                ...$data,
                'seller_id' => $seller->id,
                'status' => ProductStatus::Approved,
                'description' => 'High quality product available on CityShop. Buy with confidence from verified sellers.',
                'specifications' => CategorySpecService::generateSpecs(
                    Category::find($data['category_id'])?->slug ?? 'electronics'
                ),
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $image,
                'is_primary' => true,
            ]);
        }

        $this->call(BulkProductsSeeder::class);
    }
}
