import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PanelLayout from '@/layouts/panel-layout';
import { formatPrice, formatOrderStatus, OrderItem, Paginated } from '@/types/marketplace';

interface OrdersIndexProps {
    orders: Paginated<OrderItem & {
        order: { order_number: string; created_at: string; payment_status?: string };
        buyer?: { name: string };
    }>;
}

const nav = [
    { label: 'Dashboard', href: route('seller.dashboard') },
    { label: 'Products', href: route('seller.products.index') },
    { label: 'Orders', href: route('seller.orders.index'), active: true },
    { label: 'Messages', href: route('chat.index') },
    { label: 'Wallet', href: route('seller.wallet') },
];

const updatableStatuses = ['processing', 'packed', 'shipped', 'delivered'];
const rejectableStatuses = ['pending', 'processing', 'packed'];

export default function SellerOrdersIndex({ orders }: OrdersIndexProps) {
    const [rejectId, setRejectId] = useState<number | null>(null);
    const rejectForm = useForm({ rejection_reason: '' });

    const submitReject = () => {
        if (!rejectId) return;
        rejectForm.post(route('seller.orders.reject', rejectId), {
            onSuccess: () => {
                setRejectId(null);
                rejectForm.reset();
            },
        });
    };

    const updateStatus = (itemId: number, status: string) => {
        router.patch(route('seller.orders.update', itemId), { status });
    };

    return (
        <PanelLayout title="Orders" nav={nav}>
            <Head title="Orders" />
            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="border-b bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Order</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Buyer</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Product</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Amount</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {orders.data.map((item) => {
                            const canReject = rejectableStatuses.includes(item.status);
                            const canUpdate = updatableStatuses.includes(item.status) || item.status === 'pending';

                            return (
                                <tr key={item.id}>
                                    <td className="px-4 py-3">{item.order?.order_number}</td>
                                    <td className="px-4 py-3">{item.buyer?.name ?? '—'}</td>
                                    <td className="px-4 py-3">{item.product_name} x{item.quantity}</td>
                                    <td className="px-4 py-3">{formatPrice(item.unit_price * item.quantity)}</td>
                                    <td className="px-4 py-3">
                                        <span className="capitalize">{formatOrderStatus(item.status)}</span>
                                        {item.rejection_reason && (
                                            <p className="mt-1 text-xs text-red-500">{item.rejection_reason}</p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            {canUpdate && item.status !== 'delivered' && item.status !== 'cancelled' && (
                                                <select
                                                    defaultValue={item.status === 'pending' ? 'processing' : item.status}
                                                    onChange={(e) => updateStatus(item.id, e.target.value)}
                                                    className="rounded-md border px-2 py-1 text-xs"
                                                >
                                                    {item.status === 'pending' && <option value="processing">Mark Processing</option>}
                                                    {updatableStatuses.map((s) => (
                                                        <option key={s} value={s}>{formatOrderStatus(s)}</option>
                                                    ))}
                                                </select>
                                            )}
                                            {canReject && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => setRejectId(item.id)}
                                                >
                                                    Reject
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                {orders.data.length === 0 && <p className="p-8 text-center text-gray-500">No orders yet.</p>}
            </div>

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6">
                        <h3 className="font-semibold text-gray-900">Reject Order</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            The buyer will be refunded to their CityShop wallet if payment was already made.
                        </p>
                        <Input
                            className="mt-3"
                            placeholder="Reason for rejection..."
                            value={rejectForm.data.rejection_reason}
                            onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                        />
                        <div className="mt-4 flex gap-2">
                            <Button variant="destructive" onClick={submitReject} disabled={rejectForm.processing}>
                                Confirm Reject
                            </Button>
                            <Button variant="outline" onClick={() => setRejectId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}
