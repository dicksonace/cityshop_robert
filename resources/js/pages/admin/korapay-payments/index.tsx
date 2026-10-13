import { Head, Link, router } from '@inertiajs/react';
import { LoaderCircle, RefreshCw, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';

interface PaymentRow {
    id: number;
    reference: string;
    amount: number;
    status: string;
    purpose: string;
    checkout_number?: string | null;
    created_at?: string | null;
    user?: { id: number; name: string; mobile?: string | null; role?: string | null } | null;
}

interface Props {
    payments: Paginated<PaymentRow>;
    search?: string | null;
    status: string;
    korapay: { configured: boolean; available: boolean; is_test: boolean; webhook_url: string };
    totals: { success: number; count: number };
}

const statuses: Record<string, string> = {
    all: 'All',
    success: 'Paid',
    pending: 'Pending',
    processing: 'Processing',
    failed: 'Failed',
};

const statusStyle: Record<string, string> = {
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    failed: 'bg-red-50 text-red-700 ring-red-200',
    processing: 'bg-amber-50 text-amber-800 ring-amber-200',
    pending: 'bg-gray-50 text-gray-600 ring-gray-200',
};

function formatWhen(iso?: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AdminKorapayPayments({ payments, search, status, korapay, totals }: Props) {
    const [query, setQuery] = useState(search ?? '');
    const [checking, setChecking] = useState<number | null>(null);

    const load = (next: { search?: string; status?: string }) => {
        const nextStatus = next.status ?? status;
        router.get(
            route('admin.korapay-payments.index'),
            {
                search: (next.search ?? query) || undefined,
                status: nextStatus !== 'all' ? nextStatus : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        load({ search: query });
    };

    const recheck = (payment: PaymentRow) => {
        setChecking(payment.id);
        router.post(
            route('admin.korapay-payments.recheck', payment.id),
            {},
            { preserveScroll: true, onFinish: () => setChecking(null) },
        );
    };

    return (
        <AdminLayout title="Korapay Payments" active="korapay-payments">
            <Head title="Korapay Payments" />

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">Korapay payments</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Every payment started with Korapay. Use Check with Korapay if a buyer paid but was not credited.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2 text-xs font-semibold">
                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">
                        {totals.count} paid · {formatPrice(totals.success)}
                    </span>
                    <span
                        className={`rounded-full px-3 py-1 ${
                            korapay.available ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'
                        }`}
                    >
                        {korapay.available
                            ? korapay.is_test
                                ? 'Korapay on (test keys)'
                                : 'Korapay on'
                            : korapay.configured
                              ? 'Korapay disabled'
                              : 'No Korapay keys'}
                    </span>
                    <Link href={route('admin.paystack-fees.settings')} className="rounded-full bg-gray-100 px-3 py-1 text-gray-600">
                        Keys & settings
                    </Link>
                </div>
            </div>

            <form onSubmit={submitSearch} className="mb-3">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search name, phone or reference"
                        className="h-11 rounded-full border-gray-200 bg-white pl-9 shadow-sm"
                    />
                </div>
            </form>

            <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
                {Object.entries(statuses).map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => load({ status: value })}
                        className={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                            status === value
                                ? 'border-orange-500 bg-orange-500 text-white'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {payments.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center text-sm text-gray-500">
                    No Korapay payments yet.
                </div>
            ) : (
                <ul className="space-y-2.5">
                    {payments.data.map((payment) => (
                        <li key={payment.id} className="rounded-2xl border border-gray-200 bg-white px-4 py-3.5 shadow-sm">
                            <div className="flex items-start gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-[15px] font-extrabold text-gray-900">{payment.purpose}</p>
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-[11px] font-bold capitalize ring-1 ${
                                                statusStyle[payment.status] ?? statusStyle.pending
                                            }`}
                                        >
                                            {payment.status === 'success' ? 'paid' : payment.status}
                                        </span>
                                    </div>
                                    <p className="mt-1.5 text-sm text-gray-600">
                                        {[payment.user?.name, payment.checkout_number, payment.reference].filter(Boolean).join(' · ')}
                                    </p>
                                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                                        <span>{formatWhen(payment.created_at)}</span>
                                        {payment.user?.mobile && <span>{payment.user.mobile}</span>}
                                        {payment.user?.role && (
                                            <span className="rounded bg-gray-100 px-1.5 py-0.5 font-medium capitalize text-gray-500">
                                                {payment.user.role}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className="text-[15px] font-extrabold tabular-nums text-gray-900">{formatPrice(payment.amount)}</p>
                                    {payment.status !== 'success' && (
                                        <button
                                            type="button"
                                            disabled={checking === payment.id}
                                            onClick={() => recheck(payment)}
                                            className="mt-1.5 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 disabled:opacity-60"
                                        >
                                            {checking === payment.id ? (
                                                <LoaderCircle className="h-3 w-3 animate-spin" />
                                            ) : (
                                                <RefreshCw className="h-3 w-3" />
                                            )}
                                            Check with Korapay
                                        </button>
                                    )}
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {payments.last_page > 1 && (
                <div className="mt-6 flex flex-wrap justify-center gap-2">
                    {payments.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm ${
                                    link.active ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'
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
