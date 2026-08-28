import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

import { RmbAutoRefreshChip, RmbTransferStatusBadge } from '@/components/china/rmb-transfer-status-badge';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { Paginated } from '@/types/marketplace';

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    quote: { rmb_amount: number; usd_payout: number; ghs_payout: number; payout_currency: string; payout_amount: number };
    created_at: string | null;
    user: { id: number; name: string; mobile: string | null } | null;
};

interface Props {
    transfers: Paginated<Transfer>;
    status: string;
    search: string;
    dashboard: {
        total: number;
        submitted: number;
        awaiting_verification: number;
        processing: number;
        completed: number;
        failed: number;
        rmb_received: number;
        usd_paid: number;
        ghs_paid: number;
        fees_collected: number;
        today: number;
        this_month: number;
    };
}

const filters = [
    { id: 'open', label: 'Open' },
    { id: 'payout_processing', label: 'Processing' },
    { id: 'paid', label: 'Paid' },
    { id: 'completed', label: 'Completed' },
    { id: 'all', label: 'All' },
];

function formatPayout(item: Transfer): string {
    if (item.quote.payout_currency === 'ghs') {
        return `GH₵${item.quote.ghs_payout.toFixed(2)}`;
    }

    return `$${item.quote.usd_payout.toFixed(2)}`;
}

export default function SellRmbIndex({ transfers, status, search, dashboard }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [q, setQ] = useState(search);

    useEffect(() => {
        const id = window.setInterval(() => {
            router.reload({
                only: ['transfers', 'dashboard', 'status', 'search'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 8000);
        return () => window.clearInterval(id);
    }, [status, search]);

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.sell-rmb.index'), { status, q }, { preserveState: true });
    };

    return (
        <AdminLayout title="Sell RMB" active="sell-rmb">
            <Head title="Sell RMB" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">Sell RMB</h1>
                        <p className="text-sm text-gray-500">Buyers send RMB (Alipay), then admin pays GHS.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <RmbAutoRefreshChip />
                        <Link href={route('admin.sell-rmb.settings')}>
                            <Button variant="outline">Settings</Button>
                        </Link>
                    </div>
                </div>

                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {[
                        ['Today', dashboard.today],
                        ['This month', dashboard.this_month],
                        ['Processing', dashboard.processing],
                        ['Completed', dashboard.completed],
                        ['RMB received', `¥${dashboard.rmb_received.toFixed(2)}`],
                        ['GHS paid', `GH₵${dashboard.ghs_paid.toFixed(2)}`],
                    ].map(([label, value]) => (
                        <div key={String(label)} className="rounded-2xl border border-gray-200 bg-white p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">{label}</p>
                            <p className="mt-1 text-lg font-bold text-gray-900">{value}</p>
                        </div>
                    ))}
                </div>

                <div className="flex flex-wrap gap-2">
                    {filters.map((item) => (
                        <Link
                            key={item.id}
                            href={route('admin.sell-rmb.index', { status: item.id, q })}
                            className={`rounded-full px-3 py-1.5 text-sm font-semibold ${
                                status === item.id ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </div>

                <form onSubmit={submitSearch} className="flex max-w-md gap-2">
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search ref, name, phone" />
                    <Button type="submit">Search</Button>
                </form>

                <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                    {transfers.data.length === 0 && <p className="p-6 text-sm text-gray-500">No Sell RMB requests.</p>}
                    {transfers.data.map((item) => (
                        <Link
                            key={item.id}
                            href={route('admin.sell-rmb.show', item.id)}
                            className="flex items-center justify-between gap-4 border-b border-gray-100 px-4 py-3 last:border-0 hover:bg-gray-50"
                        >
                            <div>
                                <p className="font-bold text-gray-900">{item.reference}</p>
                                <p className="text-sm text-gray-500">
                                    {item.user?.name} · {item.user?.mobile || 'no phone'}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="font-semibold">¥{item.quote.rmb_amount.toFixed(2)}</p>
                                <p className="text-xs text-gray-600">{formatPayout(item)}</p>
                                <div className="mt-1 flex justify-end">
                                    <RmbTransferStatusBadge status={item.status} label={item.status_label} />
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
