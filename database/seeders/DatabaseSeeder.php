<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Fresh install: platform settings, categories, and one admin account only.
     * No demo sellers, buyers, or products — for real-world testing.
     */
    public function run(): void
    {
        PlatformSetting::create(['key' => 'commission_rate', 'value' => '0']);

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
            'phones-tablets' => 'Phones & Tablets',
            'computers' => 'Computers',
            'appliances' => 'Appliances',
            'fashion' => 'Fashion',
            'bags-shoes' => 'Bags & Shoes',
            'beauty' => 'Beauty & Personal Care',
            'home-garden' => 'Home & Garden',
            'food-beverages' => 'Food & Beverages',
            'groceries' => 'Groceries',
            'health-pharmacy' => 'Health & Pharmacy',
            'baby-kids' => 'Baby & Kids',
            'sports' => 'Sports',
            'toys-games' => 'Toys & Games',
            'books-education' => 'Books & Education',
            'office-stationery' => 'Office & Stationery',
            'jewelry-watches' => 'Jewelry & Watches',
            'vehicles' => 'Vehicles',
            'auto-parts' => 'Auto Parts & Accessories',
            'tools-hardware' => 'Tools & Hardware',
            'pet-supplies' => 'Pet Supplies',
        ];

        $sort = 1;
        foreach ($categoryNames as $slug => $name) {
            $config = $categorySpecs[$slug] ?? null;
            Category::create([
                'name' => $name,
                'slug' => $slug,
                'icon' => $config['icon'] ?? null,
                'spec_schema' => $config ? ['fields' => $config['fields']] : null,
                'is_active' => true,
                'sort_order' => $sort++,
            ]);
        }
    }
}
