<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\OrderItem;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;

class WalletTransactionService
{
    public static function record(
        int $userId,
        WalletTransactionType $type,
        float $amount,
        string $description,
        ?int $orderItemId = null,
        ?int $withdrawalId = null,
        ?string $reference = null,
    ): WalletTransaction {
        return WalletTransaction::create([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'order_item_id' => $orderItemId,
            'withdrawal_id' => $withdrawalId,
            'reference' => $reference,
        ]);
    }

    public static function recordSalePending(OrderItem $item): void
    {
        if (static::existsForOrderItem($item->id, WalletTransactionType::SalePending)) {
            return;
        }

        $orderNumber = $item->order?->order_number ?? 'N/A';

        static::record(
            userId: $item->seller_id,
            type: WalletTransactionType::SalePending,
            amount: (float) $item->seller_amount,
            description: "Sale: {$item->product_name} (Order {$orderNumber}) — pending delivery",
            orderItemId: $item->id,
            reference: $orderNumber,
        );
    }

    public static function recordSaleReleased(OrderItem $item): void
    {
        if (static::existsForOrderItem($item->id, WalletTransactionType::SaleReleased)) {
            return;
        }

        $orderNumber = $item->order?->order_number ?? 'N/A';

        static::record(
            userId: $item->seller_id,
            type: WalletTransactionType::SaleReleased,
            amount: (float) $item->seller_amount,
            description: "Funds released: {$item->product_name} (Order {$orderNumber})",
            orderItemId: $item->id,
            reference: $orderNumber,
        );
    }

    public static function recordWithdrawal(Withdrawal $withdrawal): void
    {
        static::record(
            userId: $withdrawal->user_id,
            type: WalletTransactionType::Withdrawal,
            amount: -1 * (float) $withdrawal->amount,
            description: "Withdrawal request to {$withdrawal->momo_number} ({$withdrawal->network})",
            withdrawalId: $withdrawal->id,
            reference: "WD-{$withdrawal->id}",
        );
    }

    public static function recordFundAdded(int $userId, float $amount, string $method, ?string $reference = null): void
    {
        static::record(
            userId: $userId,
            type: WalletTransactionType::FundAdded,
            amount: $amount,
            description: "Funds added via {$method}",
            reference: $reference ?? 'TOP-'.now()->format('YmdHis'),
        );
    }

    public static function recordOrderPayment(int $userId, float $amount, string $checkoutNumber, string $reference): void
    {
        static::record(
            userId: $userId,
            type: WalletTransactionType::OrderPayment,
            amount: -1 * $amount,
            description: "Order payment (Checkout {$checkoutNumber})",
            reference: $reference,
        );
    }

    public static function recordOrderRefund(OrderItem $item, float $amount): void
    {
        if (static::existsForOrderItem($item->id, WalletTransactionType::OrderRefund)) {
            return;
        }

        $orderNumber = $item->order?->order_number ?? 'N/A';

        static::record(
            userId: $item->order->buyer_id,
            type: WalletTransactionType::OrderRefund,
            amount: $amount,
            description: "Refund: {$item->product_name} (Order {$orderNumber})",
            orderItemId: $item->id,
            reference: $orderNumber,
        );
    }

    public static function recordSaleReversed(OrderItem $item): void
    {
        if (static::existsForOrderItem($item->id, WalletTransactionType::SaleReversed)) {
            return;
        }

        $orderNumber = $item->order?->order_number ?? 'N/A';

        static::record(
            userId: $item->seller_id,
            type: WalletTransactionType::SaleReversed,
            amount: -1 * (float) $item->seller_amount,
            description: "Sale reversed: {$item->product_name} (Order {$orderNumber})",
            orderItemId: $item->id,
            reference: $orderNumber,
        );
    }

    public static function recordWithdrawalCompleted(Withdrawal $withdrawal): void
    {
        if (WalletTransaction::where('withdrawal_id', $withdrawal->id)
            ->where('type', WalletTransactionType::WithdrawalCompleted)
            ->exists()) {
            return;
        }

        static::record(
            userId: $withdrawal->user_id,
            type: WalletTransactionType::WithdrawalCompleted,
            amount: -1 * (float) $withdrawal->amount,
            description: "Payout sent to {$withdrawal->momo_number}",
            withdrawalId: $withdrawal->id,
            reference: "WD-{$withdrawal->id}",
        );
    }

    public static function recordWithdrawalRefunded(Withdrawal $withdrawal): void
    {
        static::record(
            userId: $withdrawal->user_id,
            type: WalletTransactionType::WithdrawalRefunded,
            amount: (float) $withdrawal->amount,
            description: 'Withdrawal rejected — funds returned to wallet',
            withdrawalId: $withdrawal->id,
            reference: "WD-{$withdrawal->id}",
        );
    }

    public static function backfillForSeller(int $userId): void
    {
        if (WalletTransaction::where('user_id', $userId)->exists()) {
            return;
        }

        $orderItems = OrderItem::with('order')
            ->where('seller_id', $userId)
            ->whereHas('order', fn ($q) => $q->where('payment_status', PaymentStatus::Paid))
            ->orderBy('created_at')
            ->get();

        foreach ($orderItems as $item) {
            static::recordSalePending($item);

            if ($item->status === OrderStatus::Delivered) {
                static::recordSaleReleased($item);
            }
        }

        $withdrawals = Withdrawal::where('user_id', $userId)->orderBy('created_at')->get();

        foreach ($withdrawals as $withdrawal) {
            static::recordWithdrawal($withdrawal);

            if ($withdrawal->status === WithdrawalStatus::Paid) {
                static::recordWithdrawalCompleted($withdrawal);
            }

            if ($withdrawal->status === WithdrawalStatus::Rejected) {
                static::recordWithdrawalRefunded($withdrawal);
            }
        }
    }

    public static function labelFor(WalletTransactionType $type): string
    {
        return match ($type) {
            WalletTransactionType::SalePending => 'Sale (Pending)',
            WalletTransactionType::SaleReleased => 'Funds Released',
            WalletTransactionType::Withdrawal => 'Withdrawal Request',
            WalletTransactionType::WithdrawalCompleted => 'Payout Sent',
            WalletTransactionType::WithdrawalRefunded => 'Withdrawal Refunded',
            WalletTransactionType::FundAdded => 'Funds Added',
            WalletTransactionType::OrderPayment => 'Order Payment',
            WalletTransactionType::OrderRefund => 'Order Refund',
            WalletTransactionType::SaleReversed => 'Sale Reversed',
        };
    }

    private static function existsForOrderItem(int $orderItemId, WalletTransactionType $type): bool
    {
        return WalletTransaction::where('order_item_id', $orderItemId)
            ->where('type', $type)
            ->exists();
    }
}
