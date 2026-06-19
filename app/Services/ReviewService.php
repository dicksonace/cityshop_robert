<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;

class ReviewService
{
    public static function findReviewableItem(User $user, Product $product): ?OrderItem
    {
        return OrderItem::with('order')
            ->where('product_id', $product->id)
            ->where('status', OrderStatus::Delivered)
            ->whereHas('order', fn ($q) => $q->where('buyer_id', $user->id))
            ->latest('updated_at')
            ->get()
            ->first(function (OrderItem $item) use ($user) {
                return ! Review::where('product_id', $item->product_id)
                    ->where('user_id', $user->id)
                    ->where('order_id', $item->order_id)
                    ->exists();
            });
    }

    public static function createReview(User $user, OrderItem $item, int $rating, ?string $comment): Review
    {
        if ($item->status !== OrderStatus::Delivered) {
            throw new \RuntimeException('You can only review delivered items.');
        }

        if ($item->order->buyer_id !== $user->id) {
            throw new \RuntimeException('You can only review your own purchases.');
        }

        $exists = Review::where('product_id', $item->product_id)
            ->where('user_id', $user->id)
            ->where('order_id', $item->order_id)
            ->exists();

        if ($exists) {
            throw new \RuntimeException('You have already reviewed this item.');
        }

        $review = Review::create([
            'product_id' => $item->product_id,
            'user_id' => $user->id,
            'order_id' => $item->order_id,
            'rating' => $rating,
            'comment' => $comment,
        ]);

        static::syncProductRating($item->product);
        static::syncSellerRating($item->product->seller_id);

        return $review;
    }

    public static function syncProductRating(Product $product): void
    {
        $stats = Review::where('product_id', $product->id)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count')
            ->first();

        $product->update([
            'rating' => round($stats->avg_rating ?? 0, 2),
            'review_count' => (int) ($stats->count ?? 0),
        ]);
    }

    public static function syncSellerRating(int $sellerId): void
    {
        $profile = SellerProfile::where('user_id', $sellerId)->first();

        if (! $profile) {
            return;
        }

        $stats = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count')
            ->first();

        $profile->update([
            'rating' => round($stats->avg_rating ?? 0, 2),
        ]);
    }

    public static function syncAllRatings(): array
    {
        $products = 0;
        $sellers = 0;

        Product::query()->pluck('id')->each(function (int $id) use (&$products) {
            static::syncProductRating(Product::find($id));
            $products++;
        });

        User::query()
            ->whereHas('sellerProfile')
            ->pluck('id')
            ->each(function (int $id) use (&$sellers) {
                static::syncSellerRating($id);
                $sellers++;
            });

        return ['products' => $products, 'sellers' => $sellers];
    }
}
