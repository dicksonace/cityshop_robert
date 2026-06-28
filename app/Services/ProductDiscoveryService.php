<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProductDiscoveryService
{
    public function __construct(private ProductSearchService $search) {}

    /**
     * Weighted score blending rating, reviews, views, deals, recency, and local perks.
     */
    private function recommendedScoreSql(): string
    {
        $driver = Schema::getConnection()->getDriverName();

        $recency = in_array($driver, ['mysql', 'mariadb'], true)
            ? '(30.0 - LEAST(30.0, DATEDIFF(NOW(), products.created_at))) / 30.0 * 0.5'
            : "(30.0 - MIN(30.0, (julianday('now') - julianday(products.created_at)))) / 30.0 * 0.5";

        $cap = in_array($driver, ['mysql', 'mariadb'], true) ? 'LEAST' : 'MIN';

        return <<<SQL
            (
                COALESCE(products.rating, 0) * 0.35
                + ({$cap}(COALESCE(products.review_count, 0), 50) / 50.0) * 1.25
                + ({$cap}(COALESCE(products.views, 0), 1000) / 1000.0) * 0.75
                + CASE
                    WHEN products.discount_price IS NOT NULL AND products.discount_price < products.price
                    THEN (1.0 - (products.discount_price / products.price)) * 0.5
                    ELSE 0
                  END
                + {$recency}
                + CASE WHEN products.free_shipping = 1 THEN 0.3 ELSE 0 END
                + CASE WHEN products.in_ghana = 1 THEN 0.2 ELSE 0 END
            )
            SQL;
    }

    public function applySearch(Builder $query, ?string $search): Builder
    {
        if (! $search || trim($search) === '') {
            return $query;
        }

        return $this->search->apply($query, trim($search));
    }

    public function applySort(Builder $query, string $sort, ?string $seed = null): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(products.discount_price, products.price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(products.discount_price, products.price) DESC'),
            'rating' => $query->orderByDesc('products.rating')->orderByDesc('products.review_count'),
            'popular' => $query->orderByDesc('products.views')->orderByDesc('products.review_count'),
            'random' => $query->inRandomOrder($seed ?? (string) random_int(100_000, 999_999_999)),
            'newest' => $query->latest('products.created_at'),
            'relevance' => $this->search->applySortByRelevance($query)
                ->orderByDesc('products.review_count'),
            default => $query
                ->orderByRaw($this->recommendedScoreSql().' DESC')
                ->orderByDesc('products.review_count')
                ->orderByDesc('products.created_at'),
        };
    }

    public function resolveRandomSeed(Request $request): ?string
    {
        if ($request->get('sort') !== 'random') {
            return null;
        }

        return $request->get('seed', (string) random_int(100_000, 999_999_999));
    }

    public function needsRandomSeedRedirect(Request $request): ?string
    {
        if ($request->get('sort') !== 'random' || $request->has('seed')) {
            return null;
        }

        return (string) random_int(100_000, 999_999_999);
    }
}
