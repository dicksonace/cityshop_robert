<?php

namespace App\Services;

class CategorySpecService
{
    public static function schemaForCategory(?string $slug): ?array
    {
        if (! $slug) {
            return null;
        }

        return config("category_specs.{$slug}");
    }

    public static function generateSpecs(string $categorySlug): array
    {
        $schema = static::schemaForCategory($categorySlug);
        if (! $schema) {
            return [];
        }

        $specs = [];
        foreach ($schema['fields'] as $field) {
            if ($field['type'] === 'select' && ! empty($field['options'])) {
                $specs[$field['key']] = $field['options'][array_rand($field['options'])];
            } else {
                $specs[$field['key']] = static::sampleValue($field['key']);
            }
        }

        return $specs;
    }

    private static function sampleValue(string $key): string
    {
        return match ($key) {
            'processor' => fake()->randomElement(['Intel Core i5', 'Intel Core i7', 'Apple M1', 'Apple M2', 'AMD Ryzen 5', 'AMD Ryzen 7']),
            'display' => fake()->randomElement(['13.3" FHD', '14" FHD', '15.6" FHD', '16" Retina', '17" 4K']),
            'graphics' => fake()->randomElement(['Integrated', 'Intel Iris Xe', 'NVIDIA GTX 1650', 'NVIDIA RTX 3060', 'Apple M2 GPU']),
            'screen_size' => fake()->randomElement(['5.5"', '6.1"', '6.5"', '6.7"', '10.9"', '12.9"']),
            'battery' => fake()->randomElement(['4000mAh', '5000mAh', '6000mAh', 'Up to 12 hours', 'Up to 20 hours']),
            'camera' => fake()->randomElement(['12MP', '48MP Triple', '50MP Quad', '108MP Pro']),
            'power' => fake()->randomElement(['500W', '1000W', '1500W', '2000W']),
            'connectivity' => fake()->randomElement(['Bluetooth 5.0', 'Wi-Fi 6', 'USB-C', 'HDMI + USB']),
            'color' => fake()->randomElement(['Black', 'White', 'Silver', 'Blue', 'Red', 'Gold']),
            'material' => fake()->randomElement(['Plastic', 'Metal', 'Cotton', 'Leather', 'Wood', 'Stainless Steel']),
            'dimensions' => fake()->randomElement(['30x20x10 cm', '50x40x30 cm', '120x60x75 cm']),
            'weight' => fake()->randomElement(['0.5 kg', '1.2 kg', '2.5 kg', '5 kg']),
            'care' => 'Machine wash cold',
            'volume' => fake()->randomElement(['50ml', '100ml', '250ml', '500ml']),
            'ingredients' => fake()->randomElement(['Natural extracts', 'Vitamin E', 'Hyaluronic Acid', 'Organic']),
            'expiry' => fake()->randomElement(['12 months', '24 months', '36 months']),
            'capacity' => fake()->randomElement(['5L', '10L', '20L', '50L']),
            'power_consumption' => fake()->randomElement(['800W', '1200W', '2000W']),
            default => fake()->words(2, true),
        };
    }

    public static function validateSpecs(string $categorySlug, array $specs): array
    {
        $schema = static::schemaForCategory($categorySlug);
        if (! $schema) {
            return $specs;
        }

        $validated = [];
        foreach ($schema['fields'] as $field) {
            $key = $field['key'];
            if (! isset($specs[$key]) || $specs[$key] === '') {
                continue;
            }
            $value = $specs[$key];
            if ($field['type'] === 'select' && ! in_array($value, $field['options'] ?? [], true)) {
                continue;
            }
            $validated[$key] = $value;
        }

        return $validated;
    }
}
