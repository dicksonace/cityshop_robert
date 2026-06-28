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
    }
}
