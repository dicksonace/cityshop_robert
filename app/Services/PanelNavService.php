<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\SellerReportStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Enums\WalletTopUpStatus;
use App\Models\ContactMessage;
use App\Models\Dispute;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\SellerReport;
use App\Models\User;
use App\Models\WalletTopUpRequest;
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
            UserRole::Admin => self::adminCounts(),
            UserRole::Seller => self::sellerCounts($user),
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
            'pending_withdrawals' => Withdrawal::where('status', WithdrawalStatus::Pending)->count(),
            'pending_seller_withdrawals' => Withdrawal::where('status', WithdrawalStatus::Pending)
                ->whereHas('user', fn ($q) => $q->where('role', UserRole::Seller))
                ->count(),
            'pending_manual_top_ups' => WalletTopUpRequest::where('status', WalletTopUpStatus::Pending)->count(),
            'pending_fund_releases' => app(OrderService::class)->pendingFundReleaseItemsQuery()->count(),
            'stale_unprocessed_orders' => app(OrderService::class)->staleUnprocessedItemsQuery(24)->count(),
            'awaiting_direct_payments' => app(OrderService::class)->awaitingDirectPaymentOrdersQuery('all')->count(),
            'awaiting_buyer_confirmation' => OrderItem::where('status', OrderStatus::AwaitingConfirmation)->count(),
            'open_disputes' => Dispute::whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->count(),
            'open_seller_reports' => SellerReport::whereIn('status', [SellerReportStatus::Open, SellerReportStatus::Reviewing])->count(),
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
            'pending_orders' => OrderItem::where('seller_id', $sellerId)->visibleToSeller()->where('status', OrderStatus::Pending)->count(),
            'processing_orders' => OrderItem::where('seller_id', $sellerId)->visibleToSeller()->where('status', OrderStatus::Processing)->count(),
            'call_orders' => OrderItem::where('seller_id', $sellerId)->visibleToSeller()->where('status', OrderStatus::CallConfirmed)->count(),
            'packing_orders' => OrderItem::where('seller_id', $sellerId)->visibleToSeller()->where('status', OrderStatus::Packed)->count(),
            'delivery_orders' => OrderItem::where('seller_id', $sellerId)->visibleToSeller()->where('status', OrderStatus::Shipped)->count(),
            'awaiting_orders' => OrderItem::where('seller_id', $sellerId)->visibleToSeller()->where('status', OrderStatus::AwaitingConfirmation)->count(),
            'cancelled_orders' => OrderItem::where('seller_id', $sellerId)->visibleToSeller()->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])->count(),
            'open_refunds' => Dispute::where('seller_id', $sellerId)->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->count(),
            'unread_notifications' => ChatService::unreadNotificationCount($user),
            'unread_messages' => ChatService::unreadMessageCount($user),
        ];
    }
}
