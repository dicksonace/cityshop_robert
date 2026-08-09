<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\SellerFollow;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class SellerFollowService
{
    public static function isFollowing(User $follower, int $sellerId): bool
    {
        return SellerFollow::query()
            ->where('follower_id', $follower->id)
            ->where('seller_id', $sellerId)
            ->exists();
    }

    public static function followerCount(int $sellerId): int
    {
        return SellerFollow::query()->where('seller_id', $sellerId)->count();
    }

    /**
     * @return array{following: bool, follower_count: int}
     */
    public static function toggle(User $follower, User $seller): array
    {
        if ($follower->id === $seller->id) {
            throw new \RuntimeException('You cannot follow yourself.');
        }

        if ($seller->role !== UserRole::Seller) {
            throw new \RuntimeException('You can only follow sellers.');
        }

        if (UserBlockService::isBlockedEitherWay($follower, $seller)) {
            throw new \RuntimeException('You cannot follow this seller.');
        }

        $existing = SellerFollow::query()
            ->where('follower_id', $follower->id)
            ->where('seller_id', $seller->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            SellerFollow::query()->create([
                'follower_id' => $follower->id,
                'seller_id' => $seller->id,
            ]);
            $following = true;
        }

        return [
            'following' => $following,
            'follower_count' => self::followerCount($seller->id),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function publicUserCard(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $avatar = $user->displayAvatarPath();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'role' => $user->role?->value,
            'avatar' => $avatar
                ? (str_starts_with($avatar, 'http')
                    ? $avatar
                    : Storage::disk('public')->url($avatar))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function sellerCard(?User $seller): ?array
    {
        if (! $seller) {
            return null;
        }

        $profile = $seller->sellerProfile;
        $shopPhoto = $profile?->shop_photo;
        $shopPhotoUrl = null;
        if ($shopPhoto) {
            $shopPhotoUrl = str_starts_with((string) $shopPhoto, 'http')
                ? $shopPhoto
                : Storage::disk('public')->url($shopPhoto);
        }

        return [
            'id' => $seller->id,
            'name' => $seller->name,
            'store_name' => $profile?->displayName() ?? $seller->name,
            'store_slug' => $profile?->slug,
            'shop_photo' => $shopPhotoUrl,
            'rating' => $profile?->rating !== null ? (float) $profile->rating : null,
            'total_sales' => $profile?->total_sales !== null ? (int) $profile->total_sales : null,
            'follower_count' => self::followerCount($seller->id),
        ];
    }
}
