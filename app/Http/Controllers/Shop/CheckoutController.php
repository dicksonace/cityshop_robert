<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $items = $request->user()->cartItems()->with(['product.images'])->get();
        $subtotal = $items->sum(fn ($item) => $item->subtotal());

        return Inertia::render('shop/checkout', [
            'items' => $items,
            'subtotal' => $subtotal,
            'user' => $request->user(),
            'paystackPublicKey' => config('services.paystack.public_key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['required', 'string', 'max:20'],
            'region' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'digital_address' => ['nullable', 'string', 'max:100'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:momo,card,cash'],
        ]);

        try {
            $order = $this->orderService->createPendingOrderFromCart(
                $request->user(),
                $validated,
                $validated['payment_method']
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($validated['payment_method'] === 'cash') {
            $this->orderService->confirmCashOnDelivery($order);

            return redirect()->route('orders.show', $order)
                ->with('success', 'Order placed! Pay on delivery.');
        }

        return redirect()->route('checkout.payment', $order);
    }

    public function payment(Request $request, Order $order): Response|RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->payment_status === PaymentStatus::Paid) {
            return redirect()->route('orders.show', $order);
        }

        return Inertia::render('shop/payment', [
            'order' => $order->load('items'),
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

            $orderId = $data['metadata']['order_id'] ?? null;
            $order = $orderId
                ? Order::findOrFail($orderId)
                : Order::where('payment_reference', $reference)->firstOrFail();

            $this->orderService->fulfillPaidOrder($order, $reference);

            return redirect()->route('orders.show', $order)->with('success', 'Payment successful!');
        } catch (\Throwable $e) {
            Log::error('Paystack callback error', ['error' => $e->getMessage()]);

            return redirect()->route('orders.index')->with('error', 'Payment verification failed.');
        }
    }

    public function initializePayment(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Already paid'], 422);
        }

        if (! $this->paystack->isConfigured()) {
            return response()->json(['message' => 'Paystack is not configured. Add PAYSTACK keys to .env'], 503);
        }

        try {
            $data = $this->paystack->initializeTransaction(
                $request->user()->email,
                (float) $order->total,
                $order->payment_reference,
                ['order_id' => $order->id, 'order_number' => $order->order_number]
            );

            $order->update(['payment_reference' => $data['reference']]);

            return response()->json([
                'authorization_url' => $data['authorization_url'],
                'access_code' => $data['access_code'],
                'reference' => $data['reference'],
                'email' => $request->user()->email,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
