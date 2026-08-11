<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Server-rendered Open Graph tags for WhatsApp / Facebook / iMessage.
 * Those crawlers do not run the React app, so tags must live in Blade.
 */
class SharePreview
{
    /**
     * @param  array<string, mixed>  $page
     * @return array{
     *   title: string,
     *   description: string,
     *   image: string,
     *   url: string,
     *   type: string,
     *   image_alt: string
     * }
     */
    public static function fromInertiaPage(array $page): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $site = (string) config('app.name', 'CityShop');
        $props = is_array($page['props'] ?? null) ? $page['props'] : [];
        $component = (string) ($page['component'] ?? '');
        $path = (string) ($page['url'] ?? '/');

        $defaults = [
            'title' => $site,
            'description' => 'Shop products from local Ghana sellers on CityShop — secure checkout, delivery across Ghana.',
            'image' => self::absoluteMediaUrl('/images/logo.png') ?? $appUrl.'/images/logo.png',
            'url' => $appUrl.$path,
            'type' => 'website',
            'image_alt' => $site,
        ];

        if ($component === 'shop/product-show') {
            $product = is_array($props['product'] ?? null) ? $props['product'] : [];
            if ($product !== []) {
                return self::forProduct($product, $defaults, $appUrl, $site);
            }
        }

        if ($component === 'shop/store') {
            $store = is_array($props['store'] ?? null) ? $props['store'] : [];
            if ($store !== []) {
                return self::forStore($store, $defaults, $appUrl, $site);
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    private static function forProduct(array $product, array $defaults, string $appUrl, string $site): array
    {
        $name = trim((string) ($product['name'] ?? 'Product'));
        $slug = trim((string) ($product['slug'] ?? ''));
        $price = $product['discount_price'] ?? $product['price'] ?? null;
        $priceLabel = is_numeric($price) ? 'GH₵'.number_format((float) $price, 2) : null;

        $rawDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string) (
            $product['meta_description'] ?? $product['description'] ?? ''
        ))) ?? '');
        $description = $rawDesc !== ''
            ? $rawDesc
            : ($priceLabel ? "{$priceLabel} · Buy {$name} on {$site}." : "Buy {$name} on {$site}.");
        if ($priceLabel && ! str_contains($description, 'GH₵')) {
            $description = $priceLabel.' · '.$description;
        }

        $imagePath = self::productImagePath($product);
        $image = self::absoluteMediaUrl($imagePath) ?? $defaults['image'];

        return [
            'title' => $name.' · '.$site,
            'description' => mb_substr($description, 0, 320),
            'image' => $image,
            'url' => $slug !== '' ? $appUrl.'/products/'.$slug : $defaults['url'],
            'type' => 'product',
            'image_alt' => $name,
        ];
    }

    /**
     * @param  array<string, mixed>  $store
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    private static function forStore(array $store, array $defaults, string $appUrl, string $site): array
    {
        $name = trim((string) ($store['business_name'] ?? $store['store_name'] ?? 'Store'));
        $slug = trim((string) ($store['slug'] ?? ''));
        $rawDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($store['store_description'] ?? ''))) ?? '');
        $description = $rawDesc !== ''
            ? $rawDesc
            : "Shop {$name} on {$site} — products from a trusted Ghana seller.";

        $image = self::absoluteMediaUrl($store['shop_photo'] ?? null) ?? $defaults['image'];

        return [
            'title' => $name.' · '.$site,
            'description' => mb_substr($description, 0, 320),
            'image' => $image,
            'url' => $slug !== '' ? $appUrl.'/store/'.$slug : $defaults['url'],
            'type' => 'profile',
            'image_alt' => $name,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private static function productImagePath(array $product): ?string
    {
        $images = $product['images'] ?? [];
        if (! is_array($images) || $images === []) {
            return null;
        }

        $primary = null;
        $first = null;
        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }
            $path = $image['path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }
            $first ??= $path;
            if (! empty($image['is_primary'])) {
                $primary = $path;
                break;
            }
        }

        return $primary ?? $first;
    }

    public static function absoluteMediaUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (str_starts_with($path, '//')) {
            return 'https:'.$path;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return preg_replace('/^http:/i', 'https:', $path) ?: $path;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $url = rtrim((string) config('app.url'), '/').'/'.$path;
        } elseif (str_starts_with($path, 'images/')) {
            $url = rtrim((string) config('app.url'), '/').'/'.$path;
        } else {
            $storage = Storage::disk('public')->url($path);
            $url = str_starts_with($storage, 'http')
                ? $storage
                : rtrim((string) config('app.url'), '/').'/'.ltrim($storage, '/');
        }

        return preg_replace('/^http:/i', 'https:', $url) ?: $url;
    }
}
