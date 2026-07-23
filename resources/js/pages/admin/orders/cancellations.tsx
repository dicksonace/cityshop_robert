import { Head, Link, router } from '@inertiajs/react';

import AdminOrderTabs from '@/components/admin/admin-order-tabs';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';

interface CancelledItem {
    id: number;
    product_name: string;
    quantity: number;
    line_total: number;
    rejection_reason?: string | null;
    cancellation_code?: string | null;
    cancellation_label?: string;
    cancelled_at?: string | null;
    refund_status?: string | null;
    order: {
        id: number;
        order_number: string;
        payment_channel?: string | null;
    };
    buyer: { name?: string };
    seller: { id?: number; name?: string; store?: string | null };
}

interface HighCancelSeller {
    seller_id: number;
    name?: string;
    store?: string | null;
    total_items: number;
    seller_cancels: number;
    rate: number;
}

interface Props {
    items: Paginated<CancelledItem>;
    highCancelSellers: HighCancelSeller[];
}

function refundLabel(status?: string | null): string {
    return matchRefund(status);
}

function matchRefund(status?: string | null): string {
    switch (status) {
        case 'completed':
            return 'Refunded to wallet';
        case 'not_applicable':
            return 'No wallet refund';
        case 'failed':
            return 'Refund failed';
        default:
            return status ?? '—';
    }
}

export default function AdminSellerCancellations({ items, highCancelSellers }: Props) {
    return (
        <AdminLayout title="Seller cancellations" active="orders-cancellations">
            <Head title="Seller cancellations" />

            <AdminOrderTabs active="cancellations" />

            {highCancelSellers.length > 0 && (
                <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p className="font-semibold text-amber-900">Sellers with cancellations</p>
                    <p className="mt-1 text-xs text-amber-800">Monitor high rates for possible abuse.</p>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {highCancelSellers.map((s) => (
                            <div key={s.seller_id} className="rounded-lg bg-white/80 px-3 py-2 text-sm">
                                <p className="font-medium text-gray-900">{s.store || s.name || `Seller #${s.seller_id}`}</p>
                                <p className="text-xs text-gray-600">
                                    {s.seller_cancels} cancelled / {s.total_items} items · <strong>{s.rate}%</strong>
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="space-y-4">
                {items.data.map((item) => (
                    <div key={item.id} className="rounded-xl bg-white p-5 shadow-sm">
                        <div className="flex flex-col gap-2 sm:flex-row sm:justify-between">
                            <div>
                                <p className="font-semibold text-gray-900">{item.product_name}</p>
                                <p className="mt-1 text-sm text-gray-500">
                                    Qty {item.quantity} · {formatPrice(item.line_total)}
                                </p>
                                <p className="mt-2 text-sm text-gray-600">
                                    Order{' '}
                                    <Link href={route('admin.orders.show', item.order.id)} className="font-medium text-orange-600 hover:underline">
                                        {item.order.order_number}
                                    </Link>
                                </p>
                                <p className="mt-1 text-xs text-gray-500">Buyer: {item.buyer.name ?? '—'}</p>
                                <p className="text-xs text-gray-500">Seller: {item.seller.store || item.seller.name || '—'}</p>
                                <p className="mt-2 text-sm text-red-700">
                                    <span className="font-medium">{item.cancellation_label ?? 'Cancelled'}</span>
                                    {item.rejection_reason ? ` — ${item.rejection_reason}` : ''}
                                </p>
                            </div>
                            <div className="text-sm sm:text-right">
                                <p className="font-medium text-gray-900">{refundLabel(item.refund_status)}</p>
                                {item.cancelled_at && (
                                    <p className="mt-1 text-xs text-gray-400">
                                        {new Date(item.cancelled_at).toLocaleString()}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                ))}

                {items.data.length === 0 && (
                    <div className="rounded-xl bg-white p-10 text-center text-gray-500 shadow-sm">
                        No seller cancellations yet.
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
