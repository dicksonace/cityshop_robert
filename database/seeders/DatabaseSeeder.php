<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformSettings;
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
        PlatformSetting::updateOrCreate(
            ['key' => 'commission_rate'],
            ['value' => '0'],
        );

        PlatformSettings::saveManualFundingAccounts([
            'enabled' => true,
            'instructions' => 'Send payment to one of the CityShop Mobile Money accounts below, then submit your proof and transaction reference so we can credit your wallet.',
            'accounts' => PlatformSettings::defaultCityShopMomoAccounts(),
        ]);

        User::updateOrCreate(
            ['email' => 'admin@cityshop.com'],
            [
                'name' => 'Super Admin',
                'mobile' => '0200000000',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
            ],
        );

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
            Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'icon' => $config['icon'] ?? null,
                    'spec_schema' => $config ? ['fields' => $config['fields']] : null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
            );
        }
    }
}
