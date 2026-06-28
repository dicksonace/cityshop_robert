import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ExternalLink,
    Eye,
    Package,
    Search,
    Shield,
    ShoppingCart,
    Trash2,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated, Product, productImageUrl, SellerProfile } from '@/types/marketplace';

interface StoreShowProps {
    seller: SellerProfile & {
        user: { name: string; email: string; mobile?: string };
    };
    stats: {
        total_products: number;
        live_products: number;
        pending_products: number;
        total_orders: number;
        total_earnings: number;
        available_balance: number;
        product_views: number;
        average_rating: number;
    };
    storeHealth: { score: number; stars: number; tips: string[] };
    storeUrl: string | null;
    products: Paginated<Product & { category?: { name: string } }>;
    filters: {
        status?: string | null;
        product_search?: string | null;
        sort: string;
    };
}

const statusTabs = [
    { value: '', label: 'All' },
    { value: 'approved', label: 'Live' },
    { value: 'pending', label: 'Pending' },
    { value: 'draft', label: 'Hidden' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'sold_out', label: 'Sold out' },
];

const statusBadge: Record<string, string> = {
    approved: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
    draft: 'bg-gray-100 text-gray-600',
    rejected: 'bg-red-100 text-red-700',
};

export default function AdminStoreShow({
    seller,
    stats,
    storeHealth,
    storeUrl,
    products,
    filters,
}: StoreShowProps) {
    const [search, setSearch] = useState(filters.product_search ?? '');
    const [selected, setSelected] = useState<number[]>([]);

    const storeName = seller.business_name ?? seller.store_name;

    const applyFilters = (overrides: Record<string, string>) => {
        router.get(
            route('admin.stores.show', seller.id),
            {
                status: filters.status ?? '',
                product_search: filters.product_search ?? '',
                sort: filters.sort,
                ...overrides,
            },
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilters({ product_search: search });
    };

    const toggleSelect = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const runBulk = (action: 'hide' | 'delete' | 'approve') => {
        if (selected.length === 0) return;
        if (action === 'delete' && !confirm(`Permanently delete ${selected.length} product(s)?`)) return;

        router.post(
            route('admin.stores.products.bulk', seller.id),
            { action, product_ids: selected },
            { onSuccess: () => setSelected([]) },
        );
    };

    const hideProduct = (productId: number) => {
        if (!confirm('Disable this product and hide it from the shop?')) return;
        router.post(route('admin.stores.products.hide', [seller.id, productId]));
    };

    const deleteProduct = (productId: number) => {
        if (!confirm('Permanently delete this product? This cannot be undone.')) return;
        router.delete(route('admin.stores.products.destroy', [seller.id, productId]));
    };

    const approveProduct = (productId: number) => {
        router.post(route('admin.stores.products.approve', [seller.id, productId]));
    };

    const kpis = [
        { label: 'Live products', value: stats.live_products, icon: Package, color: 'text-blue-600', bg: 'bg-blue-50' },
        { label: 'Total products', value: stats.total_products, icon: Package, color: 'text-gray-600', bg: 'bg-gray-50' },
        { label: 'Orders', value: stats.total_orders, icon: ShoppingCart, color: 'text-orange-600', bg: 'bg-orange-50' },
        { label: 'Earnings', value: formatPrice(stats.total_earnings), icon: TrendingUp, color: 'text-emerald-600', bg: 'bg-emerald-50' },
        { label: 'Wallet', value: formatPrice(stats.available_balance), icon: Wallet, color: 'text-green-600', bg: 'bg-green-50' },
        { label: 'Views', value: stats.product_views, icon: Eye, color: 'text-purple-600', bg: 'bg-purple-50' },
    ];

    return (
        <AdminLayout title={storeName} active="stores">
            <Head title={`${storeName} — Store Oversight`} />

            <div className="mb-4">
                <Link
                    href={route('admin.stores.index')}
                    className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-orange-500"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to store search
                </Link>
            </div>

            <div className="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <Shield className="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p className="font-semibold">Admin store view</p>
                    <p className="mt-0.5 text-amber-800">
                        You are managing <strong>{storeName}</strong> ({seller.user.email}). Disable hides products from
                        the shop. Delete removes them permanently.
                    </p>
                </div>
            </div>

            <div className="mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-slate-800 to-slate-900 p-6 text-white shadow-lg">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-slate-300">Seller account</p>
                        <h2 className="text-2xl font-bold">{storeName}</h2>
                        <p className="mt-1 text-sm text-slate-300">
                            {seller.user.name} · {seller.user.email}
                        </p>
                        <p className="mt-1 text-xs capitalize text-slate-400">Status: {seller.status}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {storeUrl && (
                            <a
                                href={storeUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 rounded-lg bg-white/10 px-3 py-2 text-sm hover:bg-white/20"
                            >
                                <ExternalLink className="h-4 w-4" />
                                Public store
                            </a>
                        )}
                        <Link
                            href={route('admin.sellers.show', seller.id)}
                            className="inline-flex items-center rounded-lg bg-white/10 px-3 py-2 text-sm hover:bg-white/20"
                        >
                            Seller application
                        </Link>
                    </div>
                </div>
                <div className="mt-4 inline-flex rounded-xl bg-white/10 px-4 py-2 text-sm">
                    Store health: <strong className="ml-1">{storeHealth.score}%</strong>
                </div>
            </div>

            <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {kpis.map((kpi) => (
                    <div key={kpi.label} className="rounded-xl bg-white p-4 shadow-sm">
                        <div className={`mb-2 inline-flex rounded-lg p-2 ${kpi.bg}`}>
                            <kpi.icon className={`h-4 w-4 ${kpi.color}`} />
                        </div>
                        <p className="text-xs text-gray-500">{kpi.label}</p>
                        <p className="text-lg font-bold text-gray-900">{kpi.value}</p>
                    </div>
                ))}
            </div>

            <div className="rounded-xl bg-white p-4 shadow-sm sm:p-6">
                <h3 className="text-lg font-semibold text-gray-900">Store products</h3>
                <p className="mt-1 text-sm text-gray-500">Search, filter, disable, approve, or delete listings.</p>

                <form onSubmit={submitSearch} className="relative mt-4 max-w-md">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search products in this store..."
                        className="pl-9"
                    />
                </form>

                <div className="mt-4 flex flex-wrap gap-2">
                    {statusTabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => applyFilters({ status: tab.value })}
                            className={`rounded-full px-3 py-1.5 text-sm font-medium transition ${
                                (filters.status ?? '') === tab.value
                                    ? 'bg-orange-500 text-white'
                                    : 'bg-gray-50 text-gray-600 ring-1 ring-gray-200 hover:bg-gray-100'
                            }`}
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
                    <div className="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-orange-50 p-3">
                        <span className="text-sm font-medium text-orange-900">{selected.length} selected</span>
                        <Button size="sm" variant="outline" onClick={() => runBulk('hide')}>
                            Disable
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => runBulk('approve')}>
                            Approve
                        </Button>
                        <Button size="sm" variant="destructive" onClick={() => runBulk('delete')}>
                            Delete
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setSelected([])}>
                            Clear
                        </Button>
                    </div>
                )}

                <div className="mt-6 space-y-3">
                    {products.data.length === 0 ? (
                        <p className="py-8 text-center text-sm text-gray-500">No products found for this store.</p>
                    ) : (
                        products.data.map((product) => (
                            <div
                                key={product.id}
                                className="flex flex-col gap-4 rounded-xl border border-gray-100 p-4 sm:flex-row sm:items-center"
                            >
                                <div className="flex min-w-0 flex-1 items-start gap-3">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(product.id)}
                                        onChange={() => toggleSelect(product.id)}
                                        className="mt-1 rounded border-gray-300"
                                    />
                                    <img
                                        src={productImageUrl(product.images?.[0]?.path)}
                                        alt=""
                                        className="h-16 w-16 shrink-0 rounded-lg object-contain"
                                    />
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h4 className="font-medium text-gray-900">{product.name}</h4>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusBadge[product.status] ?? 'bg-gray-100 text-gray-600'}`}
                                            >
                                                {product.status}
                                            </span>
                                        </div>
                                        <p className="text-sm text-gray-500">
                                            {product.category?.name ?? 'Uncategorized'} · Qty {product.quantity}
                                        </p>
                                        <p className="font-semibold text-orange-500">{formatPrice(product.price)}</p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2 sm:shrink-0">
                                    {product.status !== 'approved' && (
                                        <Button size="sm" className="bg-green-600" onClick={() => approveProduct(product.id)}>
                                            Approve
                                        </Button>
                                    )}
                                    {product.status !== 'draft' && (
                                        <Button size="sm" variant="outline" onClick={() => hideProduct(product.id)}>
                                            Disable
                                        </Button>
                                    )}
                                    <Button size="sm" variant="destructive" onClick={() => deleteProduct(product.id)}>
                                        <Trash2 className="mr-1 h-3.5 w-3.5" />
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {products.last_page > 1 && (
                    <div className="mt-6 flex flex-wrap justify-center gap-2">
                        {products.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-orange-500 text-white' : 'bg-gray-50 text-gray-600 ring-1 ring-gray-200'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : null,
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
