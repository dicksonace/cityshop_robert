import { Head, Link, router, usePage } from '@inertiajs/react';
import { Camera, Search, Store } from 'lucide-react';
import { useMemo } from 'react';

import InfiniteProductGrid from '@/components/shop/infinite-product-grid';
import SearchBox from '@/components/shop/search-box';
import ShopLayout from '@/layouts/shop-layout';
import { addProductToCart } from '@/lib/shop-actions';
import { Paginated, Product, productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface SearchStore {
    id: number;
    name: string;
    slug: string;
    shop_photo?: string | null;
    products_count: number;
}

interface SearchPageProps {
    products: Paginated<Product>;
    categories: { id: number; name: string; slug: string; products_count: number }[];
    stores?: SearchStore[];
    query: string;
    sort: string;
    category?: string;
}

const sortOptions = [
    { value: 'relevance', label: 'Most relevant' },
    { value: 'newest', label: 'Newest' },
    { value: 'price_asc', label: 'Price: Low to High' },
    { value: 'price_desc', label: 'Price: High to Low' },
    { value: 'rating', label: 'Top rated' },
    { value: 'popular', label: 'Most popular' },
];

export default function SearchPage({
    products,
    categories,
    stores = [],
    query,
    sort,
    category = '',
}: SearchPageProps) {
    const { auth } = usePage<SharedData>().props;

    const resetKey = useMemo(
        () => JSON.stringify({
            query,
            sort,
            category,
            page: products.current_page,
            total: products.total,
        }),
        [query, sort, category, products.current_page, products.total],
    );

    const handleAddToCart = (productId: number) => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        addProductToCart(productId);
    };

    return (
        <ShopLayout>
            <Head title={query ? `Search: ${query}` : 'Search'} />

            <div className="border-b border-gray-100 bg-gradient-to-b from-orange-50/80 to-white">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:py-10">
                    <div className="flex items-center justify-center gap-2 text-orange-500">
                        <Search className="h-6 w-6" />
                        <h1 className="text-xl font-bold text-gray-900 sm:text-2xl">
                            {query ? 'Search results' : 'Find products & stores'}
                        </h1>
                    </div>
                    {query && (
                        <p className="mt-2 text-center text-sm text-gray-500">
                            Showing results for <span className="font-semibold text-gray-800">&ldquo;{query}&rdquo;</span>
                        </p>
                    )}
                    <div className="mt-6">
                        <SearchBox initialQuery={query} showButton showBack backHref={route('home')} />
                    </div>
                    <p className="mt-4 text-center">
                        <Link
                            href={route('search.image')}
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-orange-500 hover:text-orange-600"
                        >
                            <Camera className="h-4 w-4" />
                            Or search by uploading a photo
                        </Link>
                    </p>
                </div>
            </div>

            <div className="mx-auto max-w-7xl px-3 py-6 sm:px-4">
                {stores.length > 0 && query && (
                    <section className="mb-8">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                            <Store className="h-4 w-4 text-orange-500" />
                            Stores
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {stores.map((store) => (
                                <Link
                                    key={store.id}
                                    href={route('store.show', store.slug)}
                                    className="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-orange-200 hover:shadow-md"
                                >
                                    <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-lg font-bold text-white">
                                        {store.shop_photo ? (
                                            <img
                                                src={productImageUrl(store.shop_photo)}
                                                alt=""
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            store.name.charAt(0).toUpperCase()
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold text-gray-900">{store.name}</p>
                                        <p className="text-sm text-gray-500">
                                            {store.products_count} product{store.products_count === 1 ? '' : 's'}
                                        </p>
                                        <p className="mt-0.5 text-xs font-medium text-orange-500">Visit store →</p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {categories.length > 0 && query && (
                    <div className="mb-6 flex flex-wrap gap-2">
                        <span className="text-sm text-gray-500">Categories:</span>
                        {categories.slice(0, 6).map((cat) => (
                            <Link
                                key={cat.id}
                                href={route('search', { q: query, category: cat.id })}
                                className="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-orange-50 hover:ring-orange-200"
                            >
                                {cat.name} ({cat.products_count})
                            </Link>
                        ))}
                    </div>
                )}

                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-gray-600">
                        <span className="font-semibold text-gray-900">{products.total}</span> product{products.total !== 1 ? 's' : ''} found
                    </p>
                    <select
                        value={sort}
                        onChange={(e) => router.get(route('search'), { q: query || undefined, category: new URLSearchParams(window.location.search).get('category'), sort: e.target.value }, { preserveState: true })}
                        className="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm"
                    >
                        {sortOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>

                {products.data.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-gray-200 bg-white py-16 text-center">
                        <Search className="mx-auto h-12 w-12 text-gray-300" />
                        <p className="mt-4 text-lg font-medium text-gray-900">
                            {stores.length > 0 ? 'No matching products' : 'No products found'}
                        </p>
                        <p className="mt-1 text-sm text-gray-500">
                            {stores.length > 0
                                ? 'Try a store above, or different keywords.'
                                : 'Try a store name, product keywords, or browse the shop.'}
                        </p>
                        <Link href={route('home')} className="mt-6 inline-block text-orange-500 hover:underline">
                            Browse all products
                        </Link>
                    </div>
                ) : (
                    <InfiniteProductGrid
                        initial={products}
                        resetKey={resetKey}
                        onAddToCart={handleAddToCart}
                        gridClassName="lg:grid-cols-4"
                        skeletonCount={8}
                    />
                )}
            </div>
        </ShopLayout>
    );
}
