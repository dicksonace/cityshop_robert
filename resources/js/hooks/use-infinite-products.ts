import { useCallback, useEffect, useRef, useState } from 'react';

import { Paginated, Product } from '@/types/marketplace';

export interface InfiniteFeedResponse {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    has_more: boolean;
    next_page: number | null;
}

interface UseInfiniteProductsOptions {
    /** Initial paginated payload from Inertia. */
    initial: Paginated<Product>;
    /**
     * Stable key that changes when filters/search/sort change
     * so the list resets from the server props.
     */
    resetKey: string;
}

function mergeProducts(existing: Product[], incoming: Product[]): Product[] {
    const seen = new Set(existing.map((p) => p.id));
    const next = [...existing];
    for (const item of incoming) {
        if (!seen.has(item.id)) {
            seen.add(item.id);
            next.push(item);
        }
    }
    return next;
}

export function useInfiniteProducts({ initial, resetKey }: UseInfiniteProductsOptions) {
    const [items, setItems] = useState<Product[]>(initial.data);
    const [page, setPage] = useState(initial.current_page);
    const [hasMore, setHasMore] = useState(initial.current_page < initial.last_page);
    const [total, setTotal] = useState(initial.total);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadingRef = useRef(false);
    const hasMoreRef = useRef(hasMore);
    const pageRef = useRef(page);
    const sentinelRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        setItems(initial.data);
        setPage(initial.current_page);
        setHasMore(initial.current_page < initial.last_page);
        setTotal(initial.total);
        setError(null);
        loadingRef.current = false;
    }, [resetKey, initial]);

    useEffect(() => {
        hasMoreRef.current = hasMore;
    }, [hasMore]);

    useEffect(() => {
        pageRef.current = page;
    }, [page]);

    const loadMore = useCallback(async () => {
        if (loadingRef.current || !hasMoreRef.current) {
            return;
        }

        loadingRef.current = true;
        setLoading(true);
        setError(null);

        const nextPage = pageRef.current + 1;
        const params = new URLSearchParams(window.location.search);
        params.set('page', String(nextPage));

        try {
            const response = await fetch(`${window.location.pathname}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Infinite-Scroll': '1',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Could not load more products.');
            }

            const payload = (await response.json()) as InfiniteFeedResponse;
            setItems((prev) => mergeProducts(prev, payload.data ?? []));
            setPage(payload.current_page);
            setHasMore(Boolean(payload.has_more));
            setTotal(payload.total);
            pageRef.current = payload.current_page;
            hasMoreRef.current = Boolean(payload.has_more);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not load more products.');
        } finally {
            loadingRef.current = false;
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        const node = sentinelRef.current;
        if (!node || !hasMore) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    void loadMore();
                }
            },
            { root: null, rootMargin: '400px 0px', threshold: 0 },
        );

        observer.observe(node);
        return () => observer.disconnect();
    }, [hasMore, loadMore, resetKey]);

    return {
        items,
        total,
        loading,
        hasMore,
        error,
        sentinelRef,
        retry: loadMore,
    };
}
