import { Head, Link, router, usePage } from '@inertiajs/react';
import { Filter, Grid3X3, LayoutList, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

import HeroBanner from '@/components/shop/hero-banner';
import ProductCard from '@/components/shop/product-card';
import ProductFilters, { ActiveFilterChips, applyFilters, ShopFilters } from '@/components/shop/product-filters';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import ShopLayout from '@/layouts/shop-layout';
import { addProductToCart } from '@/lib/shop-actions';
import { Paginated, Product } from '@/types/marketplace';
import { SharedData } from '@/types';

interface Category {
    id: number;
    name: string;
    slug: string;
    products_count: number;
}

interface HomeProps {
    products: Paginated<Product>;
    categories: Category[];
    brands: { brand: string; count: number }[];
    priceRange: { min: number; max: number };
    filters: ShopFilters;
    counts: { in_ghana: number; free_ship: number; total: number };
    heroSlides: { title: string; subtitle: string; accent: string }[];
}

const sortOptions = [
    { value: 'recommended', label: 'Recommended For You' },
    { value: 'random', label: 'Discover Randomly' },
    { value: 'newest', label: 'Newest Arrivals' },
    { value: 'price_asc', label: 'Price: Low to High' },
    { value: 'price_desc', label: 'Price: High to Low' },
    { value: 'rating', label: 'Avg. Customer Review' },
    { value: 'popular', label: 'Most Popular' },
];

const quickFilters = [
    { key: 'in_ghana', label: 'In Ghana', param: { in_ghana: true } },
    { key: 'free_ship', label: 'Free Delivery', param: { free_ship: true } },
];

export default function Home({ products, categories, brands, priceRange, filters, counts, heroSlides }: HomeProps) {
    const { auth } = usePage<SharedData>().props;
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

    const handleAddToCart = (productId: number) => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        addProductToCart(productId);
    };

    const filterProps = { filters, categories, brands, priceRange };

    return (
        <ShopLayout>
            <Head title="Shop" />
            <HeroBanner slides={heroSlides} />

            {/* Quick stats bar */}
            <div className="border-b border-gray-100 bg-white">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-6 px-4 py-3 text-center text-xs text-gray-500 md:justify-between md:text-sm">
                    <span><strong className="text-gray-900">{counts.total}</strong> products</span>
                    <span className="hidden md:inline">|</span>
                    <span><strong className="text-emerald-600">{counts.free_ship}</strong> with free delivery</span>
                    <span className="hidden md:inline">|</span>
                    <span><strong className="text-blue-500">{counts.in_ghana}</strong> in Ghana</span>
                </div>
            </div>

            <div className="mx-auto max-w-7xl px-4 py-6">
                <div className="flex gap-6">
                    {/* Desktop sidebar filters */}
                    <div className="hidden w-64 shrink-0 lg:block">
                        <div className="sticky top-24">
                            <ProductFilters {...filterProps} />
                        </div>
                    </div>

                    {/* Main content */}
                    <div className="min-w-0 flex-1">
                        {/* Toolbar */}
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                            <div className="flex items-center gap-3">
                                <Sheet>
                                    <SheetTrigger asChild>
                                        <button type="button" className="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 lg:hidden">
                                            <SlidersHorizontal className="h-4 w-4" />
                                            Filters
                                        </button>
                                    </SheetTrigger>
                                    <SheetContent side="left" className="w-80 overflow-y-auto">
                                        <SheetHeader>
                                            <SheetTitle>Filters</SheetTitle>
                                        </SheetHeader>
                                        <ProductFilters {...filterProps} className="mt-4 border-0 shadow-none" />
                                    </SheetContent>
                                </Sheet>

                                <p className="text-sm text-gray-600">
                                    <span className="font-semibold text-gray-900">{products.total}</span> results
                                    {filters.search && <span> for &ldquo;{filters.search}&rdquo;</span>}
                                </p>
                            </div>

                            <div className="flex items-center gap-2">
                                <div className="hidden items-center rounded-xl border border-gray-200 p-0.5 sm:flex">
                                    <button
                                        type="button"
                                        onClick={() => setViewMode('grid')}
                                        className={`rounded-lg p-2 ${viewMode === 'grid' ? 'bg-gray-900 text-white' : 'text-gray-500'}`}
                                    >
                                        <Grid3X3 className="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setViewMode('list')}
                                        className={`rounded-lg p-2 ${viewMode === 'list' ? 'bg-gray-900 text-white' : 'text-gray-500'}`}
                                    >
                                        <LayoutList className="h-4 w-4" />
                                    </button>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Filter className="hidden h-4 w-4 text-gray-400 sm:block" />
                                    <select
                                        value={filters.sort ?? 'recommended'}
                                        onChange={(e) => applyFilters({ sort: e.target.value }, filters)}
                                        className="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-100"
                                    >
                                        {sortOptions.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>

                        {/* Quick filter pills */}
                        <div className="mb-4 flex flex-wrap gap-2">
                            {quickFilters.map((qf) => {
                                const active = filters[qf.key as keyof ShopFilters];
                                return (
                                    <button
                                        key={qf.key}
                                        type="button"
                                        onClick={() => applyFilters({ [qf.key]: !active }, filters)}
                                        className={`rounded-full border px-4 py-1.5 text-sm font-medium transition-all ${
                                            active
                                                ? 'border-blue-500 bg-blue-500 text-white shadow-sm'
                                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:shadow-sm'
                                        }`}
                                    >
                                        {qf.label} ({counts[qf.key as keyof typeof counts]})
                                    </button>
                                );
                            })}
                        </div>

                        <ActiveFilterChips {...filterProps} />

                        {products.data.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-gray-200 bg-white py-20 text-center">
                                <p className="text-lg font-medium text-gray-700">No products match your filters</p>
                                <p className="mt-2 text-sm text-gray-500">Try adjusting or clearing your filters</p>
                                <button
                                    type="button"
                                    onClick={() => router.get(route('home'))}
                                    className="mt-4 text-sm font-medium text-orange-500 hover:underline"
                                >
                                    Clear all filters
                                </button>
                            </div>
                        ) : viewMode === 'grid' ? (
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-3">
                                {products.data.map((product) => (
                                    <ProductCard key={product.id} product={product} onAddToCart={handleAddToCart} />
                                ))}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {products.data.map((product) => (
                                    <ProductCard key={product.id} product={product} onAddToCart={handleAddToCart} variant="list" />
                                ))}
                            </div>
                        )}

                        {products.last_page > 1 && (
                            <div className="mt-8 flex justify-center gap-1">
                                {products.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            className={`min-w-[2.5rem] rounded-xl px-3 py-2 text-sm font-medium transition-colors ${
                                                link.active
                                                    ? 'bg-orange-500 text-white shadow-sm'
                                                    : 'bg-white text-gray-600 shadow-sm hover:bg-gray-50'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : null,
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
