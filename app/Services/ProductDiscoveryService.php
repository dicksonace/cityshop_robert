<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace-style product ranking (Amazon / Alibaba inspired).
 *
 * Primary signals: sales, conversion, engagement, ratings, recency, deals,
 * stock, seller quality, and local perks. Exploration reorders the feed on
 * each homepage refresh while keeping pagination stable.
 */
class ProductDiscoveryService
{
    public function __construct(private ProductSearchService $search) {}

    public function applySearch(Builder $query, ?string $search): Builder
    {
        if (! $search || trim($search) === '') {
            return $query;
        }

        return $this->search->apply($query, trim($search));
    }

    public function applySort(Builder $query, string $sort, ?string $seed = null, ?User $viewer = null): Builder
    {
        $seed = $seed ?: $this->fallbackSeed();

        return match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(products.discount_price, products.price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(products.discount_price, products.price) DESC'),
            'rating' => $query->orderByDesc('products.rating')->orderByDesc('products.review_count'),
            'popular' => $query
                ->orderByDesc('products.purchase_count')
                ->orderByDesc('products.views')
                ->orderByDesc('products.review_count'),
            'random' => $query->inRandomOrder($seed),
            'newest' => $query->latest('products.created_at'),
            'relevance' => $this->search->applySortByRelevance($query)
                ->orderByRaw($this->marketplaceScoreSql($viewer, $seed).' DESC')
                ->orderByRaw($this->seedTieBreakSql($seed)),
            default => $query
                ->orderByRaw($this->marketplaceScoreSql($viewer, $seed).' DESC')
                ->orderByRaw($this->seedTieBreakSql($seed)),
        };
    }

    /**
     * Fresh ranking seed on each homepage/search page-1 load/refresh.
     * Reused for page 2+ and product pages so pagination stays consistent.
     */
    public function explorationSeed(?Request $request = null): string
    {
        $request ??= request();

        if (! $request->hasSession()) {
            return $this->fallbackSeed();
        }

        $sessionKey = 'discovery_rank_seed';
        $page = max(1, (int) $request->get('page', 1));
        $route = $request->route()?->getName();
        $isListing = in_array($route, ['home', 'search'], true);

        // Keep the same order while browsing later pages or product details.
        if ((! $isListing || $page > 1) && $request->session()->has($sessionKey)) {
            return (string) $request->session()->get($sessionKey);
        }

        if (! $isListing) {
            return $this->fallbackSeed();
        }

        // New homepage/search load: rotate order (algorithm still drives ranking).
        $seed = (string) random_int(1_000_000, 2_000_000_000);
        $request->session()->put($sessionKey, $seed);

        return $seed;
    }

    public function resolveRandomSeed(Request $request): ?string
    {
        $sort = $request->get('sort', 'recommended');

        if ($sort === 'random') {
            return $request->get('seed') ?: $this->explorationSeed($request);
        }

        if (in_array($sort, ['recommended', '', null], true)) {
            return $this->explorationSeed($request);
        }

        return null;
    }

    public function needsRandomSeedRedirect(Request $request): ?string
    {
        if ($request->get('sort') === 'random' && ! $request->has('seed')) {
            return $this->explorationSeed($request);
        }

        return null;
    }

