import { Head, Link, router, usePage } from '@inertiajs/react';
import { Camera, Sparkles, X } from 'lucide-react';

import ImageSearchUpload from '@/components/shop/image-search-upload';
import ProductCard from '@/components/shop/product-card';
import ShopLayout from '@/layouts/shop-layout';
import { addProductToCart } from '@/lib/shop-actions';
import { Product } from '@/types/marketplace';
import { SharedData } from '@/types';

interface ImageSearchResult {
    product: Product;
    match_percent: number;
}

interface ImageSearchPageProps {
    results: ImageSearchResult[];
    preview: string | null;
    keywords: string[];
    method: 'visual' | 'ai_visual' | null;
    visionEnabled: boolean;
    seller_id?: number | null;
    store_slug?: string | null;
    store_name?: string | null;
}

export default function ImageSearchPage({
    results,
    preview,
    keywords,
    method,
    visionEnabled,
    seller_id = null,
    store_slug = null,
    store_name = null,
}: ImageSearchPageProps) {
    const { auth } = usePage<SharedData>().props;
    const hasSearched = preview !== null;
    const hasResults = results.length > 0;
    const scopedStore = Boolean(seller_id && store_slug);

    const handleAddToCart = (productId: number) => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        addProductToCart(productId);
    };

    const resetSearch = () => {
        router.visit(
            scopedStore
                ? route('search.image', { seller_id, store: store_slug })
                : route('search.image'),
        );
    };

    return (
        <ShopLayout>
            <Head title={scopedStore ? `Search ${store_name || 'store'} by image` : 'Search by image'} />

            <div className="border-b border-gray-100 bg-gradient-to-b from-orange-50/80 to-white">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:py-10">
                    <div className="flex items-center justify-center gap-2 text-orange-500">
                        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                            <Camera className="h-5 w-5" />
                        </span>
                        <h1 className="text-xl font-bold text-gray-900 sm:text-2xl">
                            {hasSearched ? 'Deep Search Results' : 'Search by photo'}
                        </h1>
                    </div>
                    <p className="mt-2 text-center text-sm text-gray-500">
                        {hasSearched
                            ? scopedStore
                                ? `Matches from ${store_name || 'this store'} using CityShop Deep Search`
                                : 'Products matched from your photo using CityShop Deep Search'
                            : scopedStore
                              ? `Upload a photo — we only search products in ${store_name || 'this store'}`
                              : 'Upload a product photo — we only show items that look like your image, not random guesses'}
                    </p>
                    {scopedStore && store_slug && (
                        <p className="mt-3 text-center">
                            <Link href={route('store.show', store_slug)} className="text-sm font-medium text-orange-600 hover:underline">
                                ← Back to {store_name || 'store'}
                            </Link>
                        </p>
                    )}
                </div>
            </div>

            <div className="mx-auto max-w-7xl px-3 py-6 sm:px-4">
                {!hasSearched ? (
                    <ImageSearchUpload
                        visionEnabled={visionEnabled}
                        sellerId={seller_id}
                        storeSlug={store_slug}
                    />
                ) : (
                    <div className="mb-8 flex flex-col gap-6 lg:flex-row lg:items-start">
                        <div className="shrink-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm lg:w-64">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold text-gray-900">Your photo</p>
                                <button
                                    type="button"
                                    onClick={resetSearch}
                                    className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                    title="Search another image"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                            {preview && (
                                <img
                                    src={preview}
                                    alt="Uploaded search"
                                    className="mt-3 w-full rounded-xl object-contain"
                                />
                            )}
                            {method === 'ai_visual' && keywords.length > 0 && (
                                <div className="mt-4">
                                    <p className="flex items-center gap-1 text-xs font-medium text-emerald-600">
                                        <Sparkles className="h-3.5 w-3.5" />
                                        AI detected
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {keywords.map((kw) => (
                                            <span
                                                key={kw}
                                                className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700"
                                            >
                                                {kw}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <Link
                                href={route('search.image')}
                                className="mt-4 block text-center text-sm font-medium text-orange-500 hover:underline"
                            >
                                Try another photo
                            </Link>
                        </div>

                        <div className="min-w-0 flex-1">
                            {hasResults ? (
                                <>
                                    <div className="mb-5 flex flex-wrap items-center gap-2 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3">
                                        <span className="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white">
                                            <Sparkles className="h-3.5 w-3.5" />
                                        </span>
                                        <div>
                                            <p className="text-sm font-semibold text-emerald-900">Deep Search complete</p>
                                            <p className="text-xs text-emerald-700/90">
                                                <span className="font-semibold">{results.length}</span> similar product{results.length !== 1 ? 's' : ''} ranked by visual match
                                                {method === 'visual' && ' · color & pattern matching'}
                                                {method === 'ai_visual' && ' · AI vision'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4">
                                        {results.map(({ product, match_percent }) => (
                                            <div key={product.id} className="relative">
                                                <span className="absolute top-2 right-2 z-10 rounded-full bg-orange-500 px-2 py-0.5 text-[10px] font-bold text-white shadow">
                                                    {match_percent}% match
                                                </span>
                                                <ProductCard product={product} onAddToCart={handleAddToCart} />
                                            </div>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <div className="rounded-2xl border border-dashed border-gray-200 bg-white py-16 text-center">
                                    <Camera className="mx-auto h-12 w-12 text-gray-300" />
                                    <p className="mt-4 text-lg font-medium text-gray-900">We don&apos;t have a matching product</p>
                                    <p className="mt-1 text-sm text-gray-500">
                                        No item in our shop looks like this photo. Try a clear product image, or browse categories instead.
                                    </p>
                                    <Link
                                        href={route('home')}
                                        className="mt-6 inline-block text-sm font-medium text-orange-500 hover:underline"
                                    >
                                        Browse all products
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
