<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Services\OrderService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutSessionController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaystackService $paystack,
    ) {}

    public function show(Request $request, Checkout $checkout): Response
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        $checkout->load([
            'orders.items.product.images',
            'orders.seller.sellerProfile',
            'orders.sellerPaymentMethod',
            'invoices',
            'payments',
        ]);

        return Inertia::render('shop/checkout-show', [
            'checkout' => $checkout,
        ]);
    }
}
