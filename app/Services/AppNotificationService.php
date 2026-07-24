<?php

namespace App\Services;

use App\Enums\PaymentChannel;
use App\Models\AppNotification;
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
        return AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
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
