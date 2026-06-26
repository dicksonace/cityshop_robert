import { Head, Link } from '@inertiajs/react';

import ShopLayout from '@/layouts/shop-layout';
import { formatPrice, formatOrderStatus, Order, OrderItem } from '@/types/marketplace';

interface CheckoutShowProps {
    checkout: {
        id: number;
        checkout_number: string;
        payment_status: string;
        status: string;
        total: number;
        subtotal: number;
        commission_amount: number;
        receiver_name: string;
        receiver_phone: string;
        region: string;
        city: string;
        orders: (Order & { items?: OrderItem[]; payment_channel?: string; seller?: { name: string } })[];
        invoices?: { id: number; invoice_number: string; type: string; total: number }[];
    };
}

export default function CheckoutShow({ checkout }: CheckoutShowProps) {
    return (
        <ShopLayout>
            <Head title={`Checkout ${checkout.checkout_number}`} />
            <div className="mx-auto max-w-4xl px-4 py-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Checkout {checkout.checkout_number}</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Payment: <span className="capitalize">{checkout.payment_status}</span> · {formatOrderStatus(checkout.status)}
                        </p>
                    </div>
                    <Link href={route('orders.index')} className="text-sm text-orange-500 hover:underline">← All orders</Link>
                </div>

                <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold">Delivery</h2>
                    <p className="mt-2 text-sm text-gray-600">
                        {checkout.receiver_name} · {checkout.receiver_phone}<br />
                        {checkout.city}, {checkout.region}
                    </p>
                </div>

                {checkout.invoices && checkout.invoices.length > 0 && (
                    <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                        <h2 className="font-semibold">Invoices</h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            {checkout.invoices.map((inv) => (
                                <li key={inv.id} className="flex justify-between">
                                    <span>{inv.invoice_number} <span className="text-gray-400">({inv.type.replace('_', ' ')})</span></span>
                                    <span>{formatPrice(inv.total)}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-2 text-xs text-gray-500">Invoice copies were emailed to you.</p>
                    </div>
                )}

                <div className="mt-6 space-y-4">
                    {checkout.orders.map((order) => (
                        <div key={order.id} className="rounded-xl bg-white p-6 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{order.seller?.name ?? 'Seller'}</p>
                                    <p className="text-sm text-gray-500">Order {order.order_number}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-orange-500">{formatPrice(order.total)}</p>
                                    <p className="text-xs capitalize text-gray-500">
                                        {order.payment_channel === 'direct' ? 'Direct payment' : 'CityShop payment'} · {order.payment_status}
                                    </p>
                                </div>
                            </div>
                            <ul className="mt-4 divide-y text-sm">
                                {order.items?.map((item) => (
                                    <li key={item.id} className="flex justify-between py-2">
                                        <span>{item.product_name} × {item.quantity}</span>
                                        <span>{formatPrice(item.unit_price * item.quantity)}</span>
                                    </li>
                                ))}
                            </ul>
                            <Link href={route('orders.show', order.id)} className="mt-3 inline-block text-sm text-orange-500 hover:underline">
                                View order details
                            </Link>
                        </div>
                    ))}
                </div>

                <div className="mt-6 rounded-xl bg-gray-50 p-6 text-right">
                    <p className="text-lg font-bold">Grand total: {formatPrice(checkout.total)}</p>
                </div>
            </div>
        </ShopLayout>
    );
}
