<?php

namespace App\Services;

use App\Enums\PaymentChannel;
use App\Models\AppNotification;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class AppNotificationService
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function send(
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
    ): AppNotification {
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $notification->setRelation('user', $user);
        PushNotificationService::sendForNotification($notification);

        return $notification;
    }

    /**
     * @param  iterable<User>|Collection<int, User>  $users
     * @param  array<string, mixed>|null  $data
     */
    public static function sendMany(
        iterable $users,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
    ): int {
        $count = 0;

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            static::send($user, $type, $title, $body, $data);
            $count++;
        }

        return $count;
    }

    public static function notifySellerNewOrder(
        User $seller,
        Order $order,
        ?OrderItem $item = null,
        bool $pendingOrder = false,
        bool $cashOnDelivery = false,
        bool $paymentClaim = false,
    ): void {
        $productName = $item?->product_name
            ?? $order->items->first()?->product_name
            ?? 'an item';

        $title = static::sellerNewOrderTitle($order, $pendingOrder, $cashOnDelivery, $paymentClaim);
        $body = match (true) {
            $paymentClaim => "Order {$order->order_number}: {$productName} — buyer submitted payment. Confirm only if you received the money.",
            $pendingOrder => "Order {$order->order_number}: {$productName} (awaiting payment)",
            $cashOnDelivery => "Order {$order->order_number}: {$productName} — call the buyer, then pack & deliver.",
            $order->payment_channel === PaymentChannel::Direct => "Order {$order->order_number}: {$productName} — buyer paid you directly.",
            default => "Order {$order->order_number}: {$productName} — paid via CityShop secured.",
        };

        static::send($seller, 'new_order', $title, $body, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_item_id' => $item?->id,
            'payment_channel' => $order->payment_channel?->value,
            'url' => $item?->id
                ? route('seller.orders.show', $item->id)
                : route('seller.orders.index'),
        ]);
    }

    public static function notifySellerProductOutOfStock(User $seller, Product $product): void
    {
        static::send(
            $seller,
            'product_out_of_stock',
            'Update stock — item sold out',
            "{$product->name} is out of stock. Update the quantity so buyers can order again.",
            [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'url' => route('seller.products.edit', $product->id),
            ],
        );
    }

    public static function notifyBuyerOrderPlaced(
        User $buyer,
        Order $order,
        bool $cashOnDelivery = false,
        bool $paymentComplete = false,
    ): void {
        $title = $cashOnDelivery ? 'Cash on delivery order placed' : 'Order placed';
        $body = match (true) {
            $cashOnDelivery => "Order {$order->order_number} is placed. The seller will call you to confirm.",
            $paymentComplete => "Order {$order->order_number} was placed. Payment complete.",
            default => "Order {$order->order_number} was placed.",
        };

        static::send($buyer, 'order', $title, $body, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'url' => route('orders.show', $order->id),
        ]);
    }

    public static function notifyBuyerPaymentConfirmed(User $buyer, Order $order): void
    {
        static::send(
            $buyer,
            'payment',
            'Payment confirmed',
            "Payment for order {$order->order_number} is confirmed. The seller will prepare your items.",
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'url' => route('orders.show', $order->id),
            ],
        );
    }

    public static function notifyBuyerOrderStatus(
        User $buyer,
        OrderItem $item,
        string $status,
        bool $refunded = false,
        float $refundAmount = 0,
    ): void {
        $item->loadMissing('order');
        $order = $item->order;
        $labels = [
            'call_confirmed' => 'Seller will call you about your cash-on-delivery order',
            'packed' => 'Your order is being packed',
            'shipped' => 'Your order is out for delivery',
            'awaiting_confirmation' => 'Please confirm you received your order',
            'delivered' => 'Your order is complete',
            'cancelled' => 'Your order was cancelled',
        ];

        $title = $labels[$status] ?? 'Order update';
        $body = "{$item->product_name} — order {$order?->order_number}.";

        if ($refunded && $refundAmount > 0) {
            $body .= ' GH₵'.number_format($refundAmount, 2).' credited to your wallet.';
        }

        static::send($buyer, 'order_status', $title, $body, [
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'order_item_id' => $item->id,
            'status' => $status,
            'url' => $order ? route('orders.show', $order->id) : null,
        ]);
    }

    public static function notifyBuyerDirectPaymentRejected(User $buyer, Order $order, string $reason): void
    {
        static::send(
            $buyer,
            'payment',
            'Direct payment rejected',
            "Seller rejected payment for order {$order->order_number}. Reason: {$reason}",
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'url' => route('orders.show', $order->id),
            ],
        );
    }

    public static function notifyDisputeResolved(User $user, Dispute $dispute): void
    {
        $dispute->loadMissing('order');
        $resolution = $dispute->status?->value ?? 'closed';
        $title = match ($resolution) {
            'resolved_buyer' => 'Refund approved',
            'resolved_seller' => 'Refund declined',
            default => 'Refund request closed',
        };

        static::send($user, 'dispute', $title, "Refund request on order {$dispute->order?->order_number} was updated.", [
            'dispute_id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'resolution' => $resolution,
            'url' => $dispute->order_id ? route('orders.show', $dispute->order_id) : null,
        ]);
    }

    /**
     * Scan-to-pay / My QR wallet transfer — always hits the notifications bell.
     *
     * @param  array{reference: string, amount: float, note: ?string}  $transfer
     */
    public static function notifyQrPayment(
        User $payer,
        User $payee,
        array $transfer,
        ?int $conversationId = null,
    ): void {
        $amountLabel = 'GH₵'.number_format((float) $transfer['amount'], 2);
        $note = $transfer['note'] ?? null;
        $reference = $transfer['reference'] ?? null;

        $payeeBody = $note
            ? "{$payer->name} paid you {$amountLabel} via QR — {$note}"
            : "{$payer->name} paid you {$amountLabel} via QR";

        static::send($payee, 'payment', 'QR payment received', $payeeBody, [
            'reference' => $reference,
            'amount' => (float) $transfer['amount'],
            'from_user_id' => $payer->id,
            'from_name' => $payer->name,
            'via' => 'qr',
            'conversation_id' => $conversationId,
        ]);

        $payerBody = $note
            ? "You paid {$payee->name} {$amountLabel} via QR — {$note}"
            : "You paid {$payee->name} {$amountLabel} via QR";

        static::send($payer, 'payment', 'QR payment sent', $payerBody, [
            'reference' => $reference,
            'amount' => (float) $transfer['amount'],
            'to_user_id' => $payee->id,
            'to_name' => $payee->name,
            'via' => 'qr',
            'conversation_id' => $conversationId,
        ]);
    }

    public static function sellerNewOrderTitle(
        Order $order,
        bool $pendingOrder = false,
        bool $cashOnDelivery = false,
        bool $paymentClaim = false,
    ): string {
        return match (true) {
            $paymentClaim => 'Buyer submitted payment — review',
            $pendingOrder => 'New order awaiting payment',
            $cashOnDelivery => 'New Order (Cash on Delivery)',
            $order->payment_channel === PaymentChannel::Direct => 'New order received (Paid to seller)',
            default => 'New order received (Paid · CityShop secured)',
        };
    }
}
