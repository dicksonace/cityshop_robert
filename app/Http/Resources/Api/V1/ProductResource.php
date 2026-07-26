<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price !== null ? (float) $this->discount_price : null,
            'effective_price' => $this->effectivePrice(),
            'quantity' => (int) $this->quantity,
            'brand' => $this->brand,
            'condition' => $this->condition,
            'sku' => $this->sku,
            'rating' => (float) $this->rating,
            'review_count' => (int) $this->review_count,
            'views' => (int) ($this->views ?? 0),
            'wishlist_adds' => (int) ($this->wishlist_adds ?? 0),
            'cart_adds' => (int) ($this->cart_adds ?? 0),
            'purchase_count' => (int) ($this->purchase_count ?? 0),
            'in_ghana' => (bool) $this->in_ghana,
            'free_shipping' => (bool) $this->free_shipping,
            'delivery_fee' => $this->delivery_fee !== null ? (float) $this->delivery_fee : null,
            'delivery_days' => $this->delivery_days !== null ? (int) $this->delivery_days : null,
            'is_preorder' => (bool) $this->is_preorder,
            'cash_on_delivery' => (bool) ($this->cash_on_delivery ?? false),
            'pickup_available' => (bool) ($this->pickup_available ?? false),
            'ships_nationwide' => (bool) ($this->ships_nationwide ?? false),
            'is_negotiable' => (bool) ($this->is_negotiable ?? false),
            'specifications' => $this->specifications ?? [],
            'video_path' => $this->video_path,
            'video_url' => $this->video_path
                ? (str_starts_with((string) $this->video_path, 'http')
                    ? $this->video_path
                    : Storage::disk('public')->url($this->video_path))
                : null,
            'video_duration' => $this->video_duration !== null ? (int) $this->video_duration : null,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(function ($image) {
                $path = (string) $image->path;
                $url = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                    ? $path
                    : Storage::disk('public')->url($path);

                return [
                    'id' => $image->id,
                    'path' => $path,
                    'url' => $url,
                    'is_primary' => (bool) $image->is_primary,
                    'sort_order' => (int) ($image->sort_order ?? 0),
                ];
            })->values()),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'icon' => $this->category->icon,
                'spec_schema' => $this->category->spec_schema,
            ] : null),
            'seller' => $this->whenLoaded('seller', function () {
                $profile = $this->seller?->sellerProfile;
                $shopPhoto = $profile?->shop_photo;
                $shopPhotoUrl = null;
                if ($shopPhoto) {
                    $shopPhotoUrl = str_starts_with((string) $shopPhoto, 'http')
                        ? $shopPhoto
                        : Storage::disk('public')->url($shopPhoto);
                }

                return [
                    'id' => $this->seller?->id,
                    'name' => $this->seller?->name,
                    'store_name' => $profile?->displayName() ?? $this->seller?->name,
                    'store_slug' => $profile?->slug,
                    'shop_photo' => $shopPhotoUrl,
                    'rating' => $profile?->rating !== null ? (float) $profile->rating : null,
                    'total_sales' => $profile?->total_sales !== null ? (int) $profile->total_sales : null,
                    'seller_profile' => $profile ? [
                        'business_name' => $profile->business_name,
                        'store_name' => $profile->store_name,
                        'slug' => $profile->slug,
                        'shop_photo' => $shopPhotoUrl,
                        'rating' => $profile->rating !== null ? (float) $profile->rating : null,
                        'total_sales' => $profile->total_sales !== null ? (int) $profile->total_sales : null,
                        'business_address' => $profile->business_address,
                    ] : null,
                ];
            }),
        ];
    }
}
