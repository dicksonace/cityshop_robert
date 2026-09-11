import { Head, Link, router, usePage } from '@inertiajs/react';

import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { formatPrice, Paginated } from '@/types/marketplace';

type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    price_ghs: number;
    created_at: string | null;
    user?: { id: number; name: string; mobile: string | null } | null;
};

interface Props {
    orders: Paginated<Order>;
    filters: { status: string };
    pendingCount: number;
}

const statuses = [
    { id: '', label: 'All' },
    { id: 'pending', label: 'Pending' },
    { id: 'processing', label: 'Processing' },
    { id: 'completed', label: 'Completed' },
    { id: 'failed', label: 'Failed' },
    { id: 'cancelled', label: 'Cancelled' },
];

export default function AdminGsmToolsIndex({ orders, filters, pendingCount }: Props) {
    const { flash } = usePage<SharedData>().props;

    return (
        <AdminLayout title="GSM Tools" active="gsm-tools">
            <Head title="GSM Tools" />
            <div className="mx-auto max-w-5xl space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">GSM Tools orders</h1>
                        <p className="text-sm text-gray-500">{pendingCount} open · wallet-paid device services</p>
                    </div>
                    <Link href={route('admin.gsm-tools.services')} className="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white">
                        Manage services
                    </Link>
                </div>

                {(flash?.success || flash?.error) && (
                    <div className={`rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    {statuses.map((s) => (
                        <button
                            key={s.id || 'all'}
                            type="button"
                            onClick={() => router.get(route('admin.gsm-tools.index'), s.id ? { status: s.id } : {}, { preserveState: true })}
                            className={`rounded-full px-3 py-1.5 text-xs font-bold ${
                                (filters.status || '') === s.id ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            {s.label}
                        </button>
                    ))}
                </div>

                <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white">
                    {orders.data.map((order) => (
                        <Link
                            key={order.id}
                            href={route('admin.gsm-tools.show', order.id)}
                            className="flex items-center justify-between gap-3 border-b border-gray-50 px-4 py-3 last:border-0 hover:bg-orange-50/40"
                        >
                            <div>
                                <p className="text-sm font-semibold text-gray-900">{order.service_name}</p>
                                <p className="text-xs text-gray-500">
                                    {order.reference} · {order.user?.name ?? 'Buyer'} {order.user?.mobile ? `· ${order.user.mobile}` : ''}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-sm font-bold text-gray-900">{formatPrice(order.price_ghs)}</p>
                                <p className="text-xs font-semibold text-orange-600">{order.status_label}</p>
                            </div>
                        </Link>
                    ))}
                    {orders.data.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-gray-500">No orders yet.</p>
                    ) : null}
                </div>
            </div>
        </AdminLayout>
    );
}
