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
            'random' => $query->inRandomOrder($seed ?? $this->rotationSeed()),
            'newest' => $query->latest('products.created_at'),
            'relevance' => $this->search->applySortByRelevance($query)
                ->orderByDesc('products.review_count'),
            // Recommended mixes quality score with a seed that rotates every 5 minutes.
            default => $query
                ->orderByRaw($this->rotatingRecommendedSql($seed ?? $this->rotationSeed()))
                ->orderByDesc('products.review_count')
                ->orderByDesc('products.created_at'),
        };
    }

    /**
     * Stable seed for a 5-minute window so listings reshuffle for everyone on the same schedule.
     */
    public function rotationSeed(?int $at = null): string
    {
        return (string) intdiv($at ?? time(), 300);
    }

    public function resolveRandomSeed(Request $request): ?string
    {
        $sort = $request->get('sort', 'recommended');

        if (! in_array($sort, ['random', 'recommended', ''], true) && $sort !== null) {
            return null;
        }

        if ($sort === 'random') {
            return $request->get('seed', $this->rotationSeed());
        }

        // Recommended always uses the current 5-minute bucket (ignore stale client seeds).
        return $this->rotationSeed();
    }

    public function needsRandomSeedRedirect(Request $request): ?string
    {
        // No redirect needed — recommended uses server-side time buckets automatically.
        if ($request->get('sort') === 'random' && ! $request->has('seed')) {
            return $this->rotationSeed();
        }

        return null;
    }

    private function rotatingRecommendedSql(string $seed): string
    {
        $score = $this->recommendedScoreSql();
        $seedInt = abs((int) $seed) ?: 1;
        $driver = Schema::getConnection()->getDriverName();

        // Quality score plus a deterministic shuffle that changes every rotation window.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return "({$score} + (CRC32(CONCAT(products.id, '-', {$seedInt})) % 1000) / 350.0) DESC";
        }

        return "({$score} + ((products.id * {$seedInt}) % 997) / 350.0) DESC";
    }
}
