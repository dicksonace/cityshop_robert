<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BuyerAddress;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutPaymentVerifier;
use App\Services\OrderService;
use App\Services\PaymentPinService;
use App\Services\PaystackService;
use App\Services\WalletService;
use App\Support\DirectCheckoutDraft;
use App\Support\PendingCheckoutDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaystackService $paystack,
        private CheckoutPaymentVerifier $paymentVerifier,
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
                'accepts_cash' => $profile?->acceptsCashOnDelivery() ?? true,
                'payment_methods' => ($profile?->paymentMethods ?? collect())
                    ->where('is_active', true)
                    ->filter(fn ($method) => ! $method->isDisabled())
                    ->values()
                    ->map(fn ($method) => [
                        'id' => $method->id,
                        'type' => $method->type->value,
                        'label' => $method->label,
                        'account_name' => $method->account_name,
                        'account_number' => $method->account_number,
                        'network' => $method->network,
                        'bank_name' => $method->bank_name,
                        'instructions' => $method->instructions,
                        'display_label' => $method->displayLabel(),
                    ]),
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
            'paystack_fee' => $this->paystack->rechargeFeePayload(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'address_id' => ['required', 'integer'],
            'payment_method' => ['required', 'in:momo,card,cash,wallet'],
            'payment_pin' => ['required_if:payment_method,wallet', 'nullable', 'string', 'regex:/^\d{4}$/'],
            'seller_payments' => ['nullable', 'array'],
            'seller_payments.*.channel' => ['required_with:seller_payments', 'in:marketplace,direct'],
            'seller_payments.*.method_id' => ['nullable', 'integer'],
            'seller_coupons' => ['nullable', 'array'],
            'seller_coupons.*' => ['nullable', 'string', 'max:30'],
        ]);

        if ($request->string('payment_method')->toString() === 'wallet') {
            PaymentPinService::assertValidForAction($request->user(), $request->input('payment_pin'));
        }

        $address = BuyerAddress::query()
            ->whereKey($request->integer('address_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $shipping = $address->toShippingArray();
        $sellerPayments = $request->input('seller_payments', []);
        $sellerCoupons = $request->input('seller_coupons', []);
        $method = $request->string('payment_method')->toString();

        // Match web: pay-to-seller only — no order until proof / transaction ID is submitted.
        if ($method !== 'cash'
            && $this->orderService->cartIsDirectOnly($request->user(), $sellerPayments)) {
            DirectCheckoutDraft::putForUser(
                $request->user(),
                $address->id,
                $shipping,
                $sellerPayments,
                $sellerCoupons,
            );

            $packages = $this->orderService->directPayPackagesFromCart(
                $request->user(),
                $sellerPayments,
            )->map(fn (array $package) => $this->directPackagePayload($package))->values();

            return response()->json([
                'message' => 'Send payment to the seller, then upload proof. No order is created until you submit.',
                'next' => 'direct_pay',
                'packages' => $packages,
                'shipping' => $shipping,
            ]);
        }

        // Paystack (momo/card): hold choices in a draft — no order until payment succeeds.
        if (in_array($method, ['momo', 'card'], true)) {
            try {
                $amount = $this->orderService->marketplaceAmountFromCart(
                    $request->user(),
                    $sellerPayments,
                    $sellerCoupons,
                );
            } catch (ValidationException $e) {
                throw $e;
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            if ($amount <= 0) {
                // All sellers chose direct — fall through to direct-only style flow.
                DirectCheckoutDraft::putForUser(
                    $request->user(),
                    $address->id,
                    $shipping,
                    $sellerPayments,
                    $sellerCoupons,
                );

                $packages = $this->orderService->directPayPackagesFromCart(
                    $request->user(),
                    $sellerPayments,
                )->map(fn (array $package) => $this->directPackagePayload($package))->values();

                return response()->json([
                    'message' => 'Send payment to the seller, then upload proof. No order is created until you submit.',
                    'next' => 'direct_pay',
                    'packages' => $packages,
                    'shipping' => $shipping,
                ]);
            }

            PendingCheckoutDraft::putForUser(
                $request->user(),
                $address->id,
                $shipping,
                $sellerPayments,
                $sellerCoupons,
                $method,
            );
            DirectCheckoutDraft::clearForUser($request->user());

            $quote = $this->paystack->rechargeQuote((float) $amount, $method);

            return response()->json([
                'message' => 'Complete payment to place your order. No order is created until payment succeeds.',
                'next' => 'paystack',
                'amount' => $quote['credit'],
                'fee' => $quote['fee'],
                'charge' => $quote['charge'],
                'paystack_fee' => $this->paystack->rechargeFeePayload(),
                'paystack_configured' => $this->paystack->isConfigured(),
                'shipping' => $shipping,
            ]);
        }

        try {
            $checkout = $this->orderService->createCheckoutFromCart(
                $request->user(),
                $shipping,
                $method,
                $sellerPayments,
                $sellerCoupons,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        DirectCheckoutDraft::clearForUser($request->user());
        PendingCheckoutDraft::clearForUser($request->user());

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

    public function directPay(Request $request): JsonResponse
    {
        $draft = DirectCheckoutDraft::getForUser($request->user());
        if (! $draft) {
            return response()->json([
                'message' => 'Start checkout again to pay the seller.',
            ], 422);
        }

        $packages = $this->orderService->directPayPackagesFromCart(
            $request->user(),
            $draft['seller_payments'] ?? [],
        )->map(fn (array $package) => $this->directPackagePayload($package))->values();

        if ($packages->isEmpty()) {
            DirectCheckoutDraft::clearForUser($request->user());

            return response()->json([
                'message' => 'Your cart changed. Choose payment again.',
                'packages' => [],
            ], 422);
        }

        return response()->json([
            'packages' => $packages,
            'shipping' => $draft['shipping'] ?? null,
        ]);
    }

    public function submitDirectPay(Request $request, int $sellerId): JsonResponse
    {
        $draft = DirectCheckoutDraft::getForUser($request->user());
        if (! $draft) {
            return response()->json([
                'message' => 'Start checkout again to pay the seller.',
            ], 422);
        }

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $reference = trim((string) ($validated['reference'] ?? ''));
        $hasProof = $request->hasFile('proof');

        if ($reference === '' && ! $hasProof) {
            throw ValidationException::withMessages([
                'proof' => 'Upload a payment screenshot, or enter a transaction ID from your MoMo SMS.',
            ]);
        }

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('direct-payment-proofs', 'public');
        }

        try {
            $order = $this->orderService->createClaimedDirectOrderFromCart(
                $request->user(),
                $sellerId,
                $draft['shipping'],
                $draft['seller_payments'] ?? [],
                $draft['seller_coupons'] ?? [],
                $reference !== '' ? $reference : null,
                $proofPath,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $remaining = $this->orderService->directPayPackagesFromCart(
            $request->user(),
            $draft['seller_payments'] ?? [],
        );

        if ($remaining->isEmpty()) {
            DirectCheckoutDraft::clearForUser($request->user());
        }

        return response()->json([
            'message' => 'Payment submitted. The seller will confirm once received.',
            'order' => $this->orderPayload($order),
            'remaining_packages' => $remaining
                ->map(fn (array $package) => $this->directPackagePayload($package))
                ->values(),
            'next' => $remaining->isEmpty() ? 'orders' : 'direct_pay',
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

        $validated = $request->validate([
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        PaymentPinService::assertValidForAction($request->user(), $validated['payment_pin']);

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

        $amount = $this->paymentVerifier->marketplaceAmountGhs($checkout);

        if ($amount <= 0) {
            return response()->json(['message' => 'No marketplace payment required for this checkout.'], 422);
        }

        $quote = $this->paystack->rechargeQuote($amount);
        $reference = 'CSH-'.uniqid('', true);
        $amountPesewas = (int) round($quote['charge'] * 100);
        $callbackUrl = url('/api/v1/paystack/mobile-return');

        try {
            $data = $this->paystack->initializeTransaction(
                $request->user()->billingEmail(),
                $quote['charge'],
                $reference,
                [
                    'checkout_id' => $checkout->id,
                    'checkout_number' => $checkout->checkout_number,
                    'buyer_id' => $request->user()->id,
                    'source' => 'mobile_app',
                    'order_amount' => $quote['credit'],
                    'paystack_fee' => $quote['fee'],
                    'expected_amount' => $quote['charge'],
                ],
                $callbackUrl,
            );

            $paystackReference = (string) ($data['reference'] ?? $reference);

            $checkout->loadMissing('orders');
            foreach ($checkout->orders->where('payment_channel', PaymentChannel::Marketplace) as $order) {
                $order->update(['payment_reference' => $paystackReference]);
            }

            Payment::where('checkout_id', $checkout->id)
                ->where('channel', PaymentChannel::Marketplace)
                ->where('status', '!=', PaymentStatus::Paid)
                ->update(['reference' => $paystackReference]);

            $this->paymentVerifier->rememberPending($checkout, $paystackReference, $amountPesewas);

            return response()->json([
                'authorization_url' => $data['authorization_url'],
                'access_code' => $data['access_code'],
                'reference' => $paystackReference,
                'callback_url' => $callbackUrl,
                'email' => $request->user()->billingEmail(),
                'amount' => $quote['charge'],
                'order_amount' => $quote['credit'],
                'fee' => $quote['fee'],
                'charge' => $quote['charge'],
                'currency' => 'GHS',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function verifyPaystack(Request $request, Checkout $checkout): JsonResponse
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        if ($checkout->payment_status === PaymentStatus::Paid) {
            return response()->json([
                'message' => 'Already paid',
                'checkout' => $this->checkoutPayload($checkout->fresh(['orders.items'])),
            ]);
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->paymentVerifier->verifyForCheckout($checkout, $validated['reference']);
            $this->orderService->fulfillPaidCheckout($checkout, $validated['reference']);
            $this->paymentVerifier->forgetPending($checkout);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment successful.',
            'checkout' => $this->checkoutPayload($checkout->fresh(['orders.items'])),
        ]);
    }

    public function initializeDraftPaystack(Request $request): JsonResponse
    {
        $draft = PendingCheckoutDraft::getForUser($request->user());
        if (! $draft) {
            return response()->json(['message' => 'Start checkout again to pay.'], 422);
        }

        if (! $this->paystack->isConfigured()) {
            return response()->json(['message' => 'Paystack is not configured.'], 503);
        }

        try {
            $amount = $this->orderService->marketplaceAmountFromCart(
                $request->user(),
                $draft['seller_payments'] ?? [],
                $draft['seller_coupons'] ?? [],
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($amount <= 0) {
            return response()->json(['message' => 'No marketplace payment required.'], 422);
        }

        $quote = $this->paystack->rechargeQuote($amount, $draft['payment_method'] ?? 'momo');
        $reference = 'CSH-'.uniqid('', true);
        $amountPesewas = (int) round($quote['charge'] * 100);
        $callbackUrl = url('/api/v1/paystack/mobile-return');

        try {
            $data = $this->paystack->initializeTransaction(
                $request->user()->billingEmail(),
                $quote['charge'],
                $reference,
                [
                    'buyer_id' => $request->user()->id,
                    'source' => 'mobile_app_draft',
                    'draft' => true,
                    'order_amount' => $quote['credit'],
                    'paystack_fee' => $quote['fee'],
                    'expected_amount' => $quote['charge'],
                ],
                $callbackUrl,
            );

            $paystackReference = (string) ($data['reference'] ?? $reference);
            PendingCheckoutDraft::rememberPaystack($request->user(), $paystackReference, $amountPesewas);

            return response()->json([
                'authorization_url' => $data['authorization_url'],
                'access_code' => $data['access_code'],
                'reference' => $paystackReference,
                'callback_url' => $callbackUrl,
                'email' => $request->user()->billingEmail(),
                'amount' => $quote['charge'],
                'order_amount' => $quote['credit'],
                'fee' => $quote['fee'],
                'charge' => $quote['charge'],
                'currency' => 'GHS',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function verifyDraftPaystack(Request $request): JsonResponse
    {
        $draft = PendingCheckoutDraft::getForUser($request->user());
        if (! $draft) {
            return response()->json(['message' => 'Start checkout again to pay.'], 422);
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);
        $reference = trim($validated['reference']);

        $pending = PendingCheckoutDraft::pendingPaystack($request->user());
        if (is_array($pending) && ! empty($pending['reference'])) {
            if (! hash_equals((string) $pending['reference'], $reference)) {
                return response()->json(['message' => 'This payment reference does not match.'], 422);
            }
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);
            if (($data['status'] ?? '') !== 'success') {
                return response()->json(['message' => 'Payment was not successful.'], 422);
            }

            $expectedPesewas = (int) ($pending['amount_pesewas'] ?? 0);
            $paidPesewas = (int) ($data['amount'] ?? 0);
            if ($expectedPesewas > 0 && $paidPesewas + 1 < $expectedPesewas) {
                return response()->json(['message' => 'Paid amount does not match checkout total.'], 422);
            }

            $checkout = $this->orderService->createCheckoutFromCart(
                $request->user(),
                $draft['shipping'],
                $draft['payment_method'] ?? 'momo',
                $draft['seller_payments'] ?? [],
                $draft['seller_coupons'] ?? [],
            );

            $this->orderService->fulfillPaidCheckout(
                $checkout,
                $reference,
                $this->paystack->paidAmountGhs($data),
            );
            PendingCheckoutDraft::clearForUser($request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $checkout = $checkout->fresh(['orders.items']);
        $hasDirect = $checkout->orders
            ->contains(fn ($order) => $order->payment_channel === PaymentChannel::Direct);

        return response()->json([
            'message' => $hasDirect
                ? 'Payment successful. Complete direct seller payments.'
                : 'Payment successful. Your order is placed.',
            'checkout' => $this->checkoutPayload($checkout),
            'next' => $hasDirect ? 'direct_payment' : 'orders',
        ]);
    }

    /**
     * Lightweight return page for in-app WebView (no auth). App detects this URL and calls verify.
     */
    public function paystackMobileReturn(Request $request): Response
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref') ?: '');
        $safe = e($reference);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>cityshop-paystack-done</title>
  <style>
    body { font-family: system-ui, sans-serif; display:flex; align-items:center; justify-content:center;
           min-height:100vh; margin:0; background:#fff7ed; color:#1c1917; text-align:center; padding:24px; }
    p { font-size:16px; line-height:1.5; }
  </style>
</head>
<body data-reference="{$safe}">
  <div>
    <p><strong>Payment received</strong></p>
    <p>Return to CityShop to finish confirming your order.</p>
  </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
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
        $order->loadMissing(['items', 'seller.sellerProfile', 'sellerPaymentMethod']);
        $method = $order->sellerPaymentMethod;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'payment_status' => $order->payment_status?->value,
            'payment_channel' => $order->payment_channel?->value,
            'payment_method' => $order->payment_method,
            'direct_payment_reference' => $order->direct_payment_reference,
            'direct_payment_proof_path' => $order->direct_payment_proof_path,
            'direct_payment_submitted' => filled($order->direct_payment_reference)
                || filled($order->direct_payment_proof_path),
            'direct_payment_confirmed_at' => $order->direct_payment_confirmed_at?->toIso8601String(),
            'direct_payment_rejection_reason' => $order->direct_payment_rejection_reason,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'region' => $order->region,
            'city' => $order->city,
            'digital_address' => $order->digital_address,
            'delivery_notes' => $order->delivery_notes,
            'subtotal' => (float) $order->subtotal,
            'shipping_cost' => (float) $order->shipping_cost,
            'total' => (float) $order->total,
            'seller' => [
                'id' => $order->seller_id,
                'store_name' => $order->seller?->sellerProfile?->displayName() ?? $order->seller?->name,
                'store_slug' => $order->seller?->sellerProfile?->slug,
            ],
            'seller_payment_method' => $method ? [
                'id' => $method->id,
                'type' => $method->type->value,
                'label' => $method->label,
                'account_name' => $method->account_name,
                'account_number' => $method->account_number,
                'network' => $method->network,
                'bank_name' => $method->bank_name,
                'instructions' => $method->instructions,
                'display_label' => $method->displayLabel(),
            ] : null,
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

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function directPackagePayload(array $package): array
    {
        $items = collect($package['items'] ?? [])->map(function ($item) {
            $images = collect($item['product']['images'] ?? [])
                ->map(function ($image) {
                    $path = is_array($image) ? ($image['path'] ?? null) : null;
                    if (! $path) {
                        return null;
                    }

                    return [
                        'path' => $path,
                        'url' => Storage::disk('public')->url($path),
                    ];
                })
                ->filter()
                ->values();

            return [
                'id' => $item['id'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'product' => [
                    'name' => $item['product']['name'] ?? 'Product',
                    'images' => $images,
                ],
            ];
        })->values();

        return [
            'seller_id' => $package['seller_id'],
            'seller_name' => $package['seller_name'],
            'store_slug' => $package['store_slug'] ?? null,
            'subtotal' => (float) ($package['subtotal'] ?? 0),
            'shipping_cost' => (float) ($package['shipping_cost'] ?? 0),
            'shipping_label' => $package['shipping_label'] ?? 'Delivery',
            'package_total' => (float) ($package['package_total'] ?? 0),
            'items' => $items,
            'payment_method' => $package['payment_method'] ?? null,
        ];
    }
}
