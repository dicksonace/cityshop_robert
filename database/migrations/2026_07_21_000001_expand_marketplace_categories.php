<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'electronics' => ['name' => 'Electronics', 'sort_order' => 1],
            'phones-tablets' => ['name' => 'Phones & Tablets', 'sort_order' => 2],
            'computers' => ['name' => 'Computers', 'sort_order' => 3],
            'appliances' => ['name' => 'Appliances', 'sort_order' => 4],
            'fashion' => ['name' => 'Fashion', 'sort_order' => 5],
            'bags-shoes' => ['name' => 'Bags & Shoes', 'sort_order' => 6],
            'beauty' => ['name' => 'Beauty & Personal Care', 'sort_order' => 7],
            'home-garden' => ['name' => 'Home & Garden', 'sort_order' => 8],
            'food-beverages' => ['name' => 'Food & Beverages', 'sort_order' => 9],
            'groceries' => ['name' => 'Groceries', 'sort_order' => 10],
            'health-pharmacy' => ['name' => 'Health & Pharmacy', 'sort_order' => 11],
            'baby-kids' => ['name' => 'Baby & Kids', 'sort_order' => 12],
            'sports' => ['name' => 'Sports', 'sort_order' => 13],
            'toys-games' => ['name' => 'Toys & Games', 'sort_order' => 14],
            'books-education' => ['name' => 'Books & Education', 'sort_order' => 15],
            'office-stationery' => ['name' => 'Office & Stationery', 'sort_order' => 16],
            'jewelry-watches' => ['name' => 'Jewelry & Watches', 'sort_order' => 17],
            'vehicles' => ['name' => 'Vehicles', 'sort_order' => 18],
            'auto-parts' => ['name' => 'Auto Parts & Accessories', 'sort_order' => 19],
            'tools-hardware' => ['name' => 'Tools & Hardware', 'sort_order' => 20],
            'pet-supplies' => ['name' => 'Pet Supplies', 'sort_order' => 21],
        ];

        foreach ($categories as $slug => $meta) {
            $config = config("category_specs.{$slug}");

            Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'icon' => $config['icon'] ?? null,
                    'spec_schema' => $config ? ['fields' => $config['fields']] : null,
                    'is_active' => true,
                    'sort_order' => $meta['sort_order'],
                ]
            );
        }
    }

    public function down(): void
    {
        Category::whereIn('slug', [
            'food-beverages',
            'groceries',
            'health-pharmacy',
            'baby-kids',
            'books-education',
            'jewelry-watches',
            'bags-shoes',
            'tools-hardware',
            'toys-games',
            'pet-supplies',
            'office-stationery',
            'auto-parts',
        ])->delete();

        Category::where('slug', 'beauty')->update(['name' => 'Beauty']);
    }
};
