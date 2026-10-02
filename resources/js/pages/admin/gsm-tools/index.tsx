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
    image_url?: string | null;
    price_ghs: number;
    quantity?: number;
    eta_label?: string | null;
    created_at: string | null;
    user?: { id: number; name: string; mobile: string | null } | null;
};

function statusChip(status: string): string {
    if (status === 'completed') return 'bg-emerald-100 text-emerald-800';
    if (status === 'failed') return 'bg-red-100 text-red-700';
    if (status === 'cancelled') return 'bg-gray-100 text-gray-600';
    if (status === 'pending') return 'bg-amber-100 text-amber-800';
    return 'bg-orange-100 text-orange-800';
}

function whenLabel(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

interface Props {
    orders: Paginated<Order>;
    filters: { status: string; type?: string };
    serviceTypes: { value: string; label: string }[];
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

export default function AdminGsmToolsIndex({ orders, filters, serviceTypes = [], pendingCount }: Props) {
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
                    <Link href={route('admin.gsm-tools.services', { type: 'imei' })} className="rounded-xl bg-orange-500 px-4 py-2 text-sm font-bold text-white">
                        Manage services
                    </Link>
                </div>

                {(flash?.success || flash?.error) && (
                    <div className={`rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    {[{ value: '', label: 'All types' }, ...serviceTypes].map((type) => (
                        <button
                            key={type.value || 'all-types'}
                            type="button"
                            onClick={() =>
                                router.get(
                                    route('admin.gsm-tools.index'),
                                    { ...(filters.status ? { status: filters.status } : {}), ...(type.value ? { type: type.value } : {}) },
                                    { preserveState: true },
                                )
                            }
                            className={`rounded-full px-3 py-1.5 text-xs font-bold ${
                                (filters.type || '') === type.value ? 'bg-slate-800 text-white' : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            {type.label}
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap gap-2">
                    {statuses.map((s) => (
                        <button
                            key={s.id || 'all'}
                            type="button"
                            onClick={() =>
                                router.get(
                                    route('admin.gsm-tools.index'),
                                    { ...(s.id ? { status: s.id } : {}), ...(filters.type ? { type: filters.type } : {}) },
                                    { preserveState: true },
                                )
                            }
                            className={`rounded-full px-3 py-1.5 text-xs font-bold ${
                                (filters.status || '') === s.id ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            {s.label}
                        </button>
                    ))}
                </div>

                <div className="space-y-3">
                    {orders.data.map((order) => {
                        const qty = order.quantity ?? 1;
                        const when = whenLabel(order.created_at);
                        return (
                            <Link
                                key={order.id}
                                href={route('admin.gsm-tools.show', order.id)}
                                className="block rounded-2xl border border-gray-200 bg-white p-3.5 shadow-sm hover:border-orange-200"
                            >
                                <div className="flex items-start gap-3">
                                    {order.image_url ? (
                                        <img src={order.image_url} alt="" className="h-14 w-14 shrink-0 rounded-2xl bg-slate-950 object-contain" />
                                    ) : (
                                        <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-50 text-xs font-black text-orange-600">
                                            GSM
                                        </span>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="text-[15px] font-black leading-snug text-gray-900">{order.service_name}</p>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-extrabold text-emerald-800">
                                                {formatPrice(order.price_ghs)}
                                            </span>
                                            <span className={`rounded-full px-2 py-0.5 text-[11px] font-extrabold uppercase ${statusChip(order.status)}`}>
                                                {order.status_label}
                                            </span>
                                            {order.eta_label ? (
                                                <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-extrabold uppercase text-blue-700">
                                                    {order.eta_label}
                                                </span>
                                            ) : null}
                                            {qty > 1 ? (
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-extrabold text-gray-700">
                                                    QTY {qty}
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>
                                    <span className="text-lg font-black text-gray-300">›</span>
                                </div>
                                <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-slate-50 px-2.5 py-2 text-xs">
                                    <span className="font-extrabold text-gray-900">{order.reference}</span>
                                    <span className="font-bold text-gray-500">{order.user?.name ?? 'Buyer'}</span>
                                    {order.user?.mobile ? <span className="font-semibold text-gray-400">{order.user.mobile}</span> : null}
                                    {when ? <span className="ml-auto font-bold text-gray-400">{when}</span> : null}
                                </div>
                            </Link>
                        );
                    })}
                    {orders.data.length === 0 ? (
                        <p className="rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">No orders yet.</p>
                    ) : null}
                </div>
            </div>
        </AdminLayout>
    );
}
