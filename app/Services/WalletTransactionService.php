<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
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
            amount: -1 * $withdrawal->totalDebited(),
            description: "Withdrawal request to {$withdrawal->momo_number} ({$withdrawal->network})"
                .((float) ($withdrawal->fee ?? 0) > 0
                    ? ' · fee GH₵'.number_format((float) $withdrawal->fee, 2)
                    : ''),
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
            description: "Funds credited via {$method}",
            reference: $reference ?? 'TOP-'.now()->format('YmdHis'),
        );
    }

    public static function recordAdminCredit(int $userId, float $amount, int $adminId, ?string $note = null): WalletTransaction
    {
        $description = 'Funds added by admin';
        if ($note !== null && trim($note) !== '') {
            $description .= ' — '.trim($note);
        }

        return static::record(
            userId: $userId,
            type: WalletTransactionType::FundAdded,
            amount: $amount,
            description: $description,
            reference: 'ADMIN-'.$adminId.'-'.now()->format('YmdHis'),
        );
    }

    public static function recordServiceFee(int $userId, float $amount, \DateTimeInterface $paidUntil): WalletTransaction
    {
        $until = \Illuminate\Support\Carbon::parse($paidUntil)->timezone('Africa/Accra')->format('j M Y');

        return static::record(
            userId: $userId,
            type: WalletTransactionType::ServiceFee,
            amount: -1 * $amount,
            description: 'Seller service fee — store active until '.$until,
            reference: 'ACT-'.strtoupper(uniqid()),
        );
    }

    public static function recordAdminDebit(int $userId, float $amount, int $adminId, ?string $note = null): WalletTransaction
    {
        $description = 'Funds removed by admin';
        if ($note !== null && trim($note) !== '') {
            $description .= ' — '.trim($note);
        }

        return static::record(
            userId: $userId,
            type: WalletTransactionType::FundRemoved,
            amount: -1 * $amount,
            description: $description,
            reference: 'ADMIN-DEBIT-'.$adminId.'-'.now()->format('YmdHis'),
        );
    }

    public static function recordDirectCancelDebit(OrderItem $item, float $amount): void
    {
        if (static::existsForOrderItem($item->id, WalletTransactionType::DirectCancelDebit)) {
            return;
        }

        $orderNumber = $item->order?->order_number ?? 'N/A';

        static::record(
            userId: $item->seller_id,
            type: WalletTransactionType::DirectCancelDebit,
            amount: -1 * $amount,
            description: "Pay-to-seller cancel: {$item->product_name} (Order {$orderNumber})",
            orderItemId: $item->id,
            reference: $orderNumber,
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

    public static function shippingPendingExists(int $orderId): bool
    {
        return WalletTransaction::where('reference', 'SHIP-'.$orderId)
            ->where('type', WalletTransactionType::SalePending)
            ->exists();
    }

    public static function shippingReleasedExists(int $orderId): bool
    {
        return WalletTransaction::where('reference', 'SHIP-REL-'.$orderId)
            ->where('type', WalletTransactionType::SaleReleased)
            ->exists();
    }

    public static function shippingRefundedExists(int $orderId): bool
    {
        return WalletTransaction::where('reference', 'SHIP-REF-'.$orderId)
            ->where('type', WalletTransactionType::OrderRefund)
            ->exists();
    }

    public static function recordShippingPending(Order $order): void
    {
        if (static::shippingPendingExists($order->id)) {
            return;
        }

        static::record(
            userId: (int) $order->seller_id,
            type: WalletTransactionType::SalePending,
            amount: (float) $order->shipping_cost,
            description: "Delivery fee (Order {$order->order_number}) — pending delivery",
            reference: 'SHIP-'.$order->id,
        );
    }

    public static function recordShippingReleased(Order $order, float $amount): void
    {
        if (static::shippingReleasedExists($order->id)) {
            return;
        }

        static::record(
            userId: (int) $order->seller_id,
            type: WalletTransactionType::SaleReleased,
            amount: $amount,
            description: "Delivery fee released (Order {$order->order_number})",
            reference: 'SHIP-REL-'.$order->id,
        );
    }

    public static function recordShippingRefund(Order $order, float $amount): void
    {
        if (static::shippingRefundedExists($order->id)) {
            return;
        }

        static::record(
            userId: (int) $order->buyer_id,
            type: WalletTransactionType::OrderRefund,
            amount: $amount,
            description: "Delivery fee refund (Order {$order->order_number})",
            reference: 'SHIP-REF-'.$order->id,
        );
    }

    public static function recordShippingReversed(Order $order, float $amount): void
    {
        $reference = 'SHIP-REV-'.$order->id;
        if (WalletTransaction::where('reference', $reference)->exists()) {
            return;
        }

        static::record(
            userId: (int) $order->seller_id,
            type: WalletTransactionType::SaleReversed,
            amount: -1 * $amount,
            description: "Delivery fee reversed (Order {$order->order_number})",
            reference: $reference,
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
            amount: $withdrawal->totalDebited(),
            description: 'Withdrawal rejected — funds returned to wallet'
                .((float) ($withdrawal->fee ?? 0) > 0
                    ? ' (incl. fee GH₵'.number_format((float) $withdrawal->fee, 2).')'
                    : ''),
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
            WalletTransactionType::FundAdded => 'Funds Credited',
            WalletTransactionType::OrderPayment => 'Order Payment',
            WalletTransactionType::OrderRefund => 'Order Refund',
            WalletTransactionType::SaleReversed => 'Sale Reversed',
        };
    }

    /**
     * For transfer rows, look up the other party's profile once per page so the
     * wallet history can show their photo / Tel even on older ledger lines.
     *
     * @param  \Illuminate\Support\Collection<int, WalletTransaction>  $page
     */
    public static function attachCounterpartyMobiles($page): void
    {
        $refs = $page
            ->filter(fn (WalletTransaction $tx) => in_array($tx->type, [
                WalletTransactionType::TransferIn,
                WalletTransactionType::TransferOut,
            ], true) && is_string($tx->reference) && trim($tx->reference) !== '')
            ->map(fn (WalletTransaction $tx) => trim((string) $tx->reference))
            ->unique()
            ->values();

        if ($refs->isEmpty()) {
            return;
        }

        $peers = WalletTransaction::query()
            ->whereIn('reference', $refs->all())
            ->whereIn('type', [
                WalletTransactionType::TransferIn->value,
                WalletTransactionType::TransferOut->value,
            ])
            ->get(['user_id', 'reference', 'type']);

        $userIds = $peers->pluck('user_id')->unique()->values();
        $users = User::query()
            ->whereIn('id', $userIds->all())
            ->with('sellerProfile:id,user_id,shop_photo')
            ->get(['id', 'name', 'mobile', 'avatar', 'role'])
            ->keyBy('id');

        foreach ($page as $tx) {
            if (! in_array($tx->type, [
                WalletTransactionType::TransferIn,
                WalletTransactionType::TransferOut,
            ], true)) {
                continue;
            }

            $ref = trim((string) ($tx->reference ?? ''));
            if ($ref === '') {
                continue;
            }

            $peer = $peers->first(function (WalletTransaction $other) use ($tx, $ref) {
                return trim((string) $other->reference) === $ref
                    && (int) $other->user_id !== (int) $tx->user_id;
            });

            if (! $peer) {
                continue;
            }

            $user = $users->get((int) $peer->user_id);
            if (! $user) {
                continue;
            }

            $mobile = trim((string) ($user->mobile ?? ''));
            if ($mobile !== '') {
                $tx->setAttribute('counterparty_mobile', $mobile);
            }

            $tx->setAttribute('counterparty', [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $mobile !== '' ? $mobile : null,
                'avatar' => $user->publicAvatarUrl(),
            ]);
        }
    }

    /** Compact counterparty payload for API clients (transfers only). */
    public static function counterpartyPayload(WalletTransaction $tx): ?array
    {
        $party = $tx->getAttribute('counterparty');

        return is_array($party) ? $party : null;
    }

    /** Statement / history line with Tel inserted when the stored description lacks it. */
    public static function displayDescription(WalletTransaction $tx): string
    {
        $description = (string) ($tx->description ?? '');
        if ($description === '') {
            return $description;
        }

        if (! in_array($tx->type, [
            WalletTransactionType::TransferIn,
            WalletTransactionType::TransferOut,
        ], true)) {
            return $description;
        }

        if (str_contains($description, ' Tel ')) {
            return $description;
        }

        $mobile = trim((string) ($tx->getAttribute('counterparty_mobile') ?? ''));
        if ($mobile === '') {
            return $description;
        }

        // Insert before an em-dash note suffix when present.
        if (preg_match('/^(Transfer (?:to|from) .+?)( — .+)?$/u', $description, $m)) {
            return $m[1].' Tel '.$mobile.($m[2] ?? '');
        }

        return $description.' Tel '.$mobile;
    }

    /**
     * Attach before/after available, pending, and total balances to a newest-first page of transactions.
     *
     * @param  \Illuminate\Support\Collection<int, WalletTransaction>  $pageNewestFirst
     * @return \Illuminate\Support\Collection<int, WalletTransaction>
     */
    public static function attachRunningBalances(
        int $userId,
        $pageNewestFirst,
        float $currentAvailable,
        float $currentPending,
    ) {
        if ($pageNewestFirst->isEmpty()) {
            return $pageNewestFirst;
        }

        $available = $currentAvailable;
        $pending = $currentPending;

        $newestOnPage = $pageNewestFirst->first();
        $newer = WalletTransaction::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($newestOnPage) {
                $q->where('created_at', '>', $newestOnPage->created_at)
                    ->orWhere(function ($q2) use ($newestOnPage) {
                        $q2->where('created_at', $newestOnPage->created_at)
                            ->where('id', '>', $newestOnPage->id);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($newer as $tx) {
            static::reverseLedgerEffect($tx->type, (float) $tx->amount, $available, $pending);
        }

        foreach ($pageNewestFirst as $tx) {
            $tx->setAttribute('available_after', round($available, 2));
            $tx->setAttribute('pending_after', round($pending, 2));
            $tx->setAttribute('balance_after', round($available + $pending, 2));
            static::reverseLedgerEffect($tx->type, (float) $tx->amount, $available, $pending);
            $tx->setAttribute('available_before', round($available, 2));
            $tx->setAttribute('pending_before', round($pending, 2));
            $tx->setAttribute('balance_before', round($available + $pending, 2));
        }

        return $pageNewestFirst;
    }

    /**
     * @return array{
     *     available_before: float,
     *     pending_before: float,
     *     balance_before: float,
     *     available_after: float,
     *     pending_after: float,
     *     balance_after: float
     * }
     */
    public static function balancesAfterTransaction(
        WalletTransaction $transaction,
        float $currentAvailable,
        float $currentPending,
    ): array {
        $available = $currentAvailable;
        $pending = $currentPending;

        $newerAndSelf = WalletTransaction::query()
            ->where('user_id', $transaction->user_id)
            ->where(function ($q) use ($transaction) {
                $q->where('created_at', '>', $transaction->created_at)
                    ->orWhere(function ($q2) use ($transaction) {
                        $q2->where('created_at', $transaction->created_at)
                            ->where('id', '>=', $transaction->id);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($newerAndSelf as $tx) {
            if ($tx->id === $transaction->id) {
                $after = [
                    'available_after' => round($available, 2),
                    'pending_after' => round($pending, 2),
                    'balance_after' => round($available + $pending, 2),
                ];
                static::reverseLedgerEffect($tx->type, (float) $tx->amount, $available, $pending);

                return [
                    'available_before' => round($available, 2),
                    'pending_before' => round($pending, 2),
                    'balance_before' => round($available + $pending, 2),
                    ...$after,
                ];
            }
            static::reverseLedgerEffect($tx->type, (float) $tx->amount, $available, $pending);
        }

        return [
            'available_before' => round($available, 2),
            'pending_before' => round($pending, 2),
            'balance_before' => round($available + $pending, 2),
            'available_after' => round($available, 2),
            'pending_after' => round($pending, 2),
            'balance_after' => round($available + $pending, 2),
        ];
    }

    private static function reverseLedgerEffect(
        WalletTransactionType $type,
        float $amount,
        float &$available,
        float &$pending,
    ): void {
        if ($type === WalletTransactionType::SalePending) {
            $pending -= $amount;

            return;
        }

        if ($type === WalletTransactionType::SaleReleased) {
            $available -= $amount;
            $pending += $amount;

            return;
        }

        if ($type === WalletTransactionType::WithdrawalCompleted) {
            return;
        }

        if ($type === WalletTransactionType::SaleReversed) {
            // Forward reduced pending (then available). Reverse restores to available.
            $available += abs($amount);

            return;
        }

        $available -= $amount;
    }

    private static function existsForOrderItem(int $orderItemId, WalletTransactionType $type): bool
    {
        return WalletTransaction::where('order_item_id', $orderItemId)
            ->where('type', $type)
            ->exists();
    }
}
