<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CartItem;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createPendingOrderFromCart(User $buyer, array $shipping, string $paymentMethod): Order
    {
        return DB::transaction(function () use ($buyer, $shipping, $paymentMethod) {
            $cartItems = CartItem::with('product.seller.sellerProfile')
                ->where('user_id', $buyer->id)
                ->get();

            if ($cartItems->isEmpty()) {
                throw new \RuntimeException('Cart is empty.');
            }

            foreach ($cartItems as $item) {
                if (! $item->product || ! $item->product->isVisibleInShop()) {
                    throw new \RuntimeException('Your cart contains items from a seller that is no longer available.');
                }
            }

            $commissionRate = PlatformSettings::commissionRate();
            $subtotal = $cartItems->sum(fn ($item) => $item->subtotal());
            $commissionTotal = $subtotal * ($commissionRate / 100);
            $total = $subtotal;

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'buyer_id' => $buyer->id,
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'payment_method' => $paymentMethod,
                'payment_reference' => 'CSH-'.uniqid(),
                'receiver_name' => $shipping['receiver_name'],
                'receiver_phone' => $shipping['receiver_phone'],
                'region' => $shipping['region'],
                'city' => $shipping['city'],
                'digital_address' => $shipping['digital_address'] ?? null,
                'delivery_notes' => $shipping['delivery_notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'commission_amount' => $commissionTotal,
                'total' => $total,
            ]);

            foreach ($cartItems as $item) {
                $product = $item->product;
                $unitPrice = $product->effectivePrice();
                $lineTotal = $unitPrice * $item->quantity;
                $commission = $lineTotal * ($commissionRate / 100);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'product_name' => $product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $unitPrice,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commission,
                    'seller_amount' => $lineTotal - $commission,
                    'status' => OrderStatus::Pending,
                ]);
            }

            CartItem::where('user_id', $buyer->id)->delete();

            $buyer->notify(new OrderPlacedNotification($order));

            return $order->load('items.seller');
        });
    }

    public function fulfillPaidOrder(Order $order, string $paystackReference): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        return DB::transaction(function () use ($order, $paystackReference) {
            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_reference' => $paystackReference,
                'status' => OrderStatus::Processing,
            ]);

            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);

                if ($product && ! $product->is_preorder) {
                    $product->decrement('quantity', $item->quantity);
                }

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $item->seller_id],
                    ['available_balance' => 0, 'pending_balance' => 0, 'total_earnings' => 0, 'withdrawn_amount' => 0]
                );

                $wallet->increment('pending_balance', $item->seller_amount);
                $wallet->increment('total_earnings', $item->seller_amount);

                $seller = User::find($item->seller_id);
                $seller?->sellerProfile?->increment('total_sales');
                $seller?->notify(new PaymentConfirmedNotification($order, $item));

                WalletTransactionService::recordSalePending($item);
            }

            $order->buyer->notify(new PaymentConfirmedNotification($order));

            return $order->fresh('items');
        });
    }

    public function confirmCashOnDelivery(Order $order): Order
    {
        $order->update(['status' => OrderStatus::Processing]);
        $order->buyer->notify(new OrderPlacedNotification($order, cashOnDelivery: true));

        foreach ($order->items as $item) {
            $item->seller->notify(new PaymentConfirmedNotification($order, $item, cashOnDelivery: true));
        }

        return $order;
    }

    public function releaseSellerFunds(OrderItem $item): void
    {
        if ($item->status === OrderStatus::Delivered) {
            return;
        }

        if (Dispute::where('order_item_id', $item->id)->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->exists()) {
            throw new \RuntimeException('Cannot release funds while a dispute is open.');
        }

        DB::transaction(function () use ($item) {
            $wallet = Wallet::where('user_id', $item->seller_id)->firstOrFail();
            $amount = (float) $item->seller_amount;

            $wallet->decrement('pending_balance', $amount);
            $wallet->increment('available_balance', $amount);

            $item->update(['status' => OrderStatus::Delivered]);

            WalletTransactionService::recordSaleReleased($item);

            $item->seller->notify(new OrderStatusUpdatedNotification($item, 'delivered'));
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'delivered'));
        });
    }

    public function updateOrderItemStatus(OrderItem $item, array $data): OrderItem
    {
        $previousStatus = $item->status->value;
        $item->update($data);

        if ($data['status'] === 'shipped' && $previousStatus !== 'shipped') {
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'shipped'));
        }

        if ($data['status'] === 'delivered') {
            $this->releaseSellerFunds($item);
        }

        return $item->fresh();
    }

    public function rejectOrderItem(OrderItem $item, string $reason): OrderItem
    {
        $item->load(['order', 'product', 'seller.sellerProfile']);

        if (in_array($item->status, [OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            throw new \RuntimeException('This order item can no longer be rejected.');
        }

        if (in_array($item->status, [OrderStatus::Shipped], true)) {
            throw new \RuntimeException('Cannot reject an order that is already out for delivery.');
        }

        if (Dispute::where('order_item_id', $item->id)->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->exists()) {
            throw new \RuntimeException('Cannot reject while a dispute is open.');
        }

        return DB::transaction(function () use ($item, $reason) {
            $order = $item->order;
            $refundAmount = $item->lineTotal();
            $wasPaid = $order->payment_status === PaymentStatus::Paid;

            $item->update([
                'status' => OrderStatus::Cancelled,
                'rejection_reason' => $reason,
            ]);

            if ($wasPaid) {
                $buyerWallet = WalletService::ensure($order->buyer);
                $buyerWallet->increment('available_balance', $refundAmount);

                WalletTransactionService::recordOrderRefund($item, $refundAmount);

                $sellerWallet = Wallet::where('user_id', $item->seller_id)->first();
                if ($sellerWallet) {
                    $sellerAmount = (float) $item->seller_amount;
                    $sellerWallet->decrement('pending_balance', min($sellerAmount, (float) $sellerWallet->pending_balance));
                    $sellerWallet->decrement('total_earnings', min($sellerAmount, (float) $sellerWallet->total_earnings));
                }

                WalletTransactionService::recordSaleReversed($item);

                $item->seller?->sellerProfile?->decrement('total_sales');

                $product = Product::find($item->product_id);
                if ($product && ! $product->is_preorder) {
                    $product->increment('quantity', $item->quantity);
                }
            }

            $this->syncOrderStatusAfterItemChange($order);

            $order->buyer->notify(new OrderStatusUpdatedNotification($item, 'cancelled', refunded: $wasPaid, refundAmount: $refundAmount));

            return $item->fresh();
        });
    }

    private function syncOrderStatusAfterItemChange(Order $order): void
    {
        $order->load('items');
        $statuses = $order->items->pluck('status');

        if ($statuses->every(fn ($s) => $s === OrderStatus::Cancelled)) {
            $order->update([
                'status' => OrderStatus::Cancelled,
                'payment_status' => $order->payment_status === PaymentStatus::Paid
                    ? PaymentStatus::Refunded
                    : $order->payment_status,
            ]);

            return;
        }

        if ($statuses->contains(OrderStatus::Delivered) && $statuses->every(fn ($s) => in_array($s, [OrderStatus::Delivered, OrderStatus::Cancelled], true))) {
            $order->update(['status' => OrderStatus::Delivered]);
        }
    }

    public function recalculateProductRating(Product $product): void
    {
        ReviewService::syncProductRating($product);
    }
}
