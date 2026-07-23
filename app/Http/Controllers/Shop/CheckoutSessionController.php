<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Review;
use App\Support\BuyerOrderPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutSessionController extends Controller
{
    public function show(Request $request, Checkout $checkout): Response
    {
        abort_unless($checkout->buyer_id === $request->user()->id, 403);

        $checkout->load([
            'orders.items.product.images',
            'orders.items.dispute',
            'orders.seller.sellerProfile',
            'orders.sellerPaymentMethod',
            'payments',
            'invoices' => fn ($q) => $q
                ->where('user_id', $request->user()->id)
                ->whereIn('type', ['customer', 'customer_master'])
                ->orderByDesc('issued_at'),
        ]);

        $orderIds = $checkout->orders->pluck('id');
        $reviews = Review::whereIn('order_id', $orderIds)
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy(fn ($review) => $review->order_id.'-'.$review->product_id);

        $checkout->orders->each(function ($order) {
            $order->setAttribute('can_request_refund', BuyerOrderPolicy::canRequestRefund($order));
        });

        return Inertia::render('shop/checkout-show', [
            'checkout' => $checkout,
            'reviews' => $reviews,
        ]);
    }
}
