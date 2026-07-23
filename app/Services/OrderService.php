<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\DisputeStatus;
use App\Enums\FundsReleaseStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
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
use App\Models\WalletTransaction;
use App\Notifications\DirectPaymentRejectedNotification;
use App\Notifications\DisputeOpenedNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        if ($buyer->isSeller()) {
            throw new \RuntimeException(\App\Http\Middleware\PreventSellerShopping::MESSAGE);
        }

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
            $totalShipping = 0;

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

                $shippingCost = static::shippingCostForSellerItems($items);
                if ($appliedCoupon?->type === CouponType::FreeShipping) {
                    $shippingCost = 0;
                }

                $goodsTotal = max(0, round($orderSubtotal - $couponDiscount, 2));
                $orderCommission = $channel === PaymentChannel::Marketplace
                    ? round($goodsTotal * ($commissionRate / 100), 2)
                    : 0;
                $orderTotal = round($goodsTotal + $shippingCost, 2);
                $totalDiscount += $couponDiscount;
                $totalCommission += $orderCommission;
                $totalShipping += $shippingCost;
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
                    'shipping_cost' => $shippingCost,
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

                if ($paymentMethod !== 'cash') {
                    $order->seller->notify(new PaymentConfirmedNotification($order->load('items'), $order->items->first(), pendingOrder: true));
                    if ($order->seller) {
                        AppNotificationService::notifySellerNewOrder(
                            $order->seller,
                            $order,
                            $order->items->first(),
                            pendingOrder: true,
                        );
                    }
                }
            }

            $checkout->update([
                'discount_amount' => $totalDiscount,
                'commission_amount' => $totalCommission,
                'shipping_cost' => $totalShipping,
                'total' => max(0, round($subtotal - $totalDiscount + $totalShipping, 2)),
            ]);

            CartItem::where('user_id', $buyer->id)->delete();

            $checkout = $checkout->load('orders.items.seller');
            if ($paymentMethod !== 'cash') {
                $buyer->notify(new OrderPlacedNotification($checkout->orders->first(), checkout: $checkout));
            }

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

    public function payCheckoutWithWallet(Checkout $checkout, User $buyer): Checkout
    {
        abort_unless($checkout->buyer_id === $buyer->id, 403);

        if ($checkout->payment_status === PaymentStatus::Paid) {
            return $checkout;
        }

        $checkout->load('orders');

        $marketplaceTotal = (float) $checkout->orders
            ->where('payment_channel', PaymentChannel::Marketplace)
            ->sum('total');

        if ($marketplaceTotal <= 0) {
            throw ValidationException::withMessages([
                'payment_method' => 'Wallet payment only applies to CityShop marketplace orders.',
            ]);
        }

        return DB::transaction(function () use ($checkout, $buyer, $marketplaceTotal) {
            $wallet = Wallet::where('user_id', $buyer->id)->lockForUpdate()->first()
                ?? WalletService::ensure($buyer);

            if ((float) $wallet->available_balance < $marketplaceTotal) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Insufficient wallet balance. Add funds or choose another payment method.',
                ]);
            }

            $reference = 'WAL-'.$checkout->checkout_number;

            if (WalletTransaction::where('reference', $reference)->exists()) {
                return $this->fulfillPaidCheckout($checkout, $reference);
            }

            $wallet->decrement('available_balance', $marketplaceTotal);

            WalletTransactionService::recordOrderPayment(
                $buyer->id,
                $marketplaceTotal,
                $checkout->checkout_number,
                $reference,
            );

            return $this->fulfillPaidCheckout($checkout, $reference);
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
                if ($seller) {
                    AppNotificationService::notifySellerNewOrder($seller, $order, $item);
                }
            }

            if ($order->payment_channel === PaymentChannel::Marketplace && (float) $order->shipping_cost > 0) {
                $this->creditSellerShippingPending($order);
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

    public function submitDirectPaymentReference(Order $order, string $reference, ?string $proofPath = null): Order
    {
        if ($order->payment_channel !== PaymentChannel::Direct) {
            throw new \RuntimeException('This order does not use direct seller payment.');
        }

        $payload = [
            'direct_payment_reference' => $reference,
            'direct_payment_rejection_reason' => null,
        ];

        if ($proofPath !== null) {
            if ($order->direct_payment_proof_path) {
                Storage::disk('public')->delete($order->direct_payment_proof_path);
            }
            $payload['direct_payment_proof_path'] = $proofPath;
        }

        $order->update($payload);

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
                'direct_payment_rejection_reason' => null,
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
            if ($order->seller) {
                AppNotificationService::notifySellerNewOrder($order->seller, $order, $order->items->first());
            }

            return $order->fresh('items');
        });
    }

    public function rejectDirectPayment(Order $order, User $rejector, string $reason): Order
    {
        if ($order->seller_id !== $rejector->id) {
            throw new \RuntimeException('Only the selling store can reject this payment claim.');
        }

        if ($order->payment_channel !== PaymentChannel::Direct) {
            throw new \RuntimeException('Not a direct payment order.');
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            throw new \RuntimeException('This payment is already confirmed.');
        }

        if (! filled($order->direct_payment_reference) && ! filled($order->direct_payment_proof_path)) {
            throw ValidationException::withMessages([
                'reason' => 'The buyer has not submitted a payment claim to reject.',
            ]);
        }

        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'reason' => 'Please explain why you are rejecting this payment.',
            ]);
        }

        if ($order->direct_payment_proof_path) {
            Storage::disk('public')->delete($order->direct_payment_proof_path);
        }

        $order->update([
            'direct_payment_reference' => null,
            'direct_payment_proof_path' => null,
            'direct_payment_rejection_reason' => $trimmed,
        ]);

        $order->buyer->notify(new DirectPaymentRejectedNotification($order->fresh(), $trimmed));

        return $order->fresh('items');
    }

    public function syncCheckoutPaymentStatus(Checkout $checkout): void
    {
        $checkout->load('orders');

        if ($checkout->orders->isNotEmpty()
            && $checkout->orders->every(fn (Order $o) => $o->status === OrderStatus::Cancelled)) {
            $allRefunded = $checkout->orders->every(fn (Order $o) => $o->payment_status === PaymentStatus::Refunded);
            $anyPaidOrRefunded = $checkout->orders->contains(fn (Order $o) => in_array($o->payment_status, [
                PaymentStatus::Paid,
                PaymentStatus::Refunded,
            ], true));

            $checkout->update([
                'status' => OrderStatus::Cancelled,
                'payment_status' => $allRefunded
                    ? PaymentStatus::Refunded
                    : ($anyPaidOrRefunded ? PaymentStatus::Partial : PaymentStatus::Failed),
            ]);

            return;
        }

        $openOrders = $checkout->orders->reject(fn (Order $o) => $o->status === OrderStatus::Cancelled);
        $paymentOrders = $openOrders->isNotEmpty() ? $openOrders : $checkout->orders;

        $allPaid = $paymentOrders->every(fn (Order $o) => $o->payment_status === PaymentStatus::Paid);
        $anyPaid = $paymentOrders->contains(fn (Order $o) => $o->payment_status === PaymentStatus::Paid);

        $checkout->update([
            'payment_status' => $allPaid ? PaymentStatus::Paid : ($anyPaid ? PaymentStatus::Partial : PaymentStatus::Pending),
            'status' => $allPaid ? OrderStatus::Processing : OrderStatus::Pending,
        ]);
    }

    public function confirmCashOnDelivery(Checkout $checkout): Checkout
    {
        foreach ($checkout->orders as $order) {
            $order->update([
                'status' => OrderStatus::Pending,
                'payment_method' => 'cash',
                'payment_status' => PaymentStatus::Pending,
            ]);
        }

        $checkout->update(['status' => OrderStatus::Pending]);
        $checkout->buyer->notify(new OrderPlacedNotification($checkout->orders->first(), cashOnDelivery: true, checkout: $checkout));

        foreach ($checkout->orders as $order) {
            foreach ($order->items as $item) {
                $item->seller->notify(new PaymentConfirmedNotification($order, $item, cashOnDelivery: true));
                if ($item->seller) {
                    AppNotificationService::notifySellerNewOrder(
                        $item->seller,
                        $order,
                        $item,
                        cashOnDelivery: true,
                    );
                }
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

    public function releaseSellerFunds(OrderItem $item, ?int $reviewedBy = null, bool $adminOverride = false): void
    {
        $item->load(['order', 'seller']);

        if ($item->funds_release_status === FundsReleaseStatus::Released) {
            return;
        }

        if ($item->status !== OrderStatus::Delivered) {
            throw new \RuntimeException('Buyer must confirm delivery before funds are released.');
        }

        if (! in_array($item->funds_release_status, [FundsReleaseStatus::Pending, FundsReleaseStatus::Held], true)) {
            throw new \RuntimeException('This item is not waiting for fund release approval.');
        }

        if (
            ! $adminOverride
            && Dispute::where('order_item_id', $item->id)->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->exists()
        ) {
            throw new \RuntimeException('Cannot release funds while a dispute is open.');
        }

        DB::transaction(function () use ($item, $reviewedBy) {
            if ($item->order->payment_channel === PaymentChannel::Marketplace) {
                $wallet = Wallet::where('user_id', $item->seller_id)->firstOrFail();
                $amount = (float) $item->seller_amount;

                $wallet->decrement('pending_balance', min($amount, (float) $wallet->pending_balance));
                $wallet->increment('available_balance', $amount);

                WalletTransactionService::recordSaleReleased($item);
            }

            $item->update([
                'funds_release_status' => FundsReleaseStatus::Released,
                'funds_release_notes' => null,
                'funds_reviewed_by' => $reviewedBy,
                'funds_released_at' => now(),
            ]);
        });

        $this->maybeReleaseSellerShipping($item->order->fresh('items'));
    }

    /**
     * Admin reject: keep sale in pending balance and open a dispute for review.
     */
    public function holdSellerFunds(OrderItem $item, string $reason, int $reviewedBy): void
    {
        $item->load(['order', 'seller', 'order.buyer']);

        if ($item->status !== OrderStatus::Delivered) {
            throw new \RuntimeException('Only confirmed deliveries can have funds held.');
        }

        if ($item->funds_release_status !== FundsReleaseStatus::Pending) {
            throw new \RuntimeException('This item is not waiting for fund release approval.');
        }

        DB::transaction(function () use ($item, $reason, $reviewedBy) {
            $item->update([
                'funds_release_status' => FundsReleaseStatus::Held,
                'funds_release_notes' => $reason,
                'funds_reviewed_by' => $reviewedBy,
            ]);

            $existing = Dispute::where('order_item_id', $item->id)
                ->whereNotIn('status', [DisputeStatus::Cancelled])
                ->exists();

            if ($existing) {
                return;
            }

            $dispute = Dispute::create([
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'buyer_id' => $item->order->buyer_id,
                'seller_id' => $item->seller_id,
                'reason' => 'other',
                'description' => 'Admin held pending fund release: '.$reason,
                'status' => DisputeStatus::Open,
            ]);

            $dispute->load('order');

            $item->seller->notify(new DisputeOpenedNotification($dispute));
            $item->order->buyer->notify(new DisputeOpenedNotification($dispute));

            $admins = User::where('role', UserRole::Admin)->get();
            Notification::send($admins, new DisputeOpenedNotification($dispute));
        });
    }

    public function confirmBuyerDelivery(OrderItem $item): OrderItem
    {
        $item->load(['order', 'seller']);

        if ($item->status !== OrderStatus::AwaitingConfirmation) {
            throw new \RuntimeException('This item is not waiting for delivery confirmation.');
        }

        if (Dispute::where('order_item_id', $item->id)->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->exists()) {
            throw new \RuntimeException('Cannot confirm delivery while a dispute is open.');
        }

        DB::transaction(function () use ($item) {
            $isMarketplace = $item->order->payment_channel === PaymentChannel::Marketplace;

            $item->update([
                'status' => OrderStatus::Delivered,
                'funds_release_status' => $isMarketplace
                    ? FundsReleaseStatus::Pending
                    : FundsReleaseStatus::NotApplicable,
            ]);

            $item->seller->notify(new OrderStatusUpdatedNotification($item, 'delivered'));
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'delivered'));
        });

        $this->syncOrderStatusAfterItemChange($item->order->fresh('items'));

        return $item->fresh();
    }

    public function updateOrderItemStatus(OrderItem $item, array $data): OrderItem
    {
        $previousStatus = $item->status->value;

        $allowed = ['status', 'vehicle_number', 'driver_phone', 'package_image'];
        $payload = array_intersect_key($data, array_flip($allowed));

        if (isset($payload['status'])) {
            $this->assertValidStatusTransition($item, OrderStatus::from($payload['status']));
        }

        $item->update($payload);

        if ($data['status'] === 'packed' && $previousStatus !== 'packed') {
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'packed'));
        }

        if ($data['status'] === 'shipped' && $previousStatus !== 'shipped') {
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'shipped'));
        }

        if ($data['status'] === 'awaiting_confirmation' && $previousStatus !== 'awaiting_confirmation') {
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'awaiting_confirmation'));
        }

        if ($data['status'] === 'call_confirmed' && $previousStatus !== 'call_confirmed') {
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'call_confirmed'));
        }

        if ($data['status'] === 'delivered' && $previousStatus !== 'delivered' && $item->order->payment_method === 'cash') {
            $item->order->update(['payment_status' => PaymentStatus::Paid, 'status' => OrderStatus::Delivered]);
            $item->order->buyer->notify(new OrderStatusUpdatedNotification($item, 'delivered'));
        }

        $this->syncOrderStatusAfterItemChange($item->order);

        return $item->fresh();
    }

    private function assertValidStatusTransition(OrderItem $item, OrderStatus $next): void
    {
        $item->loadMissing('order');
        $isCod = $item->order?->payment_method === 'cash';

        $flow = $isCod
            ? [
                OrderStatus::Pending->value => [OrderStatus::Processing],
                OrderStatus::Processing->value => [OrderStatus::CallConfirmed],
                OrderStatus::CallConfirmed->value => [OrderStatus::Packed],
                OrderStatus::Packed->value => [OrderStatus::Shipped],
                OrderStatus::Shipped->value => [OrderStatus::Delivered],
            ]
            : [
                OrderStatus::Pending->value => [OrderStatus::Processing],
                OrderStatus::Processing->value => [OrderStatus::Packed],
                OrderStatus::Packed->value => [OrderStatus::Shipped],
                OrderStatus::Shipped->value => [OrderStatus::AwaitingConfirmation],
            ];

        $current = $item->status->value;
        $allowed = $flow[$current] ?? [];

        if (! in_array($next, $allowed, true)) {
            throw new \RuntimeException("Cannot change status from {$current} to {$next->value}.");
        }
    }

    public function rejectOrderItem(
        OrderItem $item,
        string $reason,
        ?string $cancellationCode = null,
        string $cancelledBy = \App\Support\OrderCancellation::BY_SELLER,
    ): OrderItem {
        $item->load(['order', 'product', 'seller.sellerProfile']);

        if (in_array($item->status, [OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            throw new \RuntimeException('This order item can no longer be cancelled.');
        }

        if (in_array($item->status, [OrderStatus::Shipped, OrderStatus::AwaitingConfirmation], true)) {
            throw new \RuntimeException('Cannot cancel an order that is already out for delivery.');
        }

        if (Dispute::where('order_item_id', $item->id)->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])->exists()) {
            throw new \RuntimeException('Cannot cancel while a dispute is open.');
        }

        return DB::transaction(function () use ($item, $reason, $cancellationCode, $cancelledBy) {
            $order = $item->order;
            $refundAmount = $item->lineTotal();
            $wasPaid = $order->payment_status === PaymentStatus::Paid;
            $refundStatus = \App\Support\OrderCancellation::REFUND_NOT_APPLICABLE;

            $item->update([
                'status' => OrderStatus::Cancelled,
                'rejection_reason' => $reason,
                'cancellation_code' => $cancellationCode,
                'cancelled_by' => $cancelledBy,
                'cancelled_at' => now(),
                'refund_status' => $refundStatus,
            ]);

            if ($wasPaid && $order->payment_channel === PaymentChannel::Marketplace) {
                try {
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

                    $refundStatus = \App\Support\OrderCancellation::REFUND_COMPLETED;
                    $item->update(['refund_status' => $refundStatus]);
                } catch (\Throwable $e) {
                    $item->update(['refund_status' => \App\Support\OrderCancellation::REFUND_FAILED]);
                    throw $e;
                }
            } elseif ($wasPaid) {
                // Direct / other channels: restore stock only; no CityShop wallet refund.
                $product = Product::find($item->product_id);
                if ($product && ! $product->is_preorder) {
                    $product->increment('quantity', $item->quantity);
                }
                $item->update(['refund_status' => \App\Support\OrderCancellation::REFUND_NOT_APPLICABLE]);
            }

            $this->syncOrderStatusAfterItemChange($order->fresh('items'));

            // If the whole seller package was cancelled after payment, refund delivery too.
            $order->refresh()->load('items');
            if (
                $wasPaid
                && $order->payment_channel === PaymentChannel::Marketplace
                && (float) $order->shipping_cost > 0
                && $order->items->every(fn ($i) => $i->status === OrderStatus::Cancelled)
            ) {
                $this->refundSellerShipping($order);
            }

            $order->buyer->notify(new OrderStatusUpdatedNotification(
                $item->fresh(),
                'cancelled',
                refunded: $wasPaid && $order->payment_channel === PaymentChannel::Marketplace,
                refundAmount: $refundAmount,
            ));

            return $item->fresh();
        });
    }

    /**
     * Paid marketplace items still at "pending" (seller never started) for 24+ hours.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\OrderItem>
     */
    public function staleUnprocessedItemsQuery(int $hours = 24)
    {
        $cutoff = now()->subHours($hours);

        return OrderItem::query()
            ->whereIn('status', [
                OrderStatus::Pending,
                OrderStatus::Processing,
                OrderStatus::CallConfirmed,
                OrderStatus::Packed,
            ])
            ->whereHas('order', function ($q) use ($cutoff) {
                $q->where('payment_status', PaymentStatus::Paid)
                    ->where(function ($channel) {
                        $channel->where('payment_channel', PaymentChannel::Marketplace)
                            ->orWhereNull('payment_channel');
                    })
                    ->where(function ($paid) use ($cutoff) {
                        $paid->whereHas('payments', function ($p) use ($cutoff) {
                            $p->whereNotNull('paid_at')->where('paid_at', '<=', $cutoff);
                        })->orWhereHas('checkout.payments', function ($p) use ($cutoff) {
                            $p->whereNotNull('paid_at')->where('paid_at', '<=', $cutoff);
                        });
                    });
            });
    }

    public function itemPaidAt(OrderItem $item): ?\Carbon\CarbonInterface
    {
        $item->loadMissing(['order.payments', 'order.checkout.payments']);

        $dates = collect()
            ->merge($item->order->payments ?? [])
            ->merge($item->order->checkout?->payments ?? [])
            ->pluck('paid_at')
            ->filter();

        return $dates->min();
    }

    /**
     * Admin cancels a paid item that the seller has not completed (queue / stuck fulfillment).
     * Marketplace refunds go to the buyer wallet.
     */
    public function adminCancelUnprocessedItem(OrderItem $item, string $reason): OrderItem
    {
        $item->load(['order', 'seller']);

        $cancellable = [
            OrderStatus::Pending,
            OrderStatus::Processing,
            OrderStatus::CallConfirmed,
            OrderStatus::Packed,
        ];

        if (! in_array($item->status, $cancellable, true)) {
            throw new \RuntimeException('This order is too far along to cancel from this queue (already out for delivery or finished).');
        }

        $order = $item->order;
        if ($order->payment_status !== PaymentStatus::Paid) {
            throw new \RuntimeException('This order is not paid yet.');
        }

        if ($order->payment_channel === PaymentChannel::Direct) {
            throw new \RuntimeException('Direct-to-seller payments cannot be auto-refunded to the CityShop wallet. Handle those manually.');
        }

        $adminReason = trim($reason) !== ''
            ? $reason
            : 'Admin cancelled: seller did not process the order in time.';

        return $this->rejectOrderItem(
            $item,
            $adminReason,
            'unable_to_fulfill',
            \App\Support\OrderCancellation::BY_ADMIN,
        );
    }

    private function syncOrderStatusAfterItemChange(Order $order): void
    {
        $order->load('items');
        $statuses = $order->items->pluck('status');

        if ($statuses->every(fn ($s) => $s === OrderStatus::Cancelled)) {
            $nextPaymentStatus = match (true) {
                $order->payment_status === PaymentStatus::Paid,
                $order->payment_status === PaymentStatus::Refunded => PaymentStatus::Refunded,
                default => PaymentStatus::Failed,
            };

            $order->update([
                'status' => OrderStatus::Cancelled,
                'payment_status' => $nextPaymentStatus,
            ]);

            if ($order->checkout_id) {
                $this->syncCheckoutPaymentStatus(
                    $order->checkout()->with('orders')->firstOrFail()
                );
            }

            return;
        }

        if ($statuses->contains(OrderStatus::Delivered) && $statuses->every(fn ($s) => in_array($s, [OrderStatus::Delivered, OrderStatus::Cancelled], true))) {
            $order->update(['status' => OrderStatus::Delivered]);
            $this->maybeReleaseSellerShipping($order);

            return;
        }

        if ($statuses->contains(OrderStatus::AwaitingConfirmation)) {
            $order->update(['status' => OrderStatus::AwaitingConfirmation]);

            return;
        }

        if ($statuses->contains(OrderStatus::Shipped)) {
            $order->update(['status' => OrderStatus::Shipped]);

            return;
        }

        if ($statuses->contains(OrderStatus::Packed)) {
            $order->update(['status' => OrderStatus::Packed]);

            return;
        }

        if ($statuses->contains(OrderStatus::CallConfirmed)) {
            $order->update(['status' => OrderStatus::CallConfirmed]);

            return;
        }

        if ($statuses->contains(OrderStatus::Processing)) {
            $order->update(['status' => OrderStatus::Processing]);
        }
    }

    /**
     * Flat delivery fee per seller package = highest paid delivery fee among that seller's cart lines.
     * Free shipping and "arrange with buyer" count as 0 for that line.
     */
    public static function shippingCostForSellerItems(Collection $items): float
    {
        $fees = [];

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            if ($product->free_shipping) {
                $fees[] = 0.0;

                continue;
            }

            $fee = $product->delivery_fee;
            $fees[] = ($fee !== null && (float) $fee > 0) ? (float) $fee : 0.0;
        }

        return round($fees === [] ? 0.0 : max($fees), 2);
    }

    /**
     * @return array{cost: float, label: string, note: string|null}
     */
    public static function shippingMetaForSellerItems(Collection $items): array
    {
        $cost = static::shippingCostForSellerItems($items);
        $products = $items->map(fn ($item) => $item->product)->filter();

        $allFree = $products->isNotEmpty() && $products->every(fn (Product $p) => $p->free_shipping);
        $anyPaid = $products->contains(fn (Product $p) => ! $p->free_shipping && $p->delivery_fee !== null && (float) $p->delivery_fee > 0);
        $anyArrange = $products->contains(fn (Product $p) => ! $p->free_shipping && ($p->delivery_fee === null || (float) $p->delivery_fee <= 0));

        if ($cost > 0) {
            return [
                'cost' => $cost,
                'label' => 'Delivery',
                'note' => $products->count() > 1 ? 'One delivery fee for this seller’s package' : null,
            ];
        }

        if ($allFree) {
            return ['cost' => 0.0, 'label' => 'Free delivery', 'note' => null];
        }

        if ($anyArrange && ! $anyPaid) {
            return [
                'cost' => 0.0,
                'label' => 'Arrange with seller',
                'note' => 'Delivery fee is agreed after order',
            ];
        }

        return ['cost' => 0.0, 'label' => 'Free delivery', 'note' => null];
    }

    private function creditSellerShippingPending(Order $order): void
    {
        $amount = (float) $order->shipping_cost;
        if ($amount <= 0 || ! $order->seller_id) {
            return;
        }

        if (WalletTransactionService::shippingPendingExists($order->id)) {
            return;
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $order->seller_id],
            ['available_balance' => 0, 'pending_balance' => 0, 'total_earnings' => 0, 'withdrawn_amount' => 0]
        );

        $wallet->increment('pending_balance', $amount);
        $wallet->increment('total_earnings', $amount);

        WalletTransactionService::recordShippingPending($order);
    }

    /**
     * Release shipping only after every active marketplace line has funds released by admin.
     */
    private function maybeReleaseSellerShipping(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->payment_channel !== PaymentChannel::Marketplace) {
            return;
        }

        $active = $order->items->filter(fn (OrderItem $item) => $item->status !== OrderStatus::Cancelled);

        if ($active->isEmpty()) {
            return;
        }

        $ready = $active->every(
            fn (OrderItem $item) => $item->status === OrderStatus::Delivered
                && $item->funds_release_status === FundsReleaseStatus::Released
        );

        if (! $ready) {
            return;
        }

        $this->releaseSellerShipping($order);
    }

    private function releaseSellerShipping(Order $order): void
    {
        $amount = (float) $order->shipping_cost;
        if ($amount <= 0 || ! $order->seller_id) {
            return;
        }

        if (! WalletTransactionService::shippingPendingExists($order->id)) {
            return;
        }

        if (WalletTransactionService::shippingReleasedExists($order->id)) {
            return;
        }

        $wallet = Wallet::where('user_id', $order->seller_id)->first();
        if (! $wallet) {
            return;
        }

        $move = min($amount, (float) $wallet->pending_balance);
        if ($move > 0) {
            $wallet->decrement('pending_balance', $move);
            $wallet->increment('available_balance', $move);
        }

        WalletTransactionService::recordShippingReleased($order, $amount);
    }

    private function refundSellerShipping(Order $order): void
    {
        $amount = (float) $order->shipping_cost;
        if ($amount <= 0 || ! $order->seller_id) {
            return;
        }

        if (! WalletTransactionService::shippingPendingExists($order->id)) {
            return;
        }

        if (WalletTransactionService::shippingRefundedExists($order->id)) {
            return;
        }

        $buyerWallet = WalletService::ensure($order->buyer);
        $buyerWallet->increment('available_balance', $amount);
        WalletTransactionService::recordShippingRefund($order, $amount);

        $sellerWallet = Wallet::where('user_id', $order->seller_id)->first();
        if ($sellerWallet) {
            $sellerWallet->decrement('pending_balance', min($amount, (float) $sellerWallet->pending_balance));
            $sellerWallet->decrement('total_earnings', min($amount, (float) $sellerWallet->total_earnings));
        }

        WalletTransactionService::recordShippingReversed($order, $amount);
    }

    public function recalculateProductRating(Product $product): void
    {
        ReviewService::syncProductRating($product);
    }
}
