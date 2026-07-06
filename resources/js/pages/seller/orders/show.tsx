import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronRight, FormEventHandler } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { formatPrice, formatOrderStatus, OrderItem, productImageUrl } from '@/types/marketplace';

interface DisputeInfo {
    id: number;
    reason: string;
    description: string;
    status: string;
    resolution_notes?: string | null;
}

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
        dispute?: DisputeInfo | null;
    };
    backStage?: string;
}

const sellerFlow: { status: string; label: string; hint: string }[] = [
    { status: 'processing', label: 'Start processing', hint: 'Payment received — begin preparing the order.' },
    { status: 'packed', label: 'Mark as packing', hint: 'Item is being packed and prepared.' },
    { status: 'shipped', label: 'Out for delivery', hint: 'Add driver & vehicle details, then send.' },
    { status: 'awaiting_confirmation', label: 'Mark as delivered', hint: 'You handed the item to the buyer — they must confirm receipt.' },
];

function nextSellerStatus(current: string): string | null {
    if (current === 'pending') return 'processing';
    const idx = sellerFlow.findIndex((s) => s.status === current);
    if (idx === -1 || idx >= sellerFlow.length - 1) return null;
    return sellerFlow[idx + 1].status;
}

export default function SellerOrderShow({ orderItem, backStage = 'new' }: OrderShowProps) {
    const form = useForm({
        status: orderItem.status,
        vehicle_number: orderItem.vehicle_number ?? '',
        driver_phone: orderItem.driver_phone ?? '',
        package_image: null as File | null,
    });

    const image = orderItem.product?.images?.[0];
    const order = orderItem.order;
    const dispute = orderItem.dispute;
    const next = nextSellerStatus(orderItem.status);
    const isTerminal = ['cancelled', 'delivered', 'refunded', 'awaiting_confirmation'].includes(orderItem.status);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seller.orders.update', orderItem.id), {
            forceFormData: true,
            preserveScroll: true,
            onBefore: () => {
                form.transform((data) => ({ ...data, _method: 'patch' }));
            },
            onFinish: () => form.setData('package_image', null),
        });
    };

    const advanceStatus = () => {
        if (!next) return;

        if (next === 'shipped' && (!form.data.vehicle_number.trim() || !form.data.driver_phone.trim())) {
            form.setError('vehicle_number', 'Add vehicle number and driver phone before sending for delivery.');
            return;
        }

        router.patch(route('seller.orders.update', orderItem.id), {
            status: next,
            vehicle_number: form.data.vehicle_number,
            driver_phone: form.data.driver_phone,
        }, { preserveScroll: true });
    };

    const currentStep = sellerFlow.find((s) => s.status === orderItem.status);

    return (
        <SellerLayout title={`Order ${order.order_number}`} active="orders">
            <Head title={`Order ${order.order_number}`} />
            <Link href={route('seller.orders.stage', backStage)} className="mb-4 inline-block text-sm text-orange-500 hover:underline">← Back to queue</Link>

            {dispute && !['cancelled', 'closed'].includes(dispute.status) && (
                <div className="mb-4 rounded-xl border border-red-100 bg-red-50 p-4">
                    <p className="font-semibold text-red-800">Refund request from buyer</p>
                    <p className="mt-1 text-sm text-red-700 capitalize">{dispute.reason.replace(/_/g, ' ')} · {dispute.status.replace(/_/g, ' ')}</p>
                    <p className="mt-2 text-sm text-red-600">{dispute.description}</p>
                    <Link href={route('seller.refunds.index')} className="mt-2 inline-block text-sm font-medium text-red-700 hover:underline">
                        View all refund requests →
                    </Link>
                </div>
            )}

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
                        <p className="mt-1 text-sm text-gray-500">
                            Current: <span className="font-medium text-gray-900">{formatOrderStatus(orderItem.status)}</span>
                        </p>

                        <div className="mt-4 space-y-2">
                            {sellerFlow.map((step, i) => {
                                const stepIdx = sellerFlow.findIndex((s) => s.status === orderItem.status);
                                const done = stepIdx > i || orderItem.status === 'delivered';
                                const active = step.status === orderItem.status;

                                return (
                                    <div
                                        key={step.status}
                                        className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${
                                            active ? 'bg-orange-50 text-orange-800' : done ? 'text-green-700' : 'text-gray-400'
                                        }`}
                                    >
                                        <span className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ${
                                            done ? 'bg-green-500 text-white' : active ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-500'
                                        }`}>
                                            {i + 1}
                                        </span>
                                        <span className="font-medium">{step.label}</span>
                                    </div>
                                );
                            })}
                        </div>

                        {orderItem.status === 'awaiting_confirmation' && (
                            <div className="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                                <p className="font-semibold">Waiting for buyer confirmation</p>
                                <p className="mt-1 text-blue-700">You marked this as delivered. The buyer must confirm receipt before the order is complete and funds are released.</p>
                            </div>
                        )}

                        {orderItem.status === 'delivered' && (
                            <div className="mt-4 rounded-xl border border-green-100 bg-green-50 p-4 text-sm text-green-800">
                                <p className="font-semibold">Order complete</p>
                                <p className="mt-1">Buyer confirmed delivery. Funds have been released to your wallet.</p>
                            </div>
                        )}

                        {next && !isTerminal && (
                            <div className="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
                                <p className="text-sm font-medium text-gray-900">
                                    Next step: {sellerFlow.find((s) => s.status === next)?.label}
                                </p>
                                <p className="mt-1 text-xs text-gray-500">{currentStep?.hint}</p>
                                <Button
                                    type="button"
                                    className="mt-3 bg-orange-500 hover:bg-orange-600"
                                    onClick={advanceStatus}
                                >
                                    {sellerFlow.find((s) => s.status === next)?.label}
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Button>
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-6 space-y-4 border-t border-gray-100 pt-4">
                            <div>
                                <h4 className="text-sm font-semibold text-gray-900">Delivery details</h4>
                                <p className="text-xs text-gray-500">Required when sending out for delivery. Buyer will see driver phone and vehicle number.</p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>Vehicle / car number</Label>
                                    <Input
                                        value={form.data.vehicle_number}
                                        onChange={(e) => form.setData('vehicle_number', e.target.value)}
                                        className="mt-1"
                                        placeholder="e.g. GR 1234-20"
                                    />
                                </div>
                                <div>
                                    <Label>Driver phone</Label>
                                    <Input
                                        value={form.data.driver_phone}
                                        onChange={(e) => form.setData('driver_phone', e.target.value)}
                                        className="mt-1"
                                        placeholder="e.g. 024 000 0000"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label>Package photo</Label>
                                {orderItem.package_image && (
                                    <img
                                        src={productImageUrl(orderItem.package_image)}
                                        alt="Package"
                                        className="mt-2 h-32 w-32 rounded-lg border object-cover"
                                    />
                                )}
                                <Input
                                    type="file"
                                    accept="image/*"
                                    className="mt-2"
                                    onChange={(e) => form.setData('package_image', e.target.files?.[0] ?? null)}
                                />
                            </div>
                            <Button type="submit" disabled={form.processing} className="bg-orange-500 hover:bg-orange-600">
                                Save delivery info
                            </Button>
                        </form>
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
