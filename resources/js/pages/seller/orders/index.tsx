import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SellerLayout from '@/layouts/seller-layout';
import { formatPrice, formatOrderStatus, OrderItem, Paginated, productImageUrl } from '@/types/marketplace';

interface OrdersIndexProps {
    orders: Paginated<OrderItem & {
        order: {
            id: number;
            order_number: string;
            created_at: string;
            payment_status?: string;
            payment_channel?: string;
            direct_payment_reference?: string | null;
            receiver_name?: string;
            receiver_phone?: string;
            city?: string;
            region?: string;
        };
        product?: { images?: { path: string }[] };
        buyer?: { name: string };
    }>;
    counts: Record<string, number>;
    activeStatus: string;
}

const statusTabs = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'processing', label: 'Processing' },
    { key: 'packed', label: 'Packing' },
    { key: 'shipped', label: 'Shipped' },
    { key: 'delivered', label: 'Delivered' },
    { key: 'cancelled', label: 'Cancelled' },
];

export default function SellerOrdersIndex({ orders, counts, activeStatus }: OrdersIndexProps) {
    const [rejectId, setRejectId] = useState<number | null>(null);
    const rejectForm = useForm({ rejection_reason: '' });

    const submitReject = () => {
        if (!rejectId) return;
        rejectForm.post(route('seller.orders.reject', rejectId), {
            onSuccess: () => { setRejectId(null); rejectForm.reset(); },
        });
    };

    return (
        <SellerLayout title="Orders" active="orders">
            <Head title="Orders" />

            <div className="mb-6 flex gap-2 overflow-x-auto pb-2">
                {statusTabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={route('seller.orders.index', { status: tab.key === 'all' ? undefined : tab.key })}
                        className={`shrink-0 rounded-full px-4 py-2 text-sm font-medium ${activeStatus === tab.key ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'}`}
                    >
                        {tab.label}
                        {counts[tab.key] !== undefined && (
                            <span className="ml-1.5 opacity-75">({counts[tab.key]})</span>
                        )}
                    </Link>
                ))}
            </div>

            {orders.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white p-16 text-center text-gray-500">
                    No orders in this status.
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {orders.data.map((item) => {
                        const image = item.product?.images?.[0];
                        return (
                            <div key={item.id} className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                                <div className="flex gap-3 border-b border-gray-50 p-4">
                                    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gray-50 p-2">
                                        {image ? (
                                            <img src={productImageUrl(image.path)} alt="" className="max-h-full max-w-full object-contain" />
                                        ) : (
                                            <span className="text-xs text-gray-400">No img</span>
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold text-gray-900">{item.product_name}</p>
                                        <p className="text-xs text-gray-500">{item.order?.order_number}</p>
                                        <p className="mt-1 text-sm font-bold text-orange-500">{formatPrice(item.unit_price * item.quantity)}</p>
                                    </div>
                                </div>
                                <div className="space-y-2 p-4 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Buyer</span>
                                        <span className="font-medium">{item.order?.buyer?.name ?? '—'}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Status</span>
                                        <span className="capitalize font-medium">{formatOrderStatus(item.status)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Qty</span>
                                        <span>{item.quantity}</span>
                                    </div>
                                    <div className="flex gap-2 pt-2">
                                        <Link href={route('seller.orders.show', item.id)} className="flex-1">
                                            <Button variant="outline" size="sm" className="w-full">Details</Button>
                                        </Link>
                                        {item.order?.payment_channel === 'direct' && item.order?.payment_status === 'pending' && (
                                            <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700" onClick={() => router.post(route('seller.orders.confirm-direct-payment', item.order!.id))}>
                                                Confirm pay
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6">
                        <h3 className="font-semibold">Reject order</h3>
                        <Input className="mt-3" placeholder="Reason..." value={rejectForm.data.rejection_reason} onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)} />
                        <div className="mt-4 flex gap-2">
                            <Button variant="destructive" onClick={submitReject} disabled={rejectForm.processing}>Reject</Button>
                            <Button variant="outline" onClick={() => setRejectId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}
        </SellerLayout>
    );
}
