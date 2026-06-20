<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        $orders = Order::with(['buyer', 'items'])->latest()->paginate(15);

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['buyer', 'items.product', 'items.seller']);

        return Inertia::render('admin/orders/show', [
            'order' => $order,
        ]);
    }
}
