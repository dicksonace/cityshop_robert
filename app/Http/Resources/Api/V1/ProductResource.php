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
            'description' => $this->when(isset($this->description) || $this->relationLoaded('images'), $this->description),
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price !== null ? (float) $this->discount_price : null,
            'effective_price' => $this->effectivePrice(),
            'quantity' => (int) $this->quantity,
            'brand' => $this->brand,
            'rating' => (float) $this->rating,
            'review_count' => (int) $this->review_count,
            'in_ghana' => (bool) $this->in_ghana,
            'free_shipping' => (bool) $this->free_shipping,
            'delivery_fee' => $this->delivery_fee !== null ? (float) $this->delivery_fee : null,
            'is_preorder' => (bool) $this->is_preorder,
            'cash_on_delivery' => (bool) ($this->cash_on_delivery ?? false),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => Storage::disk('public')->url($image->path),
                'is_primary' => (bool) $image->is_primary,
            ])->values()),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'seller' => $this->whenLoaded('seller', function () {
                $profile = $this->seller?->sellerProfile;

                return [
                    'id' => $this->seller?->id,
                    'name' => $this->seller?->name,
                    'store_name' => $profile?->displayName(),
                    'store_slug' => $profile?->slug,
                ];
            }),
        ];
    }
}
