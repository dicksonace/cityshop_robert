<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'all')->toString();

        $products = Product::with(['seller:id,name', 'images', 'category:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product) => $this->serialize($product))->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
            'status' => $status,
        ]);
    }

    public function approve(Product $product): JsonResponse
    {
        $product->update(['status' => ProductStatus::Approved, 'rejection_reason' => null]);

        return response()->json(['message' => 'Product approved.', 'data' => $this->serialize($product->fresh(['seller', 'images', 'category']))]);
    }

    public function reject(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);
        $product->update([
            'status' => ProductStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json(['message' => 'Product removed from the shop.', 'data' => $this->serialize($product->fresh(['seller', 'images', 'category']))]);
    }

    public function hide(Product $product): JsonResponse
    {
        $product->update(['status' => ProductStatus::Draft]);

        return response()->json(['message' => 'Product hidden from the shop.', 'data' => $this->serialize($product->fresh(['seller', 'images', 'category']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Product $product): array
    {
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'quantity' => (int) $product->quantity,
            'status' => $product->status?->value ?? (string) $product->status,
            'rejection_reason' => $product->rejection_reason,
            'image' => $image?->path ? Storage::disk('public')->url($image->path) : null,
            'category_name' => $product->category?->name,
            'seller_name' => $product->seller?->name,
            'created_at' => $product->created_at?->toIso8601String(),
        ];
    }
}
