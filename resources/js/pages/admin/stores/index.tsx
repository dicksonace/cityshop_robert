import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, Search, Store } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { Paginated, SellerProfile } from '@/types/marketplace';

interface StoresIndexProps {
    sellers: Paginated<
        SellerProfile & {
            user: { name: string; email: string; mobile?: string };
        }
    >;
    search?: string | null;
}

const statusColors: Record<string, string> = {
    approved: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
    suspended: 'bg-red-100 text-red-700',
    rejected: 'bg-gray-100 text-gray-600',
};

export default function AdminStoresIndex({ sellers, search }: StoresIndexProps) {
    const [query, setQuery] = useState(search ?? '');

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.stores.index'), { search: query || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout title="Store Oversight" active="stores">
            <Head title="Store Oversight" />

            <div className="mb-6 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                Search any seller store, open their dashboard view, and disable or delete products when needed.
            </div>

            <form onSubmit={submitSearch} className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by store name, slug, seller name, email, or phone..."
                        className="pl-9"
                    />
                </div>
                <Button type="submit" className="bg-orange-500 hover:bg-orange-600">
                    Search stores
                </Button>
            </form>

            {sellers.data.length === 0 ? (
                <div className="rounded-xl bg-white p-12 text-center shadow-sm">
                    <Store className="mx-auto h-10 w-10 text-gray-300" />
                    <p className="mt-4 text-gray-500">
                        {search ? 'No stores matched your search.' : 'Search for a store to manage its products.'}
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    {sellers.data.map((seller) => (
                        <div
                            key={seller.id}
                            className="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="font-semibold text-gray-900">
                                        {seller.business_name ?? seller.store_name}
                                    </h3>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusColors[seller.status] ?? 'bg-gray-100 text-gray-600'}`}
                                    >
                                        {seller.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-gray-500">
                                    {seller.user.name} · {seller.user.email}
                                    {seller.user.mobile ? ` · ${seller.user.mobile}` : ''}
                                </p>
                                {seller.slug && (
                                    <p className="mt-1 text-xs text-gray-400">/{seller.slug}</p>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {seller.slug && seller.status === 'approved' && (
                                    <a
                                        href={route('store.show', seller.slug)}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                                    >
                                        <ExternalLink className="h-4 w-4" />
                                        View shop
                                    </a>
                                )}
                                <Link
                                    href={route('admin.stores.show', seller.id)}
                                    className="inline-flex items-center rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600"
                                >
                                    Open store dashboard
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {sellers.last_page > 1 && (
                <div className="mt-6 flex flex-wrap justify-center gap-2">
                    {sellers.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : null,
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
