<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\WithdrawalStatus;
use App\Models\ContactMessage;
use App\Models\Dispute;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Withdrawal;

class PanelNavService
{
    /**
     * @return array<string, int>
     */
    public static function countsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return match ($user->role) {
            'admin' => self::adminCounts(),
            'seller' => self::sellerCounts($user),
            default => [],
        };
    }

    /**
     * @return array<string, int>
     */
    private static function adminCounts(): array
    {
        return [
            'pending_sellers' => SellerProfile::where('status', SellerStatus::Pending)->count(),
            'pending_products' => Product::where('status', ProductStatus::Pending)->count(),
            'pending_withdrawals' => Withdrawal::where('status', WithdrawalStatus::Pending)->count(),
            'open_disputes' => Dispute::whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->count(),
            'unread_messages' => ContactMessage::where('is_read', false)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function sellerCounts(User $user): array
    {
        $sellerId = $user->id;

        return [
            'pending_products' => Product::where('seller_id', $sellerId)->where('status', ProductStatus::Pending)->count(),
            'pending_orders' => OrderItem::where('seller_id', $sellerId)->where('status', OrderStatus::Pending)->count(),
        ];
    }
}
