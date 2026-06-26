<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductDiscoveryService
{
    /**
     * Weighted score blending rating, reviews, views, deals, recency, and local perks.
     */
    private const RECOMMENDED_SCORE = <<<'SQL'
        (
            COALESCE(rating, 0) * 0.35
            + (MIN(COALESCE(review_count, 0), 50) / 50.0) * 1.25
            + (MIN(COALESCE(views, 0), 1000) / 1000.0) * 0.75
            + CASE
                WHEN discount_price IS NOT NULL AND discount_price < price
                THEN (1.0 - (discount_price / price)) * 0.5
                ELSE 0
              END
            + (30.0 - MIN(30.0, (julianday('now') - julianday(created_at)))) / 30.0 * 0.5
            + CASE WHEN free_shipping = 1 THEN 0.3 ELSE 0 END
            + CASE WHEN in_ghana = 1 THEN 0.2 ELSE 0 END
        )
        SQL;

    public function applySearch(Builder $query, ?string $search): Builder
    {
        if (! $search || trim($search) === '') {
            return $query;
        }

        $term = trim($search);

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('meta_keywords', 'like', "%{$term}%")
                ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$term}%"));
        });
    }

    public function applySort(Builder $query, string $sort, ?string $seed = null): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(discount_price, price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(discount_price, price) DESC'),
            'rating' => $query->orderByDesc('rating')->orderByDesc('review_count'),
            'popular' => $query->orderByDesc('views')->orderByDesc('review_count'),
            'random' => $query->inRandomOrder($seed ?? (string) random_int(100_000, 999_999_999)),
            'newest' => $query->latest(),
            'relevance' => $query->orderByDesc('views')->orderByDesc('review_count'),
            default => $query
                ->orderByRaw(self::RECOMMENDED_SCORE.' DESC')
                ->orderByDesc('review_count')
                ->orderByDesc('created_at'),
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
