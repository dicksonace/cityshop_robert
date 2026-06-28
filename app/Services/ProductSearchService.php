<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductSearchService
{
    /** @var list<string> */
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'in', 'is', 'it',
        'of', 'on', 'or', 'the', 'to', 'with', 'gh', 'ghana',
    ];

    private ?bool $fullTextAvailable = null;

    /**
     * @return array{raw: string, tokens: list<string>, phrase: string}
     */
    public function parseQuery(string $search): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $search) ?? '');
        $tokens = [];

        foreach (preg_split('/\s+/', mb_strtolower($raw)) ?: [] as $word) {
            $word = preg_replace('/[^a-z0-9\-+]/', '', $word) ?? '';
            if (strlen($word) < 2 || in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            $tokens[] = $word;
        }

        if ($tokens === [] && mb_strlen($raw) >= 2) {
            $tokens[] = mb_strtolower($raw);
        }

        return [
            'raw' => $raw,
            'tokens' => array_values(array_unique($tokens)),
            'phrase' => mb_strtolower($raw),
        ];
    }

    public function apply(Builder $query, string $search): Builder
    {
        $parsed = $this->parseQuery($search);

        if ($parsed['raw'] === '' || $parsed['tokens'] === []) {
            return $query;
        }

        if ($this->supportsFullText()) {
            return $this->applyFullTextSearch($query, $parsed);
        }

        return $this->applyScoredSearch($query, $parsed);
    }

    public function applySortByRelevance(Builder $query): Builder
    {
        if ($this->queryHasSearchRelevance($query)) {
            return $query->orderByDesc('search_relevance');
        }

        return $query
            ->orderByDesc('purchase_count')
            ->orderByDesc('views')
            ->orderByDesc('review_count');
    }

    public function supportsFullText(): bool
    {
        if ($this->fullTextAvailable !== null) {
            return $this->fullTextAvailable;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->fullTextAvailable = false;
        }

        try {
            $indexes = DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_search_fulltext'");

            return $this->fullTextAvailable = count($indexes) > 0;
        } catch (\Throwable) {
            return $this->fullTextAvailable = false;
        }
    }

    /**
     * @param  array{raw: string, tokens: list<string>, phrase: string}  $parsed
     */
    private function applyFullTextSearch(Builder $query, array $parsed): Builder
    {
        $booleanQuery = collect($parsed['tokens'])
            ->map(fn (string $token) => '+'.str_replace(['+', '-', '<', '>', '(', ')', '~', '*', '"', '@'], '', $token).'*')
            ->implode(' ');

        $matchColumns = 'products.name, products.brand, products.description, products.meta_keywords';

        $query->whereRaw(
            "MATCH({$matchColumns}) AGAINST(? IN BOOLEAN MODE)",
            [$booleanQuery]
        );

        $qualityBoost = $this->qualityBoostSql();

        return $query->selectRaw(
            "products.*, (
                MATCH({$matchColumns}) AGAINST(? IN NATURAL LANGUAGE MODE) * 10
                + {$qualityBoost}
            ) AS search_relevance",
            [$parsed['raw']]
        );
    }

    /**
     * @param  array{raw: string, tokens: list<string>, phrase: string}  $parsed
     */
    private function applyScoredSearch(Builder $query, array $parsed): Builder
    {
        $query->where(function (Builder $outer) use ($parsed) {
            foreach ($parsed['tokens'] as $token) {
                $like = '%'.$token.'%';
                $prefix = $token.'%';

                $outer->where(function (Builder $inner) use ($like, $prefix, $token) {
                    $inner->whereRaw('LOWER(products.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(products.brand) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(products.sku) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(products.description) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(products.meta_keywords) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(products.name) LIKE ?', [$prefix])
                        ->orWhereHas('category', fn (Builder $cq) => $cq->whereRaw('LOWER(name) LIKE ?', [$like]));

                    if (strlen($token) >= 3) {
                        $inner->orWhereRaw('LOWER(products.name) LIKE ?', ['% '.$token.'%']);
                    }
                });
            }
        });

        [$scoreSql, $bindings] = $this->buildTokenScoreSql($parsed);

        return $query->selectRaw(
            "products.*, ({$scoreSql} + {$this->qualityBoostSql()}) AS search_relevance",
            $bindings
        );
    }

    /**
     * @param  array{raw: string, tokens: list<string>, phrase: string}  $parsed
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildTokenScoreSql(array $parsed): array
    {
        $parts = [];
        $bindings = [];

        $phrase = $parsed['phrase'];
        $parts[] = 'CASE WHEN LOWER(products.name) = ? THEN 120 ELSE 0 END';
        $bindings[] = $phrase;

        $parts[] = 'CASE WHEN LOWER(products.name) LIKE ? THEN 90 ELSE 0 END';
        $bindings[] = $phrase.'%';

        $parts[] = 'CASE WHEN LOWER(products.sku) = ? THEN 85 ELSE 0 END';
        $bindings[] = $phrase;

        foreach ($parsed['tokens'] as $token) {
            $like = '%'.$token.'%';
            $prefix = $token.'%';

            $parts[] = 'CASE WHEN LOWER(products.name) LIKE ? THEN 55 ELSE 0 END';
            $bindings[] = $prefix;

            $parts[] = 'CASE WHEN LOWER(products.name) LIKE ? THEN 35 ELSE 0 END';
            $bindings[] = $like;

            $parts[] = 'CASE WHEN LOWER(products.brand) LIKE ? THEN 28 ELSE 0 END';
            $bindings[] = $like;

            $parts[] = 'CASE WHEN LOWER(products.sku) LIKE ? THEN 40 ELSE 0 END';
            $bindings[] = $like;

            $parts[] = 'CASE WHEN LOWER(products.meta_keywords) LIKE ? THEN 18 ELSE 0 END';
            $bindings[] = $like;

            $parts[] = 'CASE WHEN LOWER(products.description) LIKE ? THEN 10 ELSE 0 END';
            $bindings[] = $like;

            $parts[] = 'CASE WHEN EXISTS (
                SELECT 1 FROM categories
                WHERE categories.id = products.category_id
                AND LOWER(categories.name) LIKE ?
            ) THEN 22 ELSE 0 END';
            $bindings[] = $like;
        }

        return [implode(' + ', $parts), $bindings];
    }

    private function qualityBoostSql(): string
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return <<<'SQL'
                (COALESCE(products.rating, 0) * 0.6)
                + (LEAST(COALESCE(products.review_count, 0), 100) / 100.0) * 2.5
                + (LEAST(COALESCE(products.views, 0), 1000) / 1000.0) * 1.5
                + (LEAST(COALESCE(products.purchase_count, 0), 80) / 80.0) * 3.0
                + (LEAST(COALESCE(products.cart_adds, 0), 50) / 50.0) * 1.2
                + CASE WHEN products.discount_price IS NOT NULL AND products.discount_price < products.price THEN 0.8 ELSE 0 END
                + CASE WHEN products.free_shipping = 1 THEN 0.4 ELSE 0 END
                + CASE WHEN products.in_ghana = 1 THEN 0.3 ELSE 0 END
                SQL;
        }

        return <<<'SQL'
            (COALESCE(products.rating, 0) * 0.6)
            + (MIN(COALESCE(products.review_count, 0), 100) / 100.0) * 2.5
            + (MIN(COALESCE(products.views, 0), 1000) / 1000.0) * 1.5
            + (MIN(COALESCE(products.purchase_count, 0), 80) / 80.0) * 3.0
            + (MIN(COALESCE(products.cart_adds, 0), 50) / 50.0) * 1.2
            + CASE WHEN products.discount_price IS NOT NULL AND products.discount_price < products.price THEN 0.8 ELSE 0 END
            + CASE WHEN products.free_shipping = 1 THEN 0.4 ELSE 0 END
            + CASE WHEN products.in_ghana = 1 THEN 0.3 ELSE 0 END
            SQL;
    }

    private function queryHasSearchRelevance(Builder $query): bool
    {
        $columns = $query->getQuery()->columns ?? [];

        if ($columns === null || $columns === ['*']) {
            return false;
        }

        foreach ($columns as $column) {
            if (is_string($column) && str_contains($column, 'search_relevance')) {
                return true;
            }
        }

        return false;
    }
}
