import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { formatPrice, Paginated } from '@/types/marketplace';

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    quote: { total_payable_ghs: number; rmb_amount: number };
    created_at: string | null;
    user: { id: number; name: string; mobile: string | null } | null;
};

interface Props {
    transfers: Paginated<Transfer>;
    status: string;
    search: string;
    dashboard: {
        total: number;
        pending_payment: number;
        awaiting_verification: number;
        processing: number;
        completed: number;
        failed: number;
        ghs_received: number;
        rmb_sent: number;
        fees_collected: number;
        today: number;
        this_month: number;
    };
}

const filters = [
    { id: 'open', label: 'Open' },
    { id: 'payment_submitted', label: 'Awaiting verification' },
    { id: 'processing', label: 'Processing' },
    { id: 'completed', label: 'Completed' },
    { id: 'all', label: 'All' },
];

export default function ChinaTransferIndex({ transfers, status, search, dashboard }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [q, setQ] = useState(search);

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.china-transfers.index'), { status, q }, { preserveState: true });
    };

    return (
        <AdminLayout title="China Transfer" active="china-transfers">
            <Head title="China Transfer" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">China Transfer</h1>
                        <p className="text-sm text-gray-500">GHS → RMB via Alipay. WeChat Pay is off.</p>
                    </div>
                    <Link href={route('admin.china-transfer.settings')}>
                        <Button variant="outline">Settings</Button>
                    </Link>
                </div>

                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {[
                        ['Today', dashboard.today],
                        ['This month', dashboard.this_month],
                        ['Awaiting verification', dashboard.awaiting_verification],
                        ['Processing', dashboard.processing],
                        ['Completed', dashboard.completed],
                        ['GHS received', formatPrice(dashboard.ghs_received)],
                        ['RMB sent', `¥${dashboard.rmb_sent.toFixed(2)}`],
                        ['Fees', formatPrice(dashboard.fees_collected)],
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
                            href={route('admin.china-transfers.index', { status: item.id, q })}
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
                    {transfers.data.length === 0 && <p className="p-6 text-sm text-gray-500">No transfers.</p>}
                    {transfers.data.map((item) => (
                        <Link
                            key={item.id}
                            href={route('admin.china-transfers.show', item.id)}
                            className="flex items-center justify-between gap-4 border-b border-gray-100 px-4 py-3 last:border-0 hover:bg-gray-50"
                        >
                            <div>
                                <p className="font-bold text-gray-900">{item.reference}</p>
                                <p className="text-sm text-gray-500">
                                    {item.user?.name} · {item.user?.mobile || 'no phone'}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="font-semibold">{formatPrice(item.quote.total_payable_ghs)}</p>
                                <p className="text-xs text-orange-700">{item.status_label}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
