import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import AdminOrderTabs from '@/components/admin/admin-order-tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatOrderStatus, formatPrice, orderStatusBadgeClass, Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface UnprocessedItem {
    id: number;
    product_name: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    status: string;
    paid_at: string | null;
    hours_waiting: number | null;
    order: {
        id: number;
        order_number: string;
        shipping_cost: number;
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

interface UnprocessedProps {
    items: Paginated<UnprocessedItem>;
    hours: number;
    count: number;
}

function formatWait(hours: number | null): string {
    if (hours == null) return '—';
    if (hours < 48) return `${hours}h`;
    const days = Math.floor(hours / 24);
    const rem = hours % 24;
    return rem > 0 ? `${days}d ${rem}h` : `${days}d`;
}

export default function AdminUnprocessedOrders({ items, hours, count }: UnprocessedProps) {
    const { flash } = usePage<SharedData>().props;
    const [cancellingId, setCancellingId] = useState<number | null>(null);
    const defaultReason = 'Admin cancelled: order does not look like it will go through.';
    const { data, setData, post, processing, reset } = useForm({
        reason: defaultReason,
    });

    const submitCancel = (itemId: number) => {
        post(route('admin.orders.unprocessed.cancel', itemId), {
            preserveScroll: true,
            onSuccess: () => {
                setCancellingId(null);
                reset();
                setData('reason', defaultReason);
            },
        });
    };

    return (
        <AdminLayout title="Unprocessed orders" active="orders-unprocessed">
            <Head title="Unprocessed orders (24h+)" />

            <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p className="font-semibold">Paid {hours}+ hours ago — not yet out for delivery</p>
                <p className="mt-1">
                    Each card shows the current fulfillment stage. You can cancel and refund the buyer’s CityShop wallet at any time if you
                    suspect the order will not go through (before it is out for delivery).
                </p>
                <p className="mt-2 font-medium">{count} waiting</p>
            </div>

            {(flash.success || flash.error) && (
                <div className={`mb-4 rounded-xl px-4 py-3 text-sm ${flash.error ? 'border border-red-200 bg-red-50 text-red-800' : 'border border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
                    {flash.error ?? flash.success}
                </div>
            )}

            <AdminOrderTabs active="unprocessed" />

            <div className="space-y-4">
                {items.data.map((item) => (
                    <div key={item.id} className="rounded-xl bg-white p-5 shadow-sm">
                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-between">
                            <div className="min-w-0">
                                <p className="font-semibold text-gray-900">{item.product_name}</p>
                                <p className="mt-1 text-sm text-gray-500">
                                    Qty {item.quantity} · {formatPrice(item.line_total)}
                                    {item.order.shipping_cost > 0 && (
                                        <span className="text-gray-400"> · package delivery {formatPrice(item.order.shipping_cost)}</span>
                                    )}
                                </p>
                                <p className="mt-2 text-sm text-gray-600">
                                    Order{' '}
                                    <Link href={route('admin.orders.show', item.order.id)} className="font-medium text-orange-600 hover:underline">
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
                            </div>
                            <div className="shrink-0 text-left sm:text-right">
                                <span
                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${orderStatusBadgeClass(item.status)}`}
                                >
                                    {formatOrderStatus(item.status)}
                                </span>
                                <p className="mt-2 text-sm font-bold text-red-600">Waiting {formatWait(item.hours_waiting)}</p>
                                {item.paid_at && (
                                    <p className="mt-1 text-xs text-gray-400">
                                        Paid {new Date(item.paid_at).toLocaleString()}
                                    </p>
                                )}
                                <Button
                                    size="sm"
                                    className="mt-3 bg-red-600 hover:bg-red-700"
                                    onClick={() => setCancellingId(cancellingId === item.id ? null : item.id)}
                                >
                                    Cancel & refund wallet
                                </Button>
                            </div>
                        </div>

                        {cancellingId === item.id && (
                            <div className="mt-4 space-y-2 border-t border-gray-100 pt-4">
                                <p className="text-sm text-gray-600">
                                    Confirm cancel. Buyer gets <strong>{formatPrice(item.line_total)}</strong> back in their CityShop wallet.
                                </p>
                                <Input
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    placeholder="Reason (shown to buyer)"
                                />
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        className="bg-red-600 hover:bg-red-700"
                                        disabled={processing}
                                        onClick={() => submitCancel(item.id)}
                                    >
                                        {processing ? 'Cancelling…' : 'Confirm cancel & refund'}
                                    </Button>
                                    <Button size="sm" variant="outline" onClick={() => setCancellingId(null)}>
                                        Back
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                ))}

                {items.data.length === 0 && (
                    <div className="rounded-xl bg-white p-10 text-center text-gray-500 shadow-sm">
                        No paid orders stuck {hours}+ hours before out for delivery.
                    </div>
                )}
            </div>

            {items.last_page > 1 && (
                <div className="mt-4 flex justify-center gap-2">
                    {items.prev_page_url && (
                        <Button variant="outline" size="sm" onClick={() => router.get(items.prev_page_url!)}>Previous</Button>
                    )}
                    <span className="px-3 py-2 text-sm text-gray-500">Page {items.current_page} of {items.last_page}</span>
                    {items.next_page_url && (
                        <Button variant="outline" size="sm" onClick={() => router.get(items.next_page_url!)}>Next</Button>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
