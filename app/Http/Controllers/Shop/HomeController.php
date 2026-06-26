<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductDiscoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private ProductDiscoveryService $discovery) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($seed = $this->discovery->needsRandomSeedRedirect($request)) {
            return redirect()->route('home', array_merge($request->query(), ['seed' => $seed]));
        }

        $query = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop();

        if ($search = $request->get('search')) {
            $this->discovery->applySearch($query, $search);
        }

        if ($category = $request->get('category')) {
            $query->where('category_id', $category);
        }

        if ($brand = $request->get('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->filled('price_min')) {
            $query->whereRaw('COALESCE(discount_price, price) >= ?', [(float) $request->price_min]);
        }

        if ($request->filled('price_max')) {
            $query->whereRaw('COALESCE(discount_price, price) <= ?', [(float) $request->price_max]);
        }

        if ($rating = $request->get('rating')) {
            $query->where('rating', '>=', (float) $rating);
        }

        if ($request->boolean('in_ghana')) {
            $query->where('in_ghana', true);
        }

        if ($request->boolean('free_ship')) {
            $query->where('free_shipping', true);
        }

        // Legacy quick-filter support
        match ($request->get('filter')) {
            'in_ghana' => $query->where('in_ghana', true),
            'free_ship' => $query->where('free_shipping', true),
            default => null,
        };

        $sort = $request->get('sort', 'recommended');
        $randomSeed = $this->discovery->resolveRandomSeed($request);
        $this->discovery->applySort($query, $sort, $randomSeed);

        $products = $query->paginate(12)->withQueryString();

        $priceStats = Product::visibleInShop()
            ->selectRaw('MIN(COALESCE(discount_price, price)) as min_price, MAX(COALESCE(discount_price, price)) as max_price')
            ->first();

        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->visibleInShop()])
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->products_count > 0)
            ->values();

        $brands = Product::visibleInShop()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->selectRaw('brand, COUNT(*) as count')
            ->groupBy('brand')
            ->orderBy('brand')
            ->get();

        return Inertia::render('shop/home', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'priceRange' => [
                'min' => (float) ($priceStats->min_price ?? 0),
                'max' => (float) ($priceStats->max_price ?? 10000),
            ],
            'filters' => [
                'search' => $request->get('search', ''),
                'category' => $request->get('category', ''),
                'brand' => $request->get('brand', ''),
                'price_min' => $request->get('price_min', ''),
                'price_max' => $request->get('price_max', ''),
                'rating' => $request->get('rating', ''),
                'in_ghana' => $request->boolean('in_ghana'),
                'free_ship' => $request->boolean('free_ship'),
                'sort' => $sort,
                'seed' => $randomSeed ?? $request->get('seed', ''),
            ],
            'counts' => [
                'in_ghana' => Product::visibleInShop()->where('in_ghana', true)->count(),
                'free_ship' => Product::visibleInShop()->where('free_shipping', true)->count(),
                'total' => Product::visibleInShop()->count(),
            ],
            'heroSlides' => [
                ['title' => 'Trusted Sellers, Guaranteed Quality.', 'subtitle' => 'Every item is carefully vetted so you can buy with complete confidence.', 'accent' => 'from-blue-600 to-orange-500'],
                ['title' => 'Shop Ghana\'s Best Deals', 'subtitle' => 'Electronics, fashion, and more — delivered to your doorstep.', 'accent' => 'from-emerald-600 to-teal-500'],
                ['title' => 'Buyer Protection You Can Trust', 'subtitle' => 'Secure payments, order tracking, and dispute support on every purchase.', 'accent' => 'from-violet-600 to-indigo-500'],
            ],
        ]);
    }
}
