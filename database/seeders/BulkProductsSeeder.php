<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\CategorySpecService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BulkProductsSeeder extends Seeder
{
    private array $productNames = [
        'computers' => [
            'Dell XPS 13 Laptop', 'HP Pavilion 15', 'Lenovo ThinkPad X1', 'ASUS VivoBook 14',
            'Acer Aspire 5', 'Microsoft Surface Pro 9', 'MacBook Air M2', 'MacBook Pro 14 M3',
            'HP EliteBook 840', 'Lenovo IdeaPad 3', 'ASUS ROG Strix G15', 'MSI Modern 15',
            'Dell Inspiron 16', 'HP Spectre x360', 'Lenovo Yoga 7i',
        ],
        'phones-tablets' => [
            'iPhone 15 Pro Max', 'Samsung Galaxy A54', 'Google Pixel 8', 'Tecno Camon 20',
            'Infinix Note 30', 'Xiaomi Redmi Note 13', 'iPad Air 5th Gen', 'Samsung Galaxy Tab S9',
            'Huawei MatePad 11', 'Oppo Reno 11', 'Vivo V29', 'Realme 11 Pro',
            'iPhone 14', 'Samsung Galaxy S23 FE', 'Tecno Spark 20',
        ],
        'electronics' => [
            'JBL Bluetooth Speaker', 'Sony WH-1000XM5 Headphones', 'Anker Power Bank 20000mAh',
            'Logitech MX Master 3 Mouse', 'Samsung 32" Smart Monitor', 'Canon EOS R50 Camera',
            'GoPro Hero 12', 'Philips LED Smart Bulb Pack', 'TP-Link Wi-Fi 6 Router',
            'Razer BlackWidow Keyboard', 'Bose SoundLink Mini', 'DJI Mini 3 Drone',
            'SanDisk 1TB External SSD', 'Amazon Echo Dot 5', 'Ring Video Doorbell',
        ],
        'fashion' => [
            'Men\'s Ankara Print Shirt', 'Women\'s Kente Wrap Dress', 'Leather Sandals',
            'Denim Jacket Classic Fit', 'Cotton Polo Shirt', 'African Print Maxi Skirt',
            'Running Sneakers White', 'Formal Blazer Navy', 'Casual Chino Trousers',
            'Silk Headwrap Set', 'Kids School Uniform Set', 'Woven Basket Bag',
            'Beaded Necklace Set', 'Men\'s Kaftan', 'Women\'s Jumpsuit',
        ],
        'home-garden' => [
            'Wooden Dining Table 6-Seater', 'Ergonomic Office Chair', 'Queen Size Bed Frame',
            'Storage Ottoman Bench', 'Garden Patio Set', 'Indoor Plant Stand',
            'Kitchen Knife Set 12pc', 'Non-Stick Cookware Set', 'Memory Foam Pillow 2-Pack',
            'LED Floor Lamp', 'Woven Area Rug 5x7', 'Stainless Steel Water Filter',
            'Outdoor BBQ Grill', 'Ceramic Flower Pots Set', 'Bookshelf 5-Tier',
        ],
        'appliances' => [
            'Samsung 300L Refrigerator', 'LG 7kg Washing Machine', 'Binatone Blender 1.5L',
            'Philips Air Fryer XXL', 'Midea 1.5HP Air Conditioner', 'Panasonic Microwave 25L',
            'Russell Hobbs Kettle', 'Hisense 55" Smart TV', 'Tefal Iron Steam Station',
            'Nasco Chest Freezer 200L', 'Sokany Stand Mixer', 'Black+Decker Toaster Oven',
        ],
        'beauty' => [
            'Shea Butter Body Lotion 500ml', 'Natural Black Soap Pack', 'Argan Oil Hair Serum',
            'Vitamin C Face Serum', 'SPF 50 Sunscreen', 'Matte Lipstick Set',
            'Men\'s Beard Oil Kit', 'Aloe Vera Gel Pure', 'Charcoal Face Mask',
            'Coconut Oil Hair Treatment', 'Perfume Eau de Parfum 100ml', 'Nail Polish Set 6 Colors',
        ],
        'sports' => [
            'Adidas Football Size 5', 'Yoga Mat Premium 6mm', 'Adjustable Dumbbells 20kg',
            'Basketball Official Size 7', 'Cycling Helmet Adult', 'Resistance Bands Set',
            'Tennis Racket Pro', 'Jump Rope Speed', 'Camping Tent 4-Person',
            'Swimming Goggles Anti-Fog', 'Cricket Bat Willow', 'Fitness Tracker Watch',
        ],
    ];

    private array $brands = [
        'computers' => ['Apple', 'Dell', 'HP', 'Lenovo', 'ASUS', 'Acer', 'MSI', 'Microsoft'],
        'phones-tablets' => ['Apple', 'Samsung', 'Tecno', 'Infinix', 'Xiaomi', 'Google', 'Huawei', 'Oppo'],
        'electronics' => ['Sony', 'JBL', 'Anker', 'Logitech', 'Canon', 'Philips', 'Samsung', 'Bose'],
        'fashion' => ['CityStyle', 'AfroWear', 'KenteCo', 'UrbanGh', 'ClassicFit'],
        'home-garden' => ['HomePro', 'GardenLife', 'ComfortHome', 'WoodCraft'],
        'appliances' => ['Samsung', 'LG', 'Philips', 'Binatone', 'Midea', 'Panasonic', 'Hisense'],
        'beauty' => ['NaturaGh', 'GlowSkin', 'PureEssence', 'AfroBeauty'],
        'sports' => ['Adidas', 'Nike', 'Puma', 'FitPro', 'SportMax'],
    ];

    public function run(): void
    {
        $this->syncCategories();

        $this->backfillExistingProducts();

        $seller = User::where('role', UserRole::Seller)->first();
        if (! $seller) {
            $this->command?->warn('No seller found. Run DatabaseSeeder first.');

            return;
        }

        $categories = Category::where('is_active', true)->get()->keyBy('slug');
        $targetTotal = 100;
        $existing = Product::count();
        $toCreate = max(0, $targetTotal - $existing);

        if ($toCreate === 0) {
            $this->command?->info("Already have {$existing} products. Skipping bulk seed.");

            return;
        }

        $this->command?->info("Creating {$toCreate} products with galleries and specs...");

        $allTemplates = [];
        foreach ($this->productNames as $slug => $names) {
            foreach ($names as $name) {
                $allTemplates[] = ['name' => $name, 'slug' => $slug];
            }
        }
        shuffle($allTemplates);

        for ($i = 0; $i < $toCreate; $i++) {
            $template = $allTemplates[$i % count($allTemplates)];
            $category = $categories->get($template['slug']);
            if (! $category) {
                continue;
            }

            $baseName = $template['name'];
            $suffix = $existing + $i + 1;
            $name = "{$baseName} #{$suffix}";

            $price = $this->randomFloat(25, 15000);
            $hasDiscount = $this->randomBool(35);

            $product = Product::create([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => $name,
                'description' => $this->randomDescription().' Available on CityShop with verified seller guarantee.',
                'specifications' => CategorySpecService::generateSpecs($category->slug),
                'brand' => $this->randomElement($this->brands[$category->slug] ?? ['Generic']),
                'price' => $price,
                'discount_price' => $hasDiscount ? round($price * $this->randomFloat(0.75, 0.95), 2) : null,
                'quantity' => $this->randomInt(1, 80),
                'status' => ProductStatus::Approved,
                'is_preorder' => $this->randomBool(20),
                'free_shipping' => $this->randomBool(45),
                'in_ghana' => $this->randomBool(70),
                'rating' => $this->randomFloat(3.5, 5.0, 1),
                'review_count' => $this->randomInt(0, 120),
            ]);

            $imageCount = $this->randomInt(2, 4);
            for ($img = 0; $img < $imageCount; $img++) {
                $seed = Str::slug($product->slug)."-{$img}";
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => "https://picsum.photos/seed/{$seed}/600/600",
                    'is_primary' => $img === 0,
                    'sort_order' => $img,
                ]);
            }
        }

        $this->command?->info('Done! Total products: '.Product::count());
    }

    private function backfillExistingProducts(): void
    {
        Product::with('category', 'images')->each(function (Product $product) {
            if ($product->category && empty($product->specifications)) {
                $product->update([
                    'specifications' => CategorySpecService::generateSpecs($product->category->slug),
                ]);
            }

            if ($product->images->count() < 2) {
                $start = $product->images->count();
                $needed = $this->randomInt(2, 4) - $start;
                for ($img = 0; $img < $needed; $img++) {
                    $index = $start + $img;
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => "https://picsum.photos/seed/{$product->slug}-extra-{$index}/600/600",
                        'is_primary' => false,
                        'sort_order' => $index,
                    ]);
                }
            }
        });
    }

    private function syncCategories(): void
    {
        $specs = config('category_specs', []);

        foreach ($specs as $slug => $config) {
            Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $this->categoryName($slug),
                    'icon' => $config['icon'],
                    'spec_schema' => ['fields' => $config['fields']],
                    'is_active' => true,
                ]
            );
        }
    }

    private function categoryName(string $slug): string
    {
        return match ($slug) {
            'home-garden' => 'Home & Garden',
            'phones-tablets' => 'Phones & Tablets',
            default => Str::title(str_replace('-', ' ', $slug)),
        };
    }

    private function randomFloat(float $min, float $max, int $decimals = 2): float
    {
        $value = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);

        return round($value, $decimals);
    }

    private function randomInt(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    private function randomBool(int $percentTrue = 50): bool
    {
        return random_int(1, 100) <= $percentTrue;
    }

    /**
     * @param  array<int, string>  $items
     */
    private function randomElement(array $items): string
    {
        return $items[array_rand($items)];
    }

    private function randomDescription(): string
    {
        $sentences = [
            'High quality product with excellent reviews from Ghanaian buyers.',
            'Trusted seller with fast delivery across Greater Accra and nationwide.',
            'Built to last with dependable performance for everyday use.',
            'Popular choice on CityShop with strong value for money.',
        ];
        shuffle($sentences);

        return implode(' ', array_slice($sentences, 0, 3));
    }
}
