<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\CartItem;
use App\Models\Checkout;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerPaymentMethod;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private InvoiceService $invoices,
        private CouponService $coupons,
        private ProductAnalyticsService $analytics,
    ) {}

    /**
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     */
    public function createCheckoutFromCart(User $buyer, array $shipping, string $paymentMethod, array $sellerPayments = [], array $sellerCoupons = []): Checkout
    {
        return DB::transaction(function () use ($buyer, $shipping, $paymentMethod, $sellerPayments, $sellerCoupons) {
            $cartItems = CartItem::with(['product.seller.sellerProfile.paymentMethods'])
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

            $checkout = Checkout::create([
                'checkout_number' => Checkout::generateCheckoutNumber(),
                'buyer_id' => $buyer->id,
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'receiver_name' => $shipping['receiver_name'],
                'receiver_phone' => $shipping['receiver_phone'],
                'region' => $shipping['region'],
                'city' => $shipping['city'],
                'digital_address' => $shipping['digital_address'] ?? null,
                'delivery_notes' => $shipping['delivery_notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'commission_amount' => 0,
                'discount_amount' => 0,
                'total' => $subtotal,
            ]);

            $totalDiscount = 0;
            $totalCommission = 0;

            $grouped = $cartItems->groupBy(fn ($item) => $item->product->seller_id);

            foreach ($grouped as $sellerId => $items) {
                $seller = User::with('sellerProfile.paymentMethods')->findOrFail($sellerId);
                $profile = $seller->sellerProfile;
                $channel = $this->resolvePaymentChannel($profile, $sellerPayments[$sellerId] ?? []);

                $orderSubtotal = $items->sum(fn ($item) => $item->subtotal());
                $couponDiscount = 0;
                $couponId = null;
                $appliedCoupon = null;

                $couponCode = $sellerCoupons[$sellerId] ?? $sellerCoupons[(string) $sellerId] ?? null;
                if ($couponCode) {
                    $result = $this->coupons->validateForSeller($buyer, (int) $sellerId, $couponCode, $orderSubtotal);
                    $couponDiscount = $result['discount'];
                    $couponId = $result['coupon']->id;
                    $appliedCoupon = $result['coupon'];
                }

                $orderTotal = max(0, round($orderSubtotal - $couponDiscount, 2));
                $orderCommission = $channel === PaymentChannel::Marketplace
                    ? round($orderTotal * ($commissionRate / 100), 2)
                    : 0;
                $totalDiscount += $couponDiscount;
                $totalCommission += $orderCommission;
                $methodId = $sellerPayments[$sellerId]['method_id'] ?? null;

                if ($channel === PaymentChannel::Direct && $methodId) {
                    $method = SellerPaymentMethod::where('seller_profile_id', $profile->id)
                        ->where('id', $methodId)
                        ->where('is_active', true)
                        ->firstOrFail();
                } else {
                    $method = null;
                    $methodId = null;
                }

                $order = Order::create([
                    'checkout_id' => $checkout->id,
                    'order_number' => Order::generateOrderNumber(),
                    'buyer_id' => $buyer->id,
                    'seller_id' => $sellerId,
                    'status' => OrderStatus::Pending,
                    'payment_status' => PaymentStatus::Pending,
                    'payment_method' => $channel === PaymentChannel::Direct ? 'direct' : $paymentMethod,
                    'payment_channel' => $channel,
                    'payment_reference' => 'CSH-'.uniqid(),
                    'seller_payment_method_id' => $methodId,
                    'receiver_name' => $shipping['receiver_name'],
                    'receiver_phone' => $shipping['receiver_phone'],
                    'region' => $shipping['region'],
                    'city' => $shipping['city'],
                    'digital_address' => $shipping['digital_address'] ?? null,
                    'delivery_notes' => $shipping['delivery_notes'] ?? null,
                    'subtotal' => $orderSubtotal,
                    'shipping_cost' => 0,
                    'commission_amount' => $orderCommission,
                    'discount_amount' => $couponDiscount,
                    'seller_coupon_id' => $couponId,
                    'total' => $orderTotal,
                ]);

                foreach ($items as $item) {
                    $product = $item->product;
                    $unitPrice = $product->effectivePrice();
                    $lineTotal = $unitPrice * $item->quantity;
                    $commission = $channel === PaymentChannel::Marketplace
                        ? $lineTotal * ($commissionRate / 100)
                        : 0;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'seller_id' => $product->seller_id,
                        'product_name' => $product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $unitPrice,
                        'commission_rate' => $channel === PaymentChannel::Marketplace ? $commissionRate : 0,
                        'commission_amount' => $commission,
                        'seller_amount' => $lineTotal - $commission,
                        'status' => OrderStatus::Pending,
                    ]);
                }

                if ($appliedCoupon) {
                    $this->coupons->recordUsage($appliedCoupon, $buyer, $order->id);
                }

                Payment::create([
                    'checkout_id' => $checkout->id,
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'channel' => $channel,
                    'method' => $order->payment_method,
                    'amount' => $order->total,
                    'status' => PaymentStatus::Pending,
                    'reference' => $order->payment_reference,
                ]);

                $order->seller->notify(new PaymentConfirmedNotification($order->load('items'), $order->items->first(), pendingOrder: true));
            }

            $checkout->update([
                'discount_amount' => $totalDiscount,
                'commission_amount' => $totalCommission,
                'total' => max(0, $subtotal - $totalDiscount),
            ]);

            CartItem::where('user_id', $buyer->id)->delete();

            $checkout = $checkout->load('orders.items.seller');
            $buyer->notify(new OrderPlacedNotification($checkout->orders->first(), checkout: $checkout));

            return $checkout;
        });
    }

    /** @deprecated Use createCheckoutFromCart */
    public function createPendingOrderFromCart(User $buyer, array $shipping, string $paymentMethod): Order
    {
        $checkout = $this->createCheckoutFromCart($buyer, $shipping, $paymentMethod);

        return $checkout->orders->first();
    }

    public function fulfillPaidCheckout(Checkout $checkout, string $paystackReference): Checkout
    {
        if ($checkout->payment_status === PaymentStatus::Paid) {
            return $checkout;
        }

        return DB::transaction(function () use ($checkout, $paystackReference) {
            $checkout->load('orders.items');

            foreach ($checkout->orders as $order) {
                if ($order->payment_channel === PaymentChannel::Direct) {
                    continue;
                }

                $this->fulfillPaidOrder($order, $paystackReference, skipCheckoutUpdate: true, skipBuyerNotify: true);
            }

            $this->syncCheckoutPaymentStatus($checkout);

            Payment::where('checkout_id', $checkout->id)
                ->where('channel', PaymentChannel::Marketplace)
                ->update([
                    'status' => PaymentStatus::Paid,
                    'reference' => $paystackReference,
                    'paid_at' => now(),
                ]);

            $checkout->refresh();
            $checkout->buyer->notify(new PaymentConfirmedNotification($checkout->orders->first()));
            $this->invoices->sendInvoices($checkout);

            return $checkout;
        });
    }

    public function fulfillPaidOrder(Order $order, string $paystackReference, bool $skipCheckoutUpdate = false, bool $skipBuyerNotify = false): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        return DB::transaction(function () use ($order, $paystackReference, $skipCheckoutUpdate, $skipBuyerNotify) {
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

                if ($product) {
                    $this->analytics->recordPurchase($product, $item->quantity);
                }

                if ($order->payment_channel === PaymentChannel::Marketplace) {
                    $wallet = Wallet::firstOrCreate(
                        ['user_id' => $item->seller_id],
                        ['available_balance' => 0, 'pending_balance' => 0, 'total_earnings' => 0, 'withdrawn_amount' => 0]
                    );

                    $wallet->increment('pending_balance', $item->seller_amount);
                    $wallet->increment('total_earnings', $item->seller_amount);

                    WalletTransactionService::recordSalePending($item);
                }

                $seller = User::find($item->seller_id);
                $seller?->sellerProfile?->increment('total_sales');
                $seller?->notify(new PaymentConfirmedNotification($order, $item));
            }

            if (! $skipBuyerNotify) {
                $order->buyer->notify(new PaymentConfirmedNotification($order));
            }

            Payment::where('order_id', $order->id)->update([
                'status' => PaymentStatus::Paid,
                'reference' => $paystackReference,
                'paid_at' => now(),
            ]);

            if (! $skipCheckoutUpdate && $order->checkout_id) {
                $this->syncCheckoutPaymentStatus($order->checkout);
            }

            return $order->fresh('items');
        });
    }

    public function submitDirectPaymentReference(Order $order, string $reference): Order
    {
        if ($order->payment_channel !== PaymentChannel::Direct) {
            throw new \RuntimeException('This order does not use direct seller payment.');
        }

        $order->update(['direct_payment_reference' => $reference]);

        return $order;
    }

    public function confirmDirectPayment(Order $order, User $confirmer): Order
    {
        if ($order->payment_channel !== PaymentChannel::Direct) {
            throw new \RuntimeException('Not a direct payment order.');
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        return DB::transaction(function () use ($order) {
            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'status' => OrderStatus::Processing,
                'direct_payment_confirmed_at' => now(),
            ]);

            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product && ! $product->is_preorder) {
                    $product->decrement('quantity', $item->quantity);
                }

                if ($product) {
                    $this->analytics->recordPurchase($product, $item->quantity);
                }

                $order->seller?->sellerProfile?->increment('total_sales');
            }

            Payment::where('order_id', $order->id)->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ]);

            if ($order->checkout_id) {
                $this->syncCheckoutPaymentStatus($order->checkout);
                $checkout = $order->checkout->fresh('orders');
                if ($checkout->isFullyPaid()) {
                    $this->invoices->sendInvoices($checkout);
                }
            }

            $order->buyer->notify(new PaymentConfirmedNotification($order));
            $order->seller?->notify(new PaymentConfirmedNotification($order, $order->items->first()));

            return $order->fresh('items');
        });
    }

    public function syncCheckoutPaymentStatus(Checkout $checkout): void
    {
        $checkout->load('orders');

        $allPaid = $checkout->orders->every(fn (Order $o) => $o->payment_status === PaymentStatus::Paid);
        $anyPaid = $checkout->orders->contains(fn (Order $o) => $o->payment_status === PaymentStatus::Paid);

        $checkout->update([
            'payment_status' => $allPaid ? PaymentStatus::Paid : ($anyPaid ? PaymentStatus::Partial : PaymentStatus::Pending),
            'status' => $allPaid ? OrderStatus::Processing : OrderStatus::Pending,
        ]);
    }

    public function confirmCashOnDelivery(Checkout $checkout): Checkout
    {
        foreach ($checkout->orders as $order) {
            $order->update(['status' => OrderStatus::Processing, 'payment_method' => 'cash']);
        }

        $checkout->update(['status' => OrderStatus::Processing]);
        $checkout->buyer->notify(new OrderPlacedNotification($checkout->orders->first(), cashOnDelivery: true, checkout: $checkout));

        foreach ($checkout->orders as $order) {
            foreach ($order->items as $item) {
                $item->seller->notify(new PaymentConfirmedNotification($order, $item, cashOnDelivery: true));
            }
        }

        return $checkout;
    }

    public function cartGroupedBySeller(User $buyer): Collection
    {
        return CartItem::with(['product.seller.sellerProfile.paymentMethods'])
            ->where('user_id', $buyer->id)
            ->get()
            ->groupBy(fn ($item) => $item->product->seller_id);
    }

    private function resolvePaymentChannel($profile, array $choice): PaymentChannel
    {
        $wantsDirect = ($choice['channel'] ?? '') === 'direct';
        $acceptsMarketplace = $profile?->accept_marketplace_payments ?? true;
        $acceptsDirect = $profile?->accept_direct_payments ?? false;

        if ($wantsDirect && $acceptsDirect) {
            return PaymentChannel::Direct;
        }

        if ($acceptsMarketplace) {
            return PaymentChannel::Marketplace;
        }

        if ($acceptsDirect) {
            return PaymentChannel::Direct;
        }

        return PaymentChannel::Marketplace;
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

            if ($wasPaid && $order->payment_channel === PaymentChannel::Marketplace) {
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

            if ($order->checkout_id) {
                $this->syncCheckoutPaymentStatus($order->checkout);
            }

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
