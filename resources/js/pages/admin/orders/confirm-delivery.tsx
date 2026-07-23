import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { formatOrderStatus, formatPrice, orderStatusBadgeClass, Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface ConfirmItem {
    id: number;
    product_name: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    status: string;
    updated_at: string | null;
    order: {
        id: number;
        order_number: string;
        payment_channel: string;
        checkout_number?: string | null;
    };
    buyer: {
        id?: number;
        name?: string;
        email?: string;
        mobile?: string;
    };
    seller: {
        id?: number;
        name?: string;
        store?: string | null;
    };
}

interface Props {
    items: Paginated<ConfirmItem>;
    count: number;
}

function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AdminConfirmDelivery({ items, count }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [busyId, setBusyId] = useState<number | null>(null);

    const confirm = (itemId: number) => {
        setBusyId(itemId);
        router.post(route('admin.orders.confirm-delivery.store', itemId), {}, {
            preserveScroll: true,
            onFinish: () => setBusyId(null),
        });
    };

    return (
        <AdminLayout title="Confirm delivery" active="orders-confirm-delivery">
            <Head title="Confirm delivery" />

            <div className="mb-6 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <p className="font-semibold">Buyer has not confirmed yet</p>
                <p className="mt-1">
                    Seller marked these as delivered. If the buyer forgets to tap Confirm delivery, you can confirm here on their behalf.
                    Marketplace funds then move to Pending Funds for your release approval.
                </p>
                <p className="mt-2 font-medium">{count} waiting</p>
            </div>

            {(flash.success || flash.error) && (
                <div
                    className={`mb-4 rounded-xl px-4 py-3 text-sm ${
                        flash.error
                            ? 'border border-red-200 bg-red-50 text-red-800'
                            : 'border border-emerald-200 bg-emerald-50 text-emerald-800'
                    }`}
                >
                    {flash.error ?? flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap gap-2">
                <Link
                    href={route('admin.orders.index')}
                    className="rounded-full bg-white px-4 py-1.5 text-sm font-medium text-gray-600 shadow-sm"
                >
                    All orders
                </Link>
                <span className="rounded-full bg-orange-500 px-4 py-1.5 text-sm font-medium text-white">
                    Confirm delivery
                </span>
                <Link
                    href={route('admin.orders.unprocessed')}
                    className="rounded-full bg-white px-4 py-1.5 text-sm font-medium text-gray-600 shadow-sm"
                >
                    Unprocessed 24h+
                </Link>
            </div>

            <div className="space-y-4">
                {items.data.map((item) => (
                    <div key={item.id} className="rounded-xl bg-white p-5 shadow-sm">
                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-between">
                            <div className="min-w-0">
                                <p className="font-semibold text-gray-900">{item.product_name}</p>
                                <p className="mt-1 text-sm text-gray-500">
                                    Qty {item.quantity} · {formatPrice(item.line_total)}
                                </p>
                                <p className="mt-2 text-sm text-gray-600">
                                    Order{' '}
                                    <Link
                                        href={route('admin.orders.show', item.order.id)}
                                        className="font-medium text-orange-600 hover:underline"
                                    >
                                        {item.order.order_number}
                                    </Link>
                                    {item.order.checkout_number ? ` · ${item.order.checkout_number}` : ''}
                                </p>
                                <p className="mt-1 text-xs text-gray-500">
                                    Buyer: {item.buyer.name ?? '—'}
                                    {item.buyer.mobile ? ` · ${item.buyer.mobile}` : ''}
                                </p>
                                <p className="mt-0.5 text-xs text-gray-500">
                                    Seller: {item.seller.store || item.seller.name || '—'}
                                </p>
                                <p className="mt-1 text-xs text-gray-400">Marked delivered {formatDate(item.updated_at)}</p>
                            </div>
                            <div className="shrink-0 text-left sm:text-right">
                                <span
                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${orderStatusBadgeClass(item.status)}`}
                                >
                                    {formatOrderStatus(item.status)}
                                </span>
                                <Button
                                    size="sm"
                                    className="mt-3 bg-orange-500 hover:bg-orange-600"
                                    disabled={busyId === item.id}
                                    onClick={() => confirm(item.id)}
                                >
                                    {busyId === item.id ? 'Confirming…' : 'Confirm delivery'}
                                </Button>
                            </div>
                        </div>
                    </div>
                ))}

                {items.data.length === 0 && (
                    <div className="rounded-xl bg-white p-10 text-center text-gray-500 shadow-sm">
                        No orders waiting for buyer confirmation.
                    </div>
                )}
            </div>

            {items.last_page > 1 && (
                <div className="mt-4 flex justify-center gap-2">
                    {items.prev_page_url && (
                        <Button variant="outline" size="sm" onClick={() => router.get(items.prev_page_url!)}>
                            Previous
                        </Button>
                    )}
                    <span className="px-3 py-2 text-sm text-gray-500">
                        Page {items.current_page} of {items.last_page}
                    </span>
                    {items.next_page_url && (
                        <Button variant="outline" size="sm" onClick={() => router.get(items.next_page_url!)}>
                            Next
                        </Button>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
