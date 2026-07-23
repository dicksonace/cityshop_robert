<?php

namespace App\Http\Controllers\Shop;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\ProductDiscoveryService;
use App\Services\ProductSearchService;
use App\Support\InfiniteScroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        private ProductDiscoveryService $discovery,
        private ProductSearchService $search,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $search = trim((string) $request->get('q', $request->get('search', '')));

        $query = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop();

        $this->discovery->applySearch($query, $search);

        if ($category = $request->get('category')) {
            $query->where('category_id', $category);
        }

        $sort = $search ? ($request->get('sort', 'relevance')) : $request->get('sort', 'recommended');
        $rankingSeed = $this->discovery->resolveRandomSeed($request);
        $this->discovery->applySort($query, $sort, $rankingSeed, $request->user());

        $products = $query->paginate(20)->withQueryString();

        if (InfiniteScroll::wants($request)) {
            return InfiniteScroll::json($products);
        }

        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->visibleInShop()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->products_count > 0)
            ->values();

        $stores = $search !== ''
            ? $this->matchingStores($search, 8)
            : collect();

        return Inertia::render('shop/search', [
            'products' => $products,
            'categories' => $categories,
            'stores' => $stores,
            'query' => $search,
            'sort' => $sort,
            'category' => $request->get('category') ? (string) $request->get('category') : '',
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['products' => [], 'categories' => [], 'stores' => []]);
        }

        $sellerId = $request->integer('seller_id') ?: null;

        $products = Product::with(['images', 'category'])
            ->visibleInShop();

        if ($sellerId) {
            $products->where('seller_id', $sellerId);
        }

        $this->search->apply($products, $q);

        $products = $this->search->applySortByRelevance($products)
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
            ->where(function ($query) use ($q) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($q).'%']);
            })
            ->withCount(['products' => function ($cq) use ($sellerId) {
                $cq->visibleInShop();
                if ($sellerId) {
                    $cq->where('seller_id', $sellerId);
                }
            }])
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

        $stores = $sellerId
            ? collect()
            : $this->matchingStores($q, 5);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'stores' => $stores,
        ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, slug: string, shop_photo: string|null, products_count: int}>
     */
    private function matchingStores(string $q, int $limit): Collection
    {
        $parsed = $this->search->parseQuery($q);

        if ($parsed['raw'] === '' || $parsed['tokens'] === []) {
            return collect();
        }

        return SellerProfile::query()
            ->where('status', SellerStatus::Approved)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->tap(fn ($query) => $this->search->constrainStoreName($query, $parsed))
            ->limit($limit * 3)
            ->get()
            ->map(function (SellerProfile $profile) {
                $productsCount = Product::query()
                    ->visibleInShop()
                    ->where('seller_id', $profile->user_id)
                    ->count();

                return [
                    'id' => $profile->id,
                    'name' => $profile->displayName(),
                    'slug' => $profile->slug,
                    'shop_photo' => $profile->shop_photo,
                    'products_count' => $productsCount,
                ];
            })
            ->sortByDesc('products_count')
            ->take($limit)
            ->values();
    }
}
