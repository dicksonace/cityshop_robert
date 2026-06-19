import { Head, Link } from '@inertiajs/react';

import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, formatOrderStatus, Order, Paginated } from '@/types/marketplace';

interface OrdersProps {
    orders: Paginated<Order>;
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export default function Orders({ orders }: OrdersProps) {
    return (
        <ShopLayout>
            <Head title="My Orders" />
            <div className="mx-auto max-w-4xl px-4 py-8">
                <h1 className="text-2xl font-bold text-gray-900">My Orders</h1>

                {orders.data.length === 0 ? (
                    <div className="mt-8 rounded-xl bg-white p-12 text-center shadow-sm">
                        <p className="text-gray-500">No orders yet.</p>
                        <Link href={route('home')} className="mt-4 inline-block text-orange-500 hover:underline">
                            Start Shopping
                        </Link>
                    </div>
                ) : (
                    <div className="mt-6 space-y-4">
                        {orders.data.map((order) => (
                            <Link
                                key={order.id}
                                href={route('orders.show', order.id)}
                                className="block rounded-xl bg-white p-4 shadow-sm transition hover:shadow-md"
                            >
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium text-gray-900">{order.order_number}</p>
                                        <p className="text-sm text-gray-500">{new Date(order.created_at).toLocaleDateString()}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-bold text-orange-500">{formatPrice(order.total)}</p>
                                        <span className={`mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[order.status] ?? 'bg-gray-100 text-gray-800'}`}>
                                            {formatOrderStatus(order.status)}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
