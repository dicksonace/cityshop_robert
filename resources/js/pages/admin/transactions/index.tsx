import { Head, Link, router } from '@inertiajs/react';
import { Copy, Search } from 'lucide-react';
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

function formatWhen(iso?: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('en-GH', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
}

function formatAmount(amount: number): string {
    const abs = formatPrice(Math.abs(amount));
    return amount < 0 ? `-${abs}` : abs;
}

function detailLine(tx: TxRow): string {
    const parts: string[] = [];
    if (tx.user?.name) parts.push(tx.user.name);
    if (tx.description?.trim()) parts.push(tx.description.trim());
    if (tx.reference?.trim()) parts.push(tx.reference.trim());
    return parts.join(' · ');
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

    const setType = (next: string) => {
        router.get(
            route('admin.transactions.index'),
            {
                search: query || undefined,
                type: next !== 'all' ? next : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const copyRef = (ref: string) => {
        void navigator.clipboard.writeText(ref);
    };

    return (
        <AdminLayout title="Transactions" active="transactions">
            <Head title="Transactions" />

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">All transactions</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Wallet activity across CityShop — top-ups, orders, transfers, and payouts.
                    </p>
                </div>
                {transactions.total > 0 && (
                    <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                        {transactions.total.toLocaleString()} total
                    </span>
                )}
            </div>

            <form onSubmit={submitSearch} className="mb-3">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search name or reference"
                        className="h-11 rounded-full border-gray-200 bg-white pl-9 pr-12 shadow-sm"
                    />
                    <button
                        type="submit"
                        className="absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                        aria-label="Search"
                    >
                        →
                    </button>
                </div>
            </form>

            <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
                {Object.entries(types).map(([value, label]) => {
                    const active = type === value;
                    return (
                        <button
                            key={value}
                            type="button"
                            onClick={() => setType(value)}
                            className={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                                active
                                    ? 'border-orange-500 bg-orange-500 text-white'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                            }`}
                        >
                            {label}
                        </button>
                    );
                })}
            </div>

            {transactions.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center text-sm text-gray-500">
                    No transactions found.
                </div>
            ) : (
                <ul className="space-y-2.5">
                    {transactions.data.map((tx) => {
                        const line = detailLine(tx);
                        const credit = tx.amount >= 0;
                        return (
                            <li
                                key={tx.id}
                                className="rounded-2xl border border-gray-200 bg-white px-4 py-3.5 shadow-sm transition hover:border-orange-200 hover:shadow-md"
                            >
                                <div className="flex items-start gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-[15px] font-extrabold leading-snug text-gray-900">
                                            {tx.type_label ?? tx.type ?? 'Transaction'}
                                        </p>
                                        {line && (
                                            <p className="mt-1.5 text-sm leading-relaxed text-gray-600">{line}</p>
                                        )}
                                        <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                                            <span>{formatWhen(tx.created_at)}</span>
                                            {tx.user?.mobile && <span>{tx.user.mobile}</span>}
                                            {tx.user?.role && (
                                                <span className="rounded bg-gray-100 px-1.5 py-0.5 font-medium capitalize text-gray-500">
                                                    {tx.user.role}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <p
                                            className={`text-[15px] font-extrabold tabular-nums ${
                                                credit ? 'text-gray-900' : 'text-red-600'
                                            }`}
                                        >
                                            {formatAmount(tx.amount)}
                                        </p>
                                        {tx.reference && (
                                            <button
                                                type="button"
                                                onClick={() => copyRef(tx.reference!)}
                                                className="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 hover:text-orange-600"
                                                title="Copy reference"
                                            >
                                                <Copy className="h-3 w-3" />
                                                Copy ref
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {transactions.last_page > 1 && (
                <div className="mt-6 flex flex-wrap justify-center gap-2">
                    {transactions.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-orange-500 text-white'
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
