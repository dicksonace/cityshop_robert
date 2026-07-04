import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';

import SellerProductCard from '@/components/seller/seller-product-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SellerLayout from '@/layouts/seller-layout';
import { Paginated, Product } from '@/types/marketplace';
import { SharedData } from '@/types';

interface ProductsIndexProps {
    products: Paginated<Product & { reviews_count?: number; category?: { name: string } }>;
    filters: { status?: string | null; search?: string | null; sort: string };
    categories?: { id: number; name: string }[];
}

const statusTabs = [
    { value: '', label: 'All' },
    { value: 'approved', label: 'Live' },
    { value: 'draft', label: 'Hidden' },
    { value: 'sold_out', label: 'Sold out' },
    { value: 'deleted', label: 'Deleted' },
];

export default function ProductsIndex({ products, filters, categories = [] }: ProductsIndexProps) {
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkCategory, setBulkCategory] = useState('');

    const toggleSelect = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const runBulk = (action: string) => {
        if (selected.length === 0) return;
        if (action === 'delete' && !confirm(`Move ${selected.length} product(s) to trash? You can restore them from Deleted.`)) return;
        router.post(route('seller.products.bulk'), {
            action,
            product_ids: selected,
            category_id: action === 'category' ? bulkCategory : undefined,
        }, { onSuccess: () => setSelected([]) });
    };

    const applyFilters = (overrides: Record<string, string>) => {
        router.get(route('seller.products.index'), {
            status: filters.status ?? '',
            search: filters.search ?? '',
            sort: filters.sort,
            ...overrides,
        }, { preserveState: true, replace: true });
    };

    return (
        <SellerLayout title="Products" active="products" showFab>
            <Head title="Products" />

            {flash.success && (
                <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                    {flash.error}
                </div>
            )}

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <form
                    className="relative max-w-md flex-1"
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters({ search });
                    }}
                >
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search products..."
                        className="pl-9"
                    />
                </form>
                <Link href={route('seller.products.create')} className="hidden lg:inline-flex">
                    <Button className="bg-orange-500 hover:bg-orange-600">
                        <Plus className="mr-2 h-4 w-4" />
                        Add product
                    </Button>
                </Link>
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {statusTabs.map((tab) => (
                    <button
                        key={tab.value}
                        type="button"
                        onClick={() => applyFilters({ status: tab.value })}
                        className={`rounded-full px-3 py-1.5 text-sm font-medium transition ${(filters.status ?? '') === tab.value ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'}`}
                    >
                        {tab.label}
                    </button>
                ))}
                <select
                    value={filters.sort}
                    onChange={(e) => applyFilters({ sort: e.target.value })}
                    className="ml-auto rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm"
                >
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="name">Name</option>
                    <option value="price_asc">Price ↑</option>
                    <option value="price_desc">Price ↓</option>
                </select>
            </div>

            {selected.length > 0 && (
                <div className="mb-4 flex flex-wrap items-center gap-2 rounded-xl bg-orange-50 p-3">
                    <span className="text-sm font-medium text-orange-900">{selected.length} selected</span>
                    <Button size="sm" variant="outline" onClick={() => runBulk('hide')}>Hide</Button>
                    <Button size="sm" variant="outline" onClick={() => runBulk('delete')}>Delete</Button>
                    {categories.length > 0 && (
                        <>
                            <select value={bulkCategory} onChange={(e) => setBulkCategory(e.target.value)} className="rounded-md border px-2 py-1 text-sm">
                                <option value="">Change category…</option>
                                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                            <Button size="sm" variant="outline" disabled={!bulkCategory} onClick={() => runBulk('category')}>Apply</Button>
                        </>
                    )}
                    <Button size="sm" variant="ghost" onClick={() => setSelected([])}>Clear</Button>
                </div>
            )}

            {products.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white p-16 text-center">
                    <p className="text-gray-500">No products found.</p>
                    <Link href={route('seller.products.create')} className="mt-4 inline-block">
                        <Button className="bg-orange-500 hover:bg-orange-600">Add your first product</Button>
                    </Link>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {products.data.map((product) => (
                        <div key={product.id} className="relative">
                            <label className="absolute top-3 left-3 z-10 flex h-6 w-6 cursor-pointer items-center justify-center rounded-md bg-white/90 shadow">
                                <input
                                    type="checkbox"
                                    checked={selected.includes(product.id)}
                                    onChange={() => toggleSelect(product.id)}
                                    className="rounded"
                                />
                            </label>
                            <SellerProductCard
                            product={product}
                            onDuplicate={(id) => router.post(route('seller.products.duplicate', id))}
                            onToggleVisibility={(id) => router.patch(route('seller.products.visibility', id))}
                            onDelete={(id) => {
                                if (confirm('Move this product to trash? You can restore it from the Deleted tab.')) {
                                    router.delete(route('seller.products.destroy', id));
                                }
                            }}
                            />
                            {filters.status === 'deleted' && (
                                <Button
                                    size="sm"
                                    className="absolute right-3 bottom-3 z-10 bg-green-600 hover:bg-green-700"
                                    onClick={() => router.post(route('seller.products.restore', product.id))}
                                >
                                    Restore
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {products.last_page > 1 && (
                <div className="mt-8 flex flex-wrap justify-center gap-2">
                    {products.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            preserveScroll
                            className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </SellerLayout>
    );
}
