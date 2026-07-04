import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';

interface BuyerRow {
    id: number;
    name: string;
    email: string;
    mobile?: string;
    created_at: string;
    orders_count: number;
    wallet?: { available_balance: number } | null;
}

interface BuyersIndexProps {
    buyers: Paginated<BuyerRow>;
    search?: string | null;
}

export default function AdminBuyersIndex({ buyers, search }: BuyersIndexProps) {
    const [query, setQuery] = useState(search ?? '');

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.buyers.index'), { search: query || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout title="Buyers" active="buyers">
            <Head title="Buyers" />

            <p className="mb-4 text-sm text-gray-500">
                View shopper accounts, wallet balances, and order history.
            </p>

            <form onSubmit={submitSearch} className="mb-6">
                <div className="relative max-w-md">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by name, email, or phone..."
                        className="pl-9"
                    />
                </div>
            </form>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-[640px] w-full text-sm">
                    <thead className="border-b bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Buyer</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Contact</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Orders</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Wallet</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Joined</th>
                            <th className="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {buyers.data.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-4 py-10 text-center text-gray-500">
                                    No buyers found.
                                </td>
                            </tr>
                        ) : (
                            buyers.data.map((buyer) => (
                                <tr key={buyer.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium text-gray-900">{buyer.name}</td>
                                    <td className="px-4 py-3">
                                        <p>{buyer.email}</p>
                                        <p className="text-gray-500">{buyer.mobile ?? '—'}</p>
                                    </td>
                                    <td className="px-4 py-3">{buyer.orders_count}</td>
                                    <td className="px-4 py-3 font-medium text-green-600">
                                        {formatPrice(buyer.wallet?.available_balance ?? 0)}
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {new Date(buyer.created_at).toLocaleDateString('en-GH', {
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route('admin.buyers.show', buyer.id)} className="text-blue-500 hover:underline">
                                            View profile
                                        </Link>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {buyers.last_page > 1 && (
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {buyers.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : null,
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
