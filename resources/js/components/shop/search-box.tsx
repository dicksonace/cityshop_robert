import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Camera, LoaderCircle, Search, Truck } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPrice, productImageUrl } from '@/types/marketplace';

interface SuggestProduct {
    id: number;
    name: string;
    slug: string;
    price: number;
    discount_price: number | null;
    image?: string | null;
    category?: string;
    free_shipping?: boolean;
}

interface SuggestCategory {
    id: number;
    name: string;
    slug: string;
    products_count: number;
}

interface SearchBoxProps {
    initialQuery?: string;
    className?: string;
    inputClassName?: string;
    showButton?: boolean;
    compact?: boolean;
    onSubmitted?: () => void;
    /** `search` = /search?q= ; `home` = shop homepage with ?search= ; `store` = store page with ?search= */
    target?: 'search' | 'home' | 'store';
    /** When set, suggestions and submit stay inside this seller's catalog. */
    sellerId?: number;
    storeSlug?: string;
    storeName?: string;
    /** Show a Back control to the left of the search field (mobile store / product flows). */
    showBack?: boolean;
    /** Optional fixed destination; otherwise uses browser history, falling back to shop home. */
    backHref?: string;
}

export default function SearchBox({
    initialQuery = '',
    className = '',
    inputClassName = '',
    showButton = true,
    compact = false,
    onSubmitted,
    target = 'search',
    sellerId,
    storeSlug,
    storeName,
    showBack = false,
    backHref,
}: SearchBoxProps) {
    const [query, setQuery] = useState(initialQuery);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [products, setProducts] = useState<SuggestProduct[]>([]);
    const [categories, setCategories] = useState<SuggestCategory[]>([]);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    const isStoreSearch = target === 'store' && Boolean(storeSlug);

    const fetchSuggestions = useCallback(
        async (q: string) => {
            if (q.length < 2) {
                setProducts([]);
                setCategories([]);
                return;
            }

            setLoading(true);
            try {
                const params = new URLSearchParams({ q });
                if (sellerId) {
                    params.set('seller_id', String(sellerId));
                }
                const res = await fetch(`${route('search.suggest')}?${params.toString()}`);
                const data = await res.json();
                setProducts(data.products ?? []);
                setCategories(data.categories ?? []);
            } catch {
                setProducts([]);
                setCategories([]);
            } finally {
                setLoading(false);
            }
        },
        [sellerId],
    );

    useEffect(() => {
        setQuery(initialQuery);
    }, [initialQuery]);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const onChange = (value: string) => {
        setQuery(value);
        setOpen(true);
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => fetchSuggestions(value), 280);
    };

    const goToSearch = (q?: string, categoryId?: number) => {
        const term = (q ?? query).trim();
        setOpen(false);
        onSubmitted?.();

        if (isStoreSearch && storeSlug) {
            const params: Record<string, string | number> = {};
            if (term) params.search = term;
            if (categoryId) params.category = categoryId;
            router.get(route('store.show', storeSlug), params);
            return;
        }

        if (target === 'home') {
            const params: Record<string, string | number> = {};
            if (term) params.search = term;
            if (categoryId) params.category = categoryId;
            router.get(route('home'), params);
            return;
        }

        const params: Record<string, string | number> = {};
        if (term) params.q = term;
        if (categoryId) params.category = categoryId;
        router.get(route('search'), params);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        goToSearch();
    };

    const imageSearchHref = isStoreSearch
        ? route('search.image', { seller_id: sellerId, store: storeSlug })
        : route('search.image');

    const placeholder = isStoreSearch
        ? compact
            ? `Search ${storeName || 'store'}...`
            : `Search products in ${storeName || 'this store'}...`
        : compact
          ? 'Search...'
          : 'Search products, brands, categories...';

    const hasResults = products.length > 0 || categories.length > 0;
    const showDropdown = open && query.length >= 2;

    const handleBack = () => {
        onSubmitted?.();
        if (backHref) {
            router.visit(backHref);
            return;
        }
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();
            return;
        }
        router.visit(route('home'));
    };

    return (
        <div ref={wrapperRef} className={`relative ${className}`}>
            <form onSubmit={handleSubmit} className="flex w-full items-center gap-2">
                {showBack && (
                    <button
                        type="button"
                        onClick={handleBack}
                        className={`inline-flex shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 shadow-sm transition-colors hover:border-orange-200 hover:bg-orange-50 hover:text-orange-600 ${compact ? 'h-10 w-10' : 'h-11 w-11'}`}
                        aria-label="Go back"
                        title="Back"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </button>
                )}
                <div
                    className={`flex min-w-0 flex-1 overflow-hidden rounded-2xl border-2 border-orange-100 bg-gray-50 transition-colors focus-within:border-orange-300 focus-within:bg-white ${compact ? 'rounded-xl' : ''}`}
                >
                    <Input
                        type="search"
                        placeholder={placeholder}
                        value={query}
                        onChange={(e) => onChange(e.target.value)}
                        onFocus={() => query.length >= 2 && setOpen(true)}
                        className={`border-0 bg-transparent focus-visible:ring-0 ${inputClassName}`}
                        autoComplete="off"
                    />
                    {showButton && (
                        <>
                            <Link
                                href={imageSearchHref}
                                className={`flex shrink-0 items-center justify-center border-l border-orange-100 bg-white px-3 text-gray-500 transition-colors hover:bg-orange-50 hover:text-orange-500 ${compact ? '' : 'px-4'}`}
                                title={isStoreSearch ? `Search ${storeName || 'store'} by photo` : 'Search by photo'}
                            >
                                <Camera className="h-4 w-4" />
                            </Link>
                            <Button
                                type="submit"
                                className={`shrink-0 rounded-none bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 ${compact ? 'px-3' : 'rounded-r-2xl px-6'}`}
                            >
                                <Search className="h-4 w-4" />
                                {!compact && <span className="ml-1 hidden sm:inline">Search</span>}
                            </Button>
                        </>
                    )}
                </div>
            </form>

            {showDropdown && (
                <div className="absolute top-full z-[60] mt-2 w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xl shadow-gray-200/50">
                    {loading && (
                        <div className="flex items-center justify-center gap-2 py-8 text-sm text-gray-500">
                            <LoaderCircle className="h-4 w-4 animate-spin" />
                            Searching...
                        </div>
                    )}

                    {!loading && !hasResults && (
                        <p className="px-4 py-6 text-center text-sm text-gray-500">
                            No products found for &ldquo;{query}&rdquo;
                            {isStoreSearch ? ' in this store' : ''}
                        </p>
                    )}

                    {!loading && categories.length > 0 && (
                        <div className="border-b border-gray-50 px-3 py-2">
                            <p className="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Categories</p>
                            {categories.map((cat) => (
                                <button
                                    key={cat.id}
                                    type="button"
                                    className="flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm hover:bg-orange-50"
                                    onClick={() => goToSearch(query, cat.id)}
                                >
                                    <span className="font-medium text-gray-800">{cat.name}</span>
                                    <span className="text-xs text-gray-400">{cat.products_count} items</span>
                                </button>
                            ))}
                        </div>
                    )}

                    {!loading && products.length > 0 && (
                        <ul className="max-h-[min(24rem,60vh)] overflow-y-auto py-2">
                            {products.map((product) => {
                                const price = product.discount_price ?? product.price;
                                return (
                                    <li key={product.id}>
                                        <Link
                                            href={route('products.show', product.slug)}
                                            className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-orange-50"
                                            onClick={() => {
                                                setOpen(false);
                                                onSubmitted?.();
                                            }}
                                        >
                                            <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-gradient-to-br from-gray-50 to-orange-50/30 p-1.5">
                                                <img
                                                    src={productImageUrl(product.image ?? undefined)}
                                                    alt=""
                                                    className="max-h-full max-w-full object-contain"
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="line-clamp-2 text-sm font-medium text-gray-900">{product.name}</p>
                                                <div className="mt-0.5 flex flex-wrap items-center gap-2">
                                                    {product.category && (
                                                        <span className="text-[10px] font-medium uppercase text-blue-500">{product.category}</span>
                                                    )}
                                                    {product.free_shipping && (
                                                        <span className="flex items-center gap-0.5 text-[10px] text-emerald-600">
                                                            <Truck className="h-3 w-3" /> Free delivery
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-1 text-sm font-bold text-orange-500">{formatPrice(price)}</p>
                                            </div>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {!loading && query.length >= 2 && (
                        <button
                            type="button"
                            onClick={() => goToSearch()}
                            className="w-full border-t border-gray-100 bg-gray-50 px-4 py-3 text-center text-sm font-medium text-orange-600 hover:bg-orange-50"
                        >
                            See all results for &ldquo;{query}&rdquo;
                            {isStoreSearch ? ' in this store' : ''}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
