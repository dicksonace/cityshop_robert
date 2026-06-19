<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');

        $products = Product::with(['seller', 'images', 'category'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'status' => $status,
        ]);
    }

    public function approve(Product $product): RedirectResponse
    {
        $product->update([
            'status' => ProductStatus::Approved,
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Product approved.');
    }

    public function reject(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $product->update([
            'status' => ProductStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('success', 'Product rejected.');
    }
}
