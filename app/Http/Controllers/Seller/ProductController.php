<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Services\CategorySpecService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::with('images')
            ->withCount('reviews')
            ->where('seller_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('seller/products/index', [
            'products' => $products,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('seller/products/create', [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'quantity' => ['required', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'free_shipping' => ['boolean'],
            'in_ghana' => ['boolean'],
            'specifications' => ['nullable', 'array'],
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['image', 'max:5120'],
        ]);

        $specifications = $this->resolveSpecifications($validated['category_id'] ?? null, $request->input('specifications', []));

        $product = Product::create([
            ...collect($validated)->except('images')->toArray(),
            'specifications' => $specifications,
            'seller_id' => $request->user()->id,
            'status' => ProductStatus::Pending,
            'is_preorder' => false,
        ]);

        foreach ($request->file('images') as $index => $image) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $image->store('products', 'public'),
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }

        return redirect()->route('seller.products.index')
            ->with('success', 'Product submitted for review.');
    }

    public function edit(Request $request, Product $product): Response
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        return Inertia::render('seller/products/edit', [
            'product' => $product->load('images'),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'quantity' => ['required', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'free_shipping' => ['boolean'],
            'in_ghana' => ['boolean'],
            'specifications' => ['nullable', 'array'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', 'exists:product_images,id'],
        ]);

        if ($request->filled('remove_images')) {
            ProductImage::where('product_id', $product->id)
                ->whereIn('id', $request->input('remove_images'))
                ->delete();
        }

        $product->refresh();
        $currentCount = $product->images()->count();
        $newCount = $request->file('images') ? count($request->file('images')) : 0;

        if ($currentCount + $newCount > 5) {
            return back()->withErrors(['images' => 'Maximum 5 images allowed per product.']);
        }

        if ($request->file('images')) {
            $startOrder = $product->images()->max('sort_order') ?? -1;
            foreach ($request->file('images') as $index => $image) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $image->store('products', 'public'),
                    'is_primary' => $product->images()->count() === 0 && $index === 0,
                    'sort_order' => $startOrder + $index + 1,
                ]);
            }
        }

        // Ensure first image by sort_order is primary
        $first = $product->images()->orderBy('sort_order')->first();
        if ($first) {
            $product->images()->update(['is_primary' => false]);
            $first->update(['is_primary' => true]);
        }

        $specifications = $this->resolveSpecifications($validated['category_id'] ?? null, $request->input('specifications', []));

        $product->update([
            ...collect($validated)->except(['images', 'remove_images'])->toArray(),
            'specifications' => $specifications,
            'status' => ProductStatus::Pending,
            'is_preorder' => false,
        ]);

        return redirect()->route('seller.products.index')
            ->with('success', 'Product updated and resubmitted for review.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    public function reviews(Request $request, Product $product): Response
    {
        abort_unless($product->seller_id === $request->user()->id, 403);

        $reviews = Review::with(['user', 'order'])
            ->where('product_id', $product->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('seller/products/reviews', [
            'product' => $product->only(['id', 'name', 'slug', 'rating', 'review_count']),
            'reviews' => $reviews,
        ]);
    }

    private function resolveSpecifications(?int $categoryId, array $specs): ?array
    {
        if (! $categoryId) {
            return null;
        }

        $category = Category::find($categoryId);
        if (! $category) {
            return null;
        }

        $validated = CategorySpecService::validateSpecs($category->slug, $specs);

        return $validated ?: null;
    }
}
