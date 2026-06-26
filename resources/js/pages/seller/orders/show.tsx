import { Head, Link, router, useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { formatPrice, formatOrderStatus, OrderItem, productImageUrl } from '@/types/marketplace';

interface OrderShowProps {
    orderItem: OrderItem & {
        order: {
            id: number;
            order_number: string;
            created_at: string;
            payment_status: string;
            payment_channel?: string;
            direct_payment_reference?: string | null;
            receiver_name: string;
            receiver_phone: string;
            city: string;
            region: string;
            delivery_notes?: string;
            buyer?: { name: string; email: string; mobile?: string };
        };
        product?: { images?: { path: string }[] };
    };
}

const nextStatuses = ['processing', 'packed', 'shipped', 'delivered'] as const;

export default function SellerOrderShow({ orderItem }: OrderShowProps) {
    const form = useForm({
        status: orderItem.status,
        courier_name: orderItem.courier_name ?? '',
        tracking_number: orderItem.tracking_number ?? '',
    });

    const image = orderItem.product?.images?.[0];
    const order = orderItem.order;

    const updateStatus = (status: string) => {
        router.patch(route('seller.orders.update', orderItem.id), { status, courier_name: form.data.courier_name, tracking_number: form.data.tracking_number });
    };

    return (
        <SellerLayout title={`Order ${order.order_number}`} active="orders">
            <Head title={`Order ${order.order_number}`} />
            <Link href={route('seller.orders.index')} className="mb-4 inline-block text-sm text-orange-500 hover:underline">← Back to orders</Link>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-6">
                    <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <div className="flex gap-4">
                            <div className="flex h-24 w-24 items-center justify-center rounded-xl bg-gray-50 p-3">
                                {image && <img src={productImageUrl(image.path)} alt="" className="max-h-full max-w-full object-contain" />}
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-gray-900">{orderItem.product_name}</h2>
                                <p className="text-gray-500">Qty: {orderItem.quantity} · {formatPrice(orderItem.unit_price)} each</p>
                                <p className="mt-2 text-2xl font-bold text-orange-500">{formatPrice(orderItem.unit_price * orderItem.quantity)}</p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Fulfillment</h3>
                        <p className="mt-1 text-sm text-gray-500">Current: <span className="capitalize font-medium text-gray-900">{formatOrderStatus(orderItem.status)}</span></p>

                        <div className="mt-4 flex flex-wrap gap-2">
                            {nextStatuses.map((s) => (
                                <Button
                                    key={s}
                                    type="button"
                                    size="sm"
                                    variant={orderItem.status === s ? 'default' : 'outline'}
                                    className={orderItem.status === s ? 'bg-orange-500' : ''}
                                    onClick={() => updateStatus(s)}
                                    disabled={orderItem.status === 'cancelled' || orderItem.status === 'delivered'}
                                >
                                    {formatOrderStatus(s)}
                                </Button>
                            ))}
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Courier</Label>
                                <Input value={form.data.courier_name} onChange={(e) => form.setData('courier_name', e.target.value)} className="mt-1" placeholder="e.g. Speedaf" />
                            </div>
                            <div>
                                <Label>Tracking number</Label>
                                <Input value={form.data.tracking_number} onChange={(e) => form.setData('tracking_number', e.target.value)} className="mt-1" />
                            </div>
                        </div>
                        <Button type="button" className="mt-3 bg-orange-500 hover:bg-orange-600" onClick={() => updateStatus(form.data.status)}>
                            Save tracking info
                        </Button>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Buyer</h3>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div><dt className="text-gray-500">Name</dt><dd className="font-medium">{order.buyer?.name}</dd></div>
                            <div><dt className="text-gray-500">Email</dt><dd>{order.buyer?.email}</dd></div>
                            {order.buyer?.mobile && <div><dt className="text-gray-500">Phone</dt><dd>{order.buyer.mobile}</dd></div>}
                        </dl>
                    </div>

                    <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Delivery address</h3>
                        <p className="mt-3 text-sm text-gray-600">
                            {order.receiver_name}<br />
                            {order.receiver_phone}<br />
                            {order.city}, {order.region}
                        </p>
                        {order.delivery_notes && <p className="mt-2 text-xs text-gray-500">Note: {order.delivery_notes}</p>}
                    </div>

                    <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Payment</h3>
                        <p className="mt-2 text-sm capitalize text-gray-600">{order.payment_channel === 'direct' ? 'Direct payment' : 'CityShop'} · {order.payment_status}</p>
                        {order.direct_payment_reference && <p className="mt-1 text-xs text-gray-500">Ref: {order.direct_payment_reference}</p>}
                        {order.payment_channel === 'direct' && order.payment_status === 'pending' && (
                            <Button className="mt-3 w-full bg-emerald-600 hover:bg-emerald-700" onClick={() => router.post(route('seller.orders.confirm-direct-payment', order.id))}>
                                Confirm payment received
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
