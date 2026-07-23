<?php

namespace App\Http\Controllers\Shop;

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
use App\Support\DirectCheckoutDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaystackService $paystack,
    ) {}

    public function index(Request $request): Response
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
                'items' => $items,
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

        $selectedId = $request->integer('address') ?: null;
        $selected = $addresses->firstWhere('id', $selectedId)
            ?? $addresses->firstWhere('is_default', true)
            ?? $addresses->first();

        return Inertia::render('shop/checkout', [
            'sellerGroups' => $sellerGroups,
            'subtotal' => $subtotal,
            'shippingTotal' => $shippingTotal,
            'grandTotal' => round($subtotal + $shippingTotal, 2),
            'user' => $request->user(),
            'wallet' => WalletService::ensure($request->user()),
            'paystackPublicKey' => config('services.paystack.public_key'),
            'addresses' => $addresses,
            'selectedAddressId' => $selected['id'] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        $shipping = $address->toShippingArray();
        $sellerPayments = $request->input('seller_payments', []);
        $sellerCoupons = $request->input('seller_coupons', []);
        $paymentMethod = $request->string('payment_method')->toString();

        // Pay-to-seller only: show bank/MoMo details without creating an order until proof is submitted.
        if ($paymentMethod !== 'cash'
            && $this->orderService->cartIsDirectOnly($request->user(), $sellerPayments)) {
            DirectCheckoutDraft::put(
                $request,
                $address->id,
                $shipping,
                $sellerPayments,
                $sellerCoupons,
            );

            return redirect()->route('checkout.direct-pay');
        }

        try {
            $checkout = $this->orderService->createCheckoutFromCart(
                $request->user(),
                $shipping,
                $paymentMethod,
                $sellerPayments,
                $sellerCoupons,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        DirectCheckoutDraft::clear($request);

        if ($paymentMethod === 'cash') {
            $this->orderService->confirmCashOnDelivery($checkout);

            return redirect()->route('checkouts.show', $checkout)
                ->with('success', 'Order placed! Pay on delivery.');
        }

        if ($paymentMethod === 'wallet') {
            try {
                $this->orderService->payCheckoutWithWallet($checkout, $request->user());
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            $hasDirect = $checkout->fresh('orders')->orders
                ->contains(fn ($order) => $order->payment_channel === PaymentChannel::Direct);

            if ($hasDirect) {
                return redirect()->route('checkout.payment', $checkout)
                    ->with('success', 'Wallet payment applied. Complete direct seller payments below.');
            }

            return redirect()->route('checkouts.show', $checkout)
                ->with('success', 'Order paid from your wallet.');
        }

        return redirect()->route('checkout.payment', $checkout);
    }

    public function directPay(Request $request): Response|RedirectResponse
    {
        $draft = DirectCheckoutDraft::get($request);
        if (! $draft) {
            return redirect()->route('checkout.index')
                ->with('error', 'Start checkout again to pay the seller.');
        }

        $packages = $this->orderService->directPayPackagesFromCart(
            $request->user(),
            $draft['seller_payments'] ?? [],
        );

        if ($packages->isEmpty()) {
            DirectCheckoutDraft::clear($request);

            return redirect()->route('checkout.index')
                ->with('error', 'Your cart changed. Choose payment again.');
        }

        return Inertia::render('shop/direct-pay', [
            'packages' => $packages,
            'shipping' => $draft['shipping'] ?? null,
        ]);
    }

    public function submitDirectPay(Request $request, int $sellerId): RedirectResponse
    {
        $draft = DirectCheckoutDraft::get($request);
        if (! $draft) {
            return redirect()->route('checkout.index')
                ->with('error', 'Start checkout again to pay the seller.');
        }

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $reference = trim((string) ($validated['reference'] ?? ''));
        $hasProof = $request->hasFile('proof');

        if ($reference === '' && ! $hasProof) {
            return back()->withErrors([
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
            return back()->withErrors($e->errors())->withInput();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $remaining = $this->orderService->directPayPackagesFromCart(
            $request->user(),
            $draft['seller_payments'] ?? [],
        );

        if ($remaining->isEmpty()) {
            DirectCheckoutDraft::clear($request);

            return redirect()->route('checkouts.show', $order->checkout_id)
                ->with('success', 'Payment submitted. The seller will confirm once received.');
        }

        return redirect()->route('checkout.direct-pay')
            ->with('success', 'Payment submitted for this seller. Pay any remaining sellers below.');
    }

    public function payment(Request $request, Checkout $checkout): Response|RedirectResponse
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        if ($checkout->payment_status === PaymentStatus::Paid) {
            return redirect()->route('checkouts.show', $checkout);
        }

        $checkout->load(['orders.items', 'orders.sellerPaymentMethod', 'orders.seller.sellerProfile']);

        if ($checkout->status === OrderStatus::Cancelled
            || $checkout->orders->every(fn ($o) => $o->status === OrderStatus::Cancelled)) {
            return redirect()->route('checkouts.show', $checkout)
                ->with('error', 'This order was cancelled. Payment is no longer required.');
        }

        $marketplaceTotal = $checkout->orders
            ->where('payment_channel', PaymentChannel::Marketplace)
            ->sum('total');

        $directOrders = $checkout->orders
            ->where('payment_channel', PaymentChannel::Direct)
            ->reject(fn ($o) => $o->status === OrderStatus::Cancelled)
            ->values();

        return Inertia::render('shop/payment', [
            'checkout' => $checkout,
            'marketplaceTotal' => $marketplaceTotal,
            'directOrders' => $directOrders,
            'paystackPublicKey' => config('services.paystack.public_key'),
            'paystackConfigured' => $this->paystack->isConfigured(),
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('home')->with('error', 'Invalid payment reference.');
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);

            if ($data['status'] !== 'success') {
                return redirect()->route('orders.index')->with('error', 'Payment was not successful.');
            }

            $checkoutId = $data['metadata']['checkout_id'] ?? null;
            $checkout = $checkoutId
                ? Checkout::findOrFail($checkoutId)
                : Checkout::whereHas('orders', fn ($q) => $q->where('payment_reference', $reference))->firstOrFail();

            $this->orderService->fulfillPaidCheckout($checkout, $reference);

            return redirect()->route('checkouts.show', $checkout)->with('success', 'Payment successful!');
        } catch (\Throwable $e) {
            Log::error('Paystack callback error', ['error' => $e->getMessage()]);

            return redirect()->route('orders.index')->with('error', 'Payment verification failed.');
        }
    }

    public function initializePayment(Request $request, Checkout $checkout): JsonResponse
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        if ($checkout->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Already paid'], 422);
        }

        if (! $this->paystack->isConfigured()) {
            return response()->json(['message' => 'Paystack is not configured. Add PAYSTACK keys to .env'], 503);
        }

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
                'amount' => $amount,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function submitDirectReference(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless($order->payment_channel === PaymentChannel::Direct, 422);

        if ($order->payment_status === PaymentStatus::Paid) {
            return back()->with('error', 'This payment is already confirmed.');
        }

        if ($order->status === OrderStatus::Cancelled
            || $order->items()->where('status', '!=', OrderStatus::Cancelled)->doesntExist()) {
            return back()->with('error', 'This order was cancelled. You cannot submit payment.');
        }

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $reference = trim((string) ($validated['reference'] ?? ''));
        $hasProof = $request->hasFile('proof') || filled($order->direct_payment_proof_path);

        if ($reference === '' && ! $hasProof) {
            return back()->withErrors([
                'proof' => 'Upload a payment screenshot, or enter a transaction ID from your MoMo SMS.',
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

        return back()->with(
            'success',
            $proofPath
                ? 'Payment screenshot submitted. The seller will confirm once received.'
                : 'Payment details submitted. The seller will confirm once received.',
        );
    }
}
