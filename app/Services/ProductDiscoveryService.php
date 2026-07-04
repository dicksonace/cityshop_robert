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
 * stock, seller quality, and local perks. A small exploration term keeps the
 * feed feeling fresh without pure randomness.
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
        return match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(products.discount_price, products.price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(products.discount_price, products.price) DESC'),
            'rating' => $query->orderByDesc('products.rating')->orderByDesc('products.review_count'),
            'popular' => $query
                ->orderByDesc('products.purchase_count')
                ->orderByDesc('products.views')
                ->orderByDesc('products.review_count'),
            'random' => $query->inRandomOrder($seed ?? $this->explorationSeed()),
            'newest' => $query->latest('products.created_at'),
            'relevance' => $this->search->applySortByRelevance($query)
                ->orderByRaw($this->marketplaceScoreSql($viewer, $seed).' DESC')
                ->orderByDesc('products.purchase_count')
                ->orderByDesc('products.review_count'),
            default => $query
                ->orderByRaw($this->marketplaceScoreSql($viewer, $seed).' DESC')
                ->orderByDesc('products.purchase_count')
                ->orderByDesc('products.review_count')
                ->orderByDesc('products.created_at'),
        };
    }

    /**
     * Exploration seed changes every hour so ranking stays mostly stable,
     * with gentle reordering for discovery (not a full random reshuffle).
     */
    public function explorationSeed(?int $at = null): string
    {
        return (string) intdiv($at ?? time(), 3600);
    }

    public function resolveRandomSeed(Request $request): ?string
    {
        $sort = $request->get('sort', 'recommended');

        if ($sort === 'random') {
            return $request->get('seed', $this->explorationSeed());
        }

        if (in_array($sort, ['recommended', '', null], true)) {
            return $this->explorationSeed();
        }

        return null;
    }

    public function needsRandomSeedRedirect(Request $request): ?string
    {
        if ($request->get('sort') === 'random' && ! $request->has('seed')) {
            return $this->explorationSeed();
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
        $seedInt = abs((int) ($seed ?? $this->explorationSeed())) ?: 1;

        // Recency: newer listings get a controlled boost (first ~45 days).
        $recency = $mysql
            ? "(45.0 - {$cap}(45.0, DATEDIFF(NOW(), products.created_at))) / 45.0"
            : "(45.0 - {$cap}(45.0, (julianday('now') - julianday(products.created_at)))) / 45.0";

        // Sales velocity — strongest commercial signal.
        $sales = "({$cap}(COALESCE(products.purchase_count, 0), 200) / 200.0) * 3.2";

        // Conversion quality (purchases / views) once there is enough traffic.
        $conversion = <<<SQL
            CASE
                WHEN COALESCE(products.views, 0) >= 8
                THEN {$cap}(COALESCE(products.purchase_count, 0) * 1.0 / products.views, 0.45) * 4.0
                ELSE 0
            END
            SQL;

        // Engagement funnel: cart + wishlist intent.
        $engagement = <<<SQL
            ({$cap}(COALESCE(products.cart_adds, 0), 300) / 300.0) * 1.1
            + ({$cap}(COALESCE(products.wishlist_adds, 0), 150) / 150.0) * 0.7
            + ({$cap}(COALESCE(products.views, 0), 2000) / 2000.0) * 0.55
            SQL;

        // Social proof: rating weighted by review volume (Bayesian-style dampening).
        $social = <<<SQL
            (COALESCE(products.rating, 0) / 5.0)
            * ({$cap}(COALESCE(products.review_count, 0), 80) / 80.0)
            * 2.4
            + ({$cap}(COALESCE(products.review_count, 0), 40) / 40.0) * 0.45
            SQL;

        // Deal strength.
        $deal = <<<SQL
            CASE
                WHEN products.discount_price IS NOT NULL
                    AND products.price > 0
                    AND products.discount_price < products.price
                THEN {$cap}((1.0 - (products.discount_price / products.price)), 0.7) * 1.4
                ELSE 0
            END
            SQL;

        // Stock / availability — demote empty listings.
        $availability = <<<SQL
            CASE
                WHEN products.quantity > 5 THEN 0.55
                WHEN products.quantity > 0 THEN 0.35
                WHEN products.is_preorder = 1 THEN 0.15
                ELSE -2.5
            END
            SQL;

        // Seller quality from store profile.
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

        // Local marketplace perks.
        $local = <<<SQL
            CASE WHEN products.free_shipping = 1 THEN 0.35 ELSE 0 END
            + CASE WHEN products.in_ghana = 1 THEN 0.25 ELSE 0 END
            + CASE WHEN products.ships_nationwide = 1 THEN 0.15 ELSE 0 END
            SQL;

        // Light exploration so the feed is not static, without dominating quality signals.
        $exploration = $mysql
            ? "((CRC32(CONCAT(products.id, '-', {$seedInt})) % 100) / 100.0) * 0.45"
            : "(((products.id * {$seedInt}) % 97) / 97.0) * 0.45";

        $personalization = $this->personalizationBoostSql($viewer);

        return <<<SQL
            (
                {$sales}
                + {$conversion}
                + {$engagement}
                + {$social}
                + ({$recency}) * 0.85
                + {$deal}
                + {$availability}
                + {$sellerQuality}
                + {$local}
                + {$exploration}
                + {$personalization}
            )
            SQL;
    }

    /**
     * Boost categories the shopper has shown interest in (wishlist / past orders).
     */
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
