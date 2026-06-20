<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::with('items')
            ->where('buyer_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('shop/orders', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        $order->load(['items.product.images', 'items.dispute']);

        $reviews = Review::where('order_id', $order->id)
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('product_id');

        return Inertia::render('shop/order-show', [
            'order' => $order,
            'reviews' => $reviews,
        ]);
    }
}
