<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
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
    ): void {
        $productName = $item?->product_name
            ?? $order->items->first()?->product_name
            ?? 'an item';

        $title = match (true) {
            $pendingOrder => 'New order awaiting payment',
            $cashOnDelivery => 'New cash-on-delivery order',
            default => 'New order received',
        };

        $body = match (true) {
            $pendingOrder => "Order {$order->order_number}: {$productName} (awaiting payment)",
            $cashOnDelivery => "Order {$order->order_number}: {$productName} (cash on delivery)",
            default => "Order {$order->order_number}: {$productName}",
        };

        static::send($seller, 'new_order', $title, $body, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_item_id' => $item?->id,
            'url' => route('seller.orders.show', $order->id),
        ]);
    }
}