    /**
     * Multi-factor ranking score used for "Recommended For You".
     */
    public function marketplaceScoreSql(?User $viewer = null, ?string $seed = null): string
    {
        $driver = Schema::getConnection()->getDriverName();
        $mysql = in_array($driver, ['mysql', 'mariadb'], true);
        $cap = $mysql ? 'LEAST' : 'MIN';
        $seedInt = abs((int) ($seed ?? $this->fallbackSeed())) ?: 1;

        $recency = $mysql
            ? "(45.0 - {$cap}(45.0, DATEDIFF(NOW(), products.created_at))) / 45.0"
            : "(45.0 - {$cap}(45.0, (julianday('now') - julianday(products.created_at)))) / 45.0";

        $sales = "({$cap}(COALESCE(products.purchase_count, 0), 200) / 200.0) * 3.2";

        $conversion = <<<SQL
            CASE
                WHEN COALESCE(products.views, 0) >= 8
                THEN {$cap}(COALESCE(products.purchase_count, 0) * 1.0 / products.views, 0.45) * 4.0
                ELSE 0
            END
            SQL;

        $engagement = <<<SQL
            ({$cap}(COALESCE(products.cart_adds, 0), 300) / 300.0) * 1.1
            + ({$cap}(COALESCE(products.wishlist_adds, 0), 150) / 150.0) * 0.7
            + ({$cap}(COALESCE(products.views, 0), 2000) / 2000.0) * 0.55
            SQL;

        $social = <<<SQL
            (COALESCE(products.rating, 0) / 5.0)
            * ({$cap}(COALESCE(products.review_count, 0), 80) / 80.0)
            * 2.4
            + ({$cap}(COALESCE(products.review_count, 0), 40) / 40.0) * 0.45
            SQL;

        $deal = <<<SQL
            CASE
                WHEN products.discount_price IS NOT NULL
                    AND products.price > 0
                    AND products.discount_price < products.price
                THEN {$cap}((1.0 - (products.discount_price / products.price)), 0.7) * 1.4
                ELSE 0
            END
            SQL;

        $availability = <<<SQL
            CASE
                WHEN products.quantity > 5 THEN 0.55
                WHEN products.quantity > 0 THEN 0.35
                WHEN products.is_preorder = 1 THEN 0.15
                ELSE -2.5
            END
            SQL;

        $sellerQuality = <<<SQL
            COALESCE((
                SELECT
                    (COALESCE(sp.rating, 0) / 5.0) * 0.9
                    + ({$cap}(COALESCE(sp.total_sales, 0), 200) / 200.0) * 0.7
                FROM seller_profiles sp
                WHERE sp.user_id = products.seller_id
                LIMIT 1
            ), 0)
            SQL;

        $local = <<<SQL
            CASE WHEN products.free_shipping = 1 THEN 0.35 ELSE 0 END
            + CASE WHEN products.in_ghana = 1 THEN 0.25 ELSE 0 END
            + CASE WHEN products.ships_nationwide = 1 THEN 0.15 ELSE 0 END
            SQL;

        // 0..1 noise used as a multiplier so similar items can swap positions on refresh.
        $noise = $mysql
            ? "((CRC32(CONCAT(products.id, '-', {$seedInt})) % 1000) / 1000.0)"
            : "(((products.id * {$seedInt}) % 997) / 997.0)";

        $personalization = $this->personalizationBoostSql($viewer);

        $quality = <<<SQL
            {$sales}
            + {$conversion}
            + {$engagement}
            + {$social}
            + ({$recency}) * 0.85
            + {$deal}
            + {$availability}
            + {$sellerQuality}
            + {$local}
            + {$personalization}
            SQL;

        // Quality stays primary; multiplier 0.55–1.45 lets the feed reshuffle without ignoring performance.
        return "(({$quality}) * (0.55 + ({$noise}) * 0.90))";
    }

    private function seedTieBreakSql(string $seed): string
    {
        $seedInt = abs((int) $seed) ?: 1;
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return "CRC32(CONCAT(products.id, '-', {$seedInt})) ASC";
        }

        return "((products.id * {$seedInt}) % 1000003) ASC";
    }

    private function fallbackSeed(): string
    {
        return (string) intdiv(time(), 120);
    }

    private function personalizationBoostSql(?User $viewer): string
    {
        if (! $viewer || ! $viewer->isBuyer()) {
            return '0';
        }

        $categoryIds = $this->viewerCategoryAffinity($viewer);

        if ($categoryIds === []) {
            return '0';
        }

        $list = implode(',', array_map('intval', $categoryIds));

        return "CASE WHEN products.category_id IN ({$list}) THEN 1.15 ELSE 0 END";
    }

    /**
     * @return list<int>
     */
    private function viewerCategoryAffinity(User $viewer): array
    {
        $wishlistCategories = Wishlist::query()
            ->where('wishlists.user_id', $viewer->id)
            ->join('products', 'products.id', '=', 'wishlists.product_id')
            ->whereNotNull('products.category_id')
            ->pluck('products.category_id')
            ->all();

        $orderCategories = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('buyer_id', $viewer->id))
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNotNull('products.category_id')
            ->pluck('products.category_id')
            ->all();

        $counts = array_count_values(array_map('intval', [...$wishlistCategories, ...$orderCategories]));
        arsort($counts);

        return array_slice(array_map('intval', array_keys($counts)), 0, 8);
    }
}
