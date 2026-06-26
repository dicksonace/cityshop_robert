<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private ProductDiscoveryService $discovery) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('q', $request->get('search', '')));

        $query = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop();

        $this->discovery->applySearch($query, $search);

        if ($category = $request->get('category')) {
            $query->where('category_id', $category);
        }

        $sort = $search ? ($request->get('sort', 'relevance')) : $request->get('sort', 'recommended');
        $this->discovery->applySort($query, $sort);

        $products = $query->paginate(24)->withQueryString();

        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->visibleInShop()])
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->products_count > 0)
            ->values();

        return Inertia::render('shop/search', [
            'products' => $products,
            'categories' => $categories,
            'query' => $search,
            'sort' => $sort,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['products' => [], 'categories' => []]);
        }

        $products = Product::with(['images', 'category'])
            ->visibleInShop()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            })
            ->orderByDesc('views')
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => (float) $p->price,
                'discount_price' => $p->discount_price ? (float) $p->discount_price : null,
                'image' => $p->images->first()?->path,
                'category' => $p->category?->name,
                'free_shipping' => $p->free_shipping,
            ]);

        $categories = Category::where('is_active', true)
            ->where('name', 'like', "%{$q}%")
            ->withCount(['products' => fn ($cq) => $cq->visibleInShop()])
            ->limit(6)
            ->get()
            ->filter(fn ($c) => $c->products_count > 0)
            ->take(4)
            ->values()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'products_count' => $c->products_count,
            ]);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }
}
