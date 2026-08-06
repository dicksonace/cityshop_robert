import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';

interface TxRow {
    id: number;
    type?: string;
    type_label?: string;
    amount: number;
    description?: string;
    reference?: string | null;
    created_at?: string | null;
    user?: {
        id: number;
        name: string;
        email: string;
        mobile?: string | null;
        role?: string | null;
    } | null;
}

interface Props {
    transactions: Paginated<TxRow>;
    search?: string | null;
    type: string;
    types: Record<string, string>;
}

export default function AdminTransactionsIndex({ transactions, search, type, types }: Props) {
    const [query, setQuery] = useState(search ?? '');

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('admin.transactions.index'),
            {
                search: query || undefined,
                type: type !== 'all' ? type : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout title="Transactions" active="transactions">
            <Head title="Transactions" />

            <p className="mb-4 text-sm text-gray-500">
                All CityShop wallet activity — top-ups, withdrawals, order payments, and peer transfers (GHS).
                Search by phone number, name, email, or reference.
            </p>

            <form onSubmit={submitSearch} className="mb-4 flex flex-wrap items-center gap-3">
                <div className="relative min-w-[240px] flex-1 max-w-md">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search mobile number, name, email, reference…"
                        className="pl-9"
                    />
                </div>
                <select
                    value={type}
                    onChange={(e) =>
                        router.get(
                            route('admin.transactions.index'),
                            {
                                search: query || undefined,
                                type: e.target.value !== 'all' ? e.target.value : undefined,
                            },
                            { preserveState: true, replace: true },
                        )
                    }
                    className="h-10 rounded-md border border-gray-200 bg-white px-3 text-sm"
                >
                    {Object.entries(types).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
                <button
                    type="submit"
                    className="h-10 rounded-md bg-orange-500 px-4 text-sm font-semibold text-white hover:bg-orange-600"
                >
                    Search
                </button>
            </form>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th className="px-4 py-3">When</th>
                            <th className="px-4 py-3">User / phone</th>
                            <th className="px-4 py-3">Type</th>
                            <th className="px-4 py-3">Description</th>
                            <th className="px-4 py-3 text-right">Amount (GHS)</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {transactions.data.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-10 text-center text-gray-500">
                                    No transactions found.
                                </td>
                            </tr>
                        ) : (
                            transactions.data.map((tx) => (
                                <tr key={tx.id} className="hover:bg-orange-50/40">
                                    <td className="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                        {tx.created_at
                                            ? new Date(tx.created_at).toLocaleString('en-GH')
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{tx.user?.name ?? '—'}</p>
                                        <p className="text-xs text-gray-500">
                                            {tx.user?.mobile || tx.user?.email || '—'}
                                            {tx.user?.role ? ` · ${tx.user.role}` : ''}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-xs font-medium text-gray-700">
                                        {tx.type_label ?? tx.type ?? '—'}
                                    </td>
                                    <td className="max-w-xs px-4 py-3">
                                        <p className="line-clamp-2 text-gray-700">{tx.description}</p>
                                        {tx.reference && (
                                            <p className="mt-0.5 text-xs text-gray-400">Ref {tx.reference}</p>
                                        )}
                                    </td>
                                    <td
                                        className={`whitespace-nowrap px-4 py-3 text-right font-semibold ${
                                            tx.amount < 0 ? 'text-red-600' : 'text-emerald-600'
                                        }`}
                                    >
                                        {formatPrice(tx.amount)}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {transactions.last_page > 1 && (
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {transactions.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-white text-gray-600 ring-1 ring-gray-200'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : null,
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
