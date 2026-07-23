import { Head, Link } from '@inertiajs/react';

import AdminOrderTabs from '@/components/admin/admin-order-tabs';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Order, Paginated } from '@/types/marketplace';

interface OrdersIndexProps {
    orders: Paginated<Order & {
        buyer: { name: string };
        checkout?: { checkout_number: string } | null;
        payment_channel?: string;
    }>;
}


export default function AdminOrdersIndex({ orders }: OrdersIndexProps) {
    return (
        <AdminLayout title="Orders" active="orders">
            <Head title="Orders" />
            <AdminOrderTabs active="all" />
            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-[720px] w-full text-sm">
                    <thead className="border-b bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Order</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Buyer</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Total</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Checkout</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Channel</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Payment</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {orders.data.map((order) => (
                            <tr key={order.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3">
                                    <Link href={route('admin.orders.show', order.id)} className="font-medium text-orange-600 hover:underline">
                                        {order.order_number}
                                    </Link>
                                </td>
                                <td className="px-4 py-3">{order.buyer?.name}</td>
                                <td className="px-4 py-3 text-orange-500">{formatPrice(order.total)}</td>
                                <td className="px-4 py-3 capitalize">{order.status}</td>
                                <td className="px-4 py-3 text-gray-500">{order.checkout?.checkout_number ?? '—'}</td>
                                <td className="px-4 py-3 capitalize">{order.payment_channel === 'direct' ? 'Direct' : 'Marketplace'}</td>
                                <td className="px-4 py-3 capitalize">{order.payment_status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
