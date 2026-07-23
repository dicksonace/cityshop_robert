<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BuyerAddress;
use App\Models\Checkout;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaystackService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaystackService $paystack,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $grouped = $this->orderService->cartGroupedBySeller($request->user());
        $subtotal = $grouped->flatten()->sum(fn ($item) => $item->subtotal());

        $sellerGroups = $grouped->map(function ($items, $sellerId) {
            $seller = $items->first()->product->seller;
            $profile = $seller->sellerProfile;
            $shipping = OrderService::shippingMetaForSellerItems($items);

            return [
                'seller_id' => (int) $sellerId,
                'seller_name' => $profile?->displayName() ?? $seller->name,
                'store_slug' => $profile?->slug,
                'accept_marketplace_payments' => $profile?->accept_marketplace_payments ?? true,
                'accept_direct_payments' => $profile?->accept_direct_payments ?? false,
                'items' => $items->map(fn ($item) => [
                    'cart_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->product?->effectivePrice(),
                    'subtotal' => $item->subtotal(),
                ])->values(),
                'subtotal' => $items->sum(fn ($item) => $item->subtotal()),
                'shipping_cost' => $shipping['cost'],
                'shipping_label' => $shipping['label'],
                'shipping_note' => $shipping['note'],
                'package_total' => round($items->sum(fn ($item) => $item->subtotal()) + $shipping['cost'], 2),
            ];
        })->values();

        $shippingTotal = $sellerGroups->sum('shipping_cost');
        $addresses = $request->user()
            ->buyerAddresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map->toInertia()
            ->values();

        return response()->json([
            'seller_groups' => $sellerGroups,
            'subtotal' => round($subtotal, 2),
            'shipping_total' => round($shippingTotal, 2),
            'grand_total' => round($subtotal + $shippingTotal, 2),
            'addresses' => $addresses,
            'wallet' => $this->walletPayload($request->user()),
            'paystack_public_key' => config('services.paystack.public_key'),
            'paystack_configured' => $this->paystack->isConfigured(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'address_id' => ['required', 'integer'],
            'payment_method' => ['required', 'in:momo,card,cash,wallet'],
            'seller_payments' => ['nullable', 'array'],
            'seller_payments.*.channel' => ['required_with:seller_payments', 'in:marketplace,direct'],
            'seller_payments.*.method_id' => ['nullable', 'integer'],
            'seller_coupons' => ['nullable', 'array'],
            'seller_coupons.*' => ['nullable', 'string', 'max:30'],
        ]);

        $address = BuyerAddress::query()
            ->whereKey($request->integer('address_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $checkout = $this->orderService->createCheckoutFromCart(
                $request->user(),
                $address->toShippingArray(),
                $request->string('payment_method')->toString(),
                $request->input('seller_payments', []),
                $request->input('seller_coupons', []),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $method = $request->string('payment_method')->toString();

        if ($method === 'cash') {
            $this->orderService->confirmCashOnDelivery($checkout);

            return response()->json([
                'message' => 'Order placed. Pay on delivery.',
                'checkout' => $this->checkoutPayload($checkout->fresh(['orders.items'])),
                'next' => 'orders',
            ], 201);
        }

        if ($method === 'wallet') {
            try {
                $this->orderService->payCheckoutWithWallet($checkout, $request->user());
            } catch (ValidationException $e) {
                throw $e;
            }

            $checkout = $checkout->fresh(['orders.items']);
            $hasDirect = $checkout->orders
                ->contains(fn ($order) => $order->payment_channel === PaymentChannel::Direct);

            return response()->json([
                'message' => $hasDirect
                    ? 'Wallet payment applied. Complete direct seller payments.'
                    : 'Order paid from wallet.',
                'checkout' => $this->checkoutPayload($checkout),
                'next' => $hasDirect ? 'direct_payment' : 'orders',
            ], 201);
        }

        return response()->json([
            'message' => 'Checkout created. Complete payment.',
            'checkout' => $this->checkoutPayload($checkout->fresh(['orders.items'])),
            'next' => 'paystack_or_direct',
        ], 201);
    }

    public function show(Request $request, Checkout $checkout): JsonResponse
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        $checkout->load(['orders.items', 'orders.sellerPaymentMethod', 'orders.seller.sellerProfile']);

        return response()->json([
            'checkout' => $this->checkoutPayload($checkout),
            'marketplace_total' => (float) $checkout->orders
                ->where('payment_channel', PaymentChannel::Marketplace)
                ->sum('total'),
            'paystack_public_key' => config('services.paystack.public_key'),
            'paystack_configured' => $this->paystack->isConfigured(),
        ]);
    }

    public function payWithWallet(Request $request, Checkout $checkout): JsonResponse
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        try {
            $this->orderService->payCheckoutWithWallet($checkout, $request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Paid with wallet.',
            'checkout' => $this->checkoutPayload($checkout->fresh(['orders.items'])),
        ]);
    }

    public function initializePaystack(Request $request, Checkout $checkout): JsonResponse
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        if ($checkout->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Already paid'], 422);
        }

        if (! $this->paystack->isConfigured()) {
            return response()->json(['message' => 'Paystack is not configured.'], 503);
        }

        $checkout->loadMissing('orders');

        $amount = $checkout->orders
            ->where('payment_channel', PaymentChannel::Marketplace)
            ->sum('total');

        if ($amount <= 0) {
            return response()->json(['message' => 'No marketplace payment required for this checkout.'], 422);
        }

        $reference = 'CSH-'.uniqid();

        try {
            $data = $this->paystack->initializeTransaction(
                $request->user()->email,
                (float) $amount,
                $reference,
                ['checkout_id' => $checkout->id, 'checkout_number' => $checkout->checkout_number]
            );

            return response()->json([
                'authorization_url' => $data['authorization_url'],
                'access_code' => $data['access_code'],
                'reference' => $data['reference'],
                'email' => $request->user()->email,
                'amount' => (float) $amount,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function submitDirectPayment(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless($order->payment_channel === PaymentChannel::Direct, 422);

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'This payment is already confirmed.'], 422);
        }

        if ($order->status === OrderStatus::Cancelled
            || $order->items()->where('status', '!=', OrderStatus::Cancelled)->doesntExist()) {
            return response()->json(['message' => 'This order was cancelled.'], 422);
        }

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $reference = trim((string) ($validated['reference'] ?? ''));
        $hasProof = $request->hasFile('proof') || filled($order->direct_payment_proof_path);

        if ($reference === '' && ! $hasProof) {
            throw ValidationException::withMessages([
                'proof' => 'Upload a payment screenshot, or enter a transaction ID.',
            ]);
        }

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('direct-payment-proofs', 'public');
        }

        $this->orderService->submitDirectPaymentReference(
            $order,
            $reference !== '' ? $reference : null,
            $proofPath,
        );

        return response()->json([
            'message' => 'Payment proof submitted.',
            'order' => $this->orderPayload($order->fresh('items')),
        ]);
    }

    private function walletPayload($user): array
    {
        $wallet = WalletService::ensure($user);

        return [
            'available_balance' => (float) $wallet->available_balance,
            'pending_balance' => (float) $wallet->pending_balance,
        ];
    }

    private function checkoutPayload(Checkout $checkout): array
    {
        $checkout->loadMissing(['orders.items', 'orders.seller.sellerProfile']);

        return [
            'id' => $checkout->id,
            'checkout_number' => $checkout->checkout_number,
            'status' => $checkout->status?->value,
            'payment_status' => $checkout->payment_status?->value,
            'subtotal' => (float) $checkout->subtotal,
            'shipping_cost' => (float) $checkout->shipping_cost,
            'total' => (float) $checkout->total,
            'orders' => $checkout->orders->map(fn (Order $order) => $this->orderPayload($order))->values(),
        ];
    }

    private function orderPayload(Order $order): array
    {
        $order->loadMissing(['items', 'seller.sellerProfile']);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'payment_status' => $order->payment_status?->value,
            'payment_channel' => $order->payment_channel?->value,
            'payment_method' => $order->payment_method,
            'subtotal' => (float) $order->subtotal,
            'shipping_cost' => (float) $order->shipping_cost,
            'total' => (float) $order->total,
            'seller' => [
                'id' => $order->seller_id,
                'store_name' => $order->seller?->sellerProfile?->displayName() ?? $order->seller?->name,
            ],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => $item->lineTotal(),
                'status' => $item->status?->value,
            ])->values(),
        ];
    }
}
