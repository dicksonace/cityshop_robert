import { Head } from '@inertiajs/react';

import PanelLayout from '@/layouts/panel-layout';
import { formatPrice, Order, Paginated } from '@/types/marketplace';

interface OrdersIndexProps {
    orders: Paginated<Order & { buyer: { name: string } }>;
}

const nav = [
    { label: 'Dashboard', href: route('admin.dashboard') },
    { label: 'Sellers', href: route('admin.sellers.index') },
    { label: 'Products', href: route('admin.products.index') },
    { label: 'Orders', href: route('admin.orders.index'), active: true },
    { label: 'Withdrawals', href: route('admin.withdrawals.index') },
];

export default function AdminOrdersIndex({ orders }: OrdersIndexProps) {
    return (
        <PanelLayout title="Orders" nav={nav} brandColor="text-blue-500">
            <Head title="Orders" />
            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="border-b bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Order</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Buyer</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Total</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Payment</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {orders.data.map((order) => (
                            <tr key={order.id}>
                                <td className="px-4 py-3 font-medium">{order.order_number}</td>
                                <td className="px-4 py-3">{order.buyer?.name}</td>
                                <td className="px-4 py-3 text-orange-500">{formatPrice(order.total)}</td>
                                <td className="px-4 py-3 capitalize">{order.status}</td>
                                <td className="px-4 py-3 capitalize">{order.payment_status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </PanelLayout>
    );
}
