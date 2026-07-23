import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, formatOrderStatus, orderStatusBadgeClass, Order, OrderItem } from '@/types/marketplace';
import { SharedData } from '@/types';

interface Checkout {
    id: number;
    checkout_number: string;
    payment_status: string;
    status: string;
    total: number;
    subtotal: number;
    commission_amount: number;
    orders?: Order[];
    payments?: { id: number; amount: number; channel: string; status: string; reference?: string }[];
    invoices?: { id: number; invoice_number: string; type: string; total: number }[];
}

interface AdminOrderShowProps {
    order: Order & {
        buyer?: { name: string; email: string };
        items?: (OrderItem & { product?: { name: string }; seller?: { name: string } })[];
        payment_channel?: string;
        seller_payment_method_id?: number | null;
        direct_payment_reference?: string | null;
        sellerPaymentMethod?: { label: string; type: string };
    };
    checkout: Checkout | null;
}

export default function AdminOrderShow({ order, checkout }: AdminOrderShowProps) {
    const { flash } = usePage<SharedData>().props;
    const [busyId, setBusyId] = useState<number | null>(null);

    const confirmDelivery = (itemId: number) => {
        setBusyId(itemId);
        router.post(route('admin.orders.confirm-delivery.store', itemId), {}, {
            preserveScroll: true,
            onFinish: () => setBusyId(null),
        });
    };

    return (
        <AdminLayout title={`Order ${order.order_number}`} active="orders">
            <Head title={`Order ${order.order_number}`} />
            <div className="mb-4">
                <Link href={route('admin.orders.index')} className="text-sm text-orange-500 hover:underline">
                    ← All orders
                </Link>
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

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold text-gray-900">Vendor order</h2>
                    <dl className="mt-4 space-y-2 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Order number</dt>
                            <dd className="font-medium">{order.order_number}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Buyer</dt>
                            <dd>
                                {order.buyer?.name} ({order.buyer?.email})
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Status</dt>
                            <dd className="capitalize">{formatOrderStatus(order.status)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Payment</dt>
                            <dd className="capitalize">{order.payment_status}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Channel</dt>
                            <dd>{order.payment_channel === 'direct' ? 'Direct seller payment' : 'Marketplace (Paystack)'}</dd>
                        </div>
                        {order.sellerPaymentMethod && (
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Seller method</dt>
                                <dd>
                                    {order.sellerPaymentMethod.label} ({order.sellerPaymentMethod.type})
                                </dd>
                            </div>
                        )}
                        {order.direct_payment_reference && (
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Buyer reference</dt>
                                <dd>{order.direct_payment_reference}</dd>
                            </div>
                        )}
                        <div className="flex justify-between border-t pt-2">
                            <dt className="font-medium">Total</dt>
                            <dd className="font-bold text-orange-500">{formatPrice(order.total)}</dd>
                        </div>
                    </dl>
                </div>

                {checkout && (
                    <div className="rounded-xl bg-white p-6 shadow-sm">
                        <h2 className="font-semibold text-gray-900">Parent checkout</h2>
                        <dl className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Checkout</dt>
                                <dd className="font-medium">{checkout.checkout_number}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Checkout status</dt>
                                <dd className="capitalize">{checkout.payment_status}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Grand total</dt>
                                <dd className="font-bold">{formatPrice(checkout.total)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Vendor orders</dt>
                                <dd>{checkout.orders?.length ?? 0}</dd>
                            </div>
                        </dl>
                    </div>
                )}
            </div>

            <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold">Items</h2>
                <div className="mt-4 space-y-3">
                    {order.items?.map((item) => (
                        <div
                            key={item.id}
                            className="flex flex-col gap-3 border-b border-gray-100 pb-3 last:border-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <p className="font-medium text-gray-900">{item.product_name}</p>
                                <p className="mt-0.5 text-sm text-gray-500">
                                    {item.seller?.name ?? '—'} · Qty {item.quantity} ·{' '}
                                    {formatPrice(item.unit_price * item.quantity)}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                <span
                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${orderStatusBadgeClass(item.status)}`}
                                >
                                    {formatOrderStatus(item.status)}
                                </span>
                                {item.status === 'awaiting_confirmation' && (
                                    <Button
                                        size="sm"
                                        className="bg-orange-500 hover:bg-orange-600"
                                        disabled={busyId === item.id}
                                        onClick={() => confirmDelivery(item.id)}
                                    >
                                        {busyId === item.id ? 'Confirming…' : 'Confirm order'}
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {checkout?.orders && checkout.orders.length > 1 && (
                <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold">All vendor orders in checkout</h2>
                    <ul className="mt-4 divide-y text-sm">
                        {checkout.orders.map((vo) => (
                            <li key={vo.id} className="flex items-center justify-between py-2">
                                <span>
                                    {vo.order_number}
                                    {vo.id === order.id && (
                                        <span className="ml-2 text-xs text-orange-500">(this order)</span>
                                    )}
                                </span>
                                <span className="capitalize text-gray-500">
                                    {vo.payment_channel} · {vo.payment_status}
                                </span>
                                <span className="font-medium">{formatPrice(vo.total)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {checkout?.payments && checkout.payments.length > 0 && (
                <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold">Payments</h2>
                    <ul className="mt-4 divide-y text-sm">
                        {checkout.payments.map((p) => (
                            <li key={p.id} className="flex justify-between py-2">
                                <span className="capitalize">
                                    {p.channel} · {p.status}
                                </span>
                                <span>{p.reference ?? '—'}</span>
                                <span className="font-medium">{formatPrice(p.amount)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {checkout?.invoices && checkout.invoices.length > 0 && (
                <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold">Invoices</h2>
                    <ul className="mt-4 divide-y text-sm">
                        {checkout.invoices.map((inv) => (
                            <li key={inv.id} className="flex justify-between py-2">
                                <span>
                                    {inv.invoice_number}{' '}
                                    <span className="text-gray-400">({inv.type.replace(/_/g, ' ')})</span>
                                </span>
                                <span>{formatPrice(inv.total)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </AdminLayout>
    );
}
