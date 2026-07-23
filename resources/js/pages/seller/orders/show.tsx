import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronRight, Download, LoaderCircle, Printer } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
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
            payment_method?: string | null;
            payment_channel?: string;
            direct_payment_reference?: string | null;
            direct_payment_proof_path?: string | null;
            direct_payment_rejection_reason?: string | null;
            receiver_name: string;
            receiver_phone: string;
            city: string;
            region: string;
            delivery_notes?: string;
            buyer?: { name: string; email: string; mobile?: string };
        };
        product?: { images?: { path: string }[] };
        dispute?: DisputeInfo | null;
        rejection_reason?: string | null;
        cancellation_code?: string | null;
        cancelled_by?: string | null;
        cancelled_at?: string | null;
        refund_status?: string | null;
        funds_release_status?: string | null;
        funds_release_notes?: string | null;
    };
    backStage?: string;
    cancellationReasons: Record<string, string>;
    canCancel: boolean;
}

const paidSellerFlow: { status: string; label: string; hint: string }[] = [
    { status: 'processing', label: 'Start processing', hint: 'Payment received — begin preparing the order.' },
    { status: 'packed', label: 'Mark as packing', hint: 'Item is being packed and prepared.' },
    { status: 'shipped', label: 'Out for delivery', hint: 'Optional: add driver & vehicle if someone else is delivering.' },
    { status: 'awaiting_confirmation', label: 'Mark as delivered', hint: 'You handed the item to the buyer — they must confirm receipt.' },
];

const codSellerFlow: { status: string; label: string; hint: string }[] = [
    { status: 'pending', label: 'Cash on delivery', hint: 'New COD order — start processing when ready.' },
    { status: 'processing', label: 'Start processing', hint: 'Cash on delivery order — begin preparing.' },
    { status: 'call_confirmed', label: 'Call buyer', hint: 'Call the buyer to confirm the order, then continue.' },
    { status: 'packed', label: 'Mark as packing', hint: 'Pack the item after you spoke with the buyer.' },
    { status: 'shipped', label: 'Package on the way', hint: 'Optional: add driver & vehicle if someone else is delivering.' },
    { status: 'delivered', label: 'Complete (cash collected)', hint: 'Buyer paid cash on delivery — mark the order complete.' },
];

function nextSellerStatus(current: string, isCod: boolean): string | null {
    if (!isCod && current === 'pending') return 'processing';
    const flow = isCod ? codSellerFlow : paidSellerFlow;
    const idx = flow.findIndex((s) => s.status === current);
    if (idx === -1 || idx >= flow.length - 1) return null;
    return flow[idx + 1].status;
}

export default function SellerOrderShow({
    orderItem,
    backStage = 'new',
    cancellationReasons = {},
    canCancel = false,
}: OrderShowProps) {
    const order = orderItem?.order;
    const itemStatus = String(orderItem?.status ?? '');
    const [advancing, setAdvancing] = useState(false);
    const [showCancel, setShowCancel] = useState(false);
    const [showRejectPayment, setShowRejectPayment] = useState(false);
    const cancelSectionRef = useRef<HTMLDivElement>(null);
    const form = useForm({
        status: itemStatus,
        vehicle_number: orderItem?.vehicle_number ?? '',
        driver_phone: orderItem?.driver_phone ?? '',
        package_image: null as File | null,
    });
    const cancelForm = useForm({
        cancellation_code: 'out_of_stock',
        rejection_reason: '',
    });
    const rejectPaymentForm = useForm({
        reason: '',
    });

    useEffect(() => {
        if (!canCancel) return;
        const params = new URLSearchParams(window.location.search);
        if (params.get('cancel') !== '1') return;
        setShowCancel(true);
        window.setTimeout(() => {
            cancelSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }, [canCancel]);

    if (!orderItem || !order) {
        return (
            <SellerLayout title="Order" active="orders">
                <Head title="Order" />
                <Link href={route('seller.orders.index')} className="mb-4 inline-block text-sm text-orange-500 hover:underline">
                    ← Back to sales center
                </Link>
                <div className="rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm">
                    <p className="font-medium text-gray-900">Order details unavailable</p>
                    <p className="mt-2 text-sm text-gray-500">This order could not be loaded. Try again from your sales queue.</p>
                </div>
            </SellerLayout>
        );
    }

    const image = orderItem.product?.images?.[0];
    const dispute = orderItem.dispute;
    const isCod = order.payment_method === 'cash';
    const sellerFlow = isCod ? codSellerFlow : paidSellerFlow;
    const next = nextSellerStatus(itemStatus, isCod);
    const isTerminal = isCod
        ? ['cancelled', 'delivered', 'refunded'].includes(itemStatus)
        : ['cancelled', 'delivered', 'refunded', 'awaiting_confirmation'].includes(itemStatus);
    const needsDeliveryDetails = next === 'shipped';
    const buyerPhone = order.receiver_phone || order.buyer?.mobile || '';

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
        if (!next || advancing) return;

        form.clearErrors();

        if (next === 'shipped') {
            setAdvancing(true);
            form.transform((data) => ({
                ...data,
                status: 'shipped',
                vehicle_number: form.data.vehicle_number.trim(),
                driver_phone: form.data.driver_phone.trim(),
                _method: 'patch',
            }));
            form.post(route('seller.orders.update', orderItem.id), {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setAdvancing(false);
                    form.setData('package_image', null);
                },
            });
            return;
        }

        setAdvancing(true);
        router.patch(
            route('seller.orders.update', orderItem.id),
            {
                status: next,
                vehicle_number: form.data.vehicle_number.trim(),
                driver_phone: form.data.driver_phone.trim(),
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    Object.entries(errors).forEach(([key, message]) => {
                        form.setError(key as 'vehicle_number' | 'driver_phone' | 'status', String(message));
                    });
                },
                onFinish: () => setAdvancing(false),
            },
        );
    };

    const currentStep = sellerFlow.find((s) => s.status === itemStatus);

    const submitCancel: FormEventHandler = (e) => {
        e.preventDefault();
        cancelForm.post(route('seller.orders.reject', orderItem.id), {
            preserveScroll: true,
            onSuccess: () => setShowCancel(false),
        });
    };

    const hasPaymentClaim = Boolean(order.direct_payment_reference || order.direct_payment_proof_path);
    const needsPaymentReview =
        order.payment_channel === 'direct' && order.payment_status === 'pending' && hasPaymentClaim;

    const submitRejectPayment: FormEventHandler = (e) => {
        e.preventDefault();
        rejectPaymentForm.post(route('seller.orders.reject-direct-payment', order.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowRejectPayment(false);
                rejectPaymentForm.reset();
            },
        });
    };

    return (
        <SellerLayout title={`Order ${order.order_number}`} active="orders">
            <Head title={`Order ${order.order_number}`} />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <Link href={route('seller.orders.stage', backStage)} className="inline-block text-sm text-orange-500 hover:underline">
                    ← Back to queue
                </Link>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm" className="border-gray-200">
                        <a href={route('seller.orders.print', orderItem.id)} target="_blank" rel="noreferrer">
                            <Printer className="mr-1.5 h-4 w-4" />
                            Print packing slip
                        </a>
                    </Button>
                    <Button asChild size="sm" className="bg-gray-900 hover:bg-gray-800">
                        <a href={route('seller.orders.pdf', orderItem.id)}>
                            <Download className="mr-1.5 h-4 w-4" />
                            Download PDF
                        </a>
                    </Button>
                </div>
            </div>

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
                            Current: <span className="font-medium text-gray-900">{formatOrderStatus(itemStatus)}</span>
                        </p>

                        {itemStatus === 'pending' && (
                            <div className="mt-4 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
                                {isCod ? (
                                    <>
                                        <p className="font-semibold">New Order (Cash on Delivery)</p>
                                        <p className="mt-1 text-amber-800">
                                            Buyer will pay cash when you deliver. Start processing, call them to confirm, then pack and send.
                                        </p>
                                    </>
                                ) : (
                                    <>
                                        <p className="font-semibold">New order — payment received</p>
                                        <p className="mt-1 text-amber-800">Tap &quot;Start processing&quot; below when you begin preparing this order.</p>
                                    </>
                                )}
                            </div>
                        )}

                        {isCod && itemStatus === 'processing' && (
                            <div className="mt-4 rounded-xl border border-teal-100 bg-teal-50 p-4 text-sm text-teal-900">
                                <p className="font-semibold">Call the buyer next</p>
                                <p className="mt-1 text-teal-800">
                                    Confirm the order by phone before packing.
                                    {buyerPhone ? (
                                        <>
                                            {' '}
                                            <a href={`tel:${buyerPhone}`} className="font-semibold underline">
                                                Call {buyerPhone}
                                            </a>
                                        </>
                                    ) : null}
                                </p>
                            </div>
                        )}

                        {isCod && itemStatus === 'call_confirmed' && (
                            <div className="mt-4 rounded-xl border border-fuchsia-100 bg-fuchsia-50 p-4 text-sm text-fuchsia-900">
                                <p className="font-semibold">Call done — pack the order</p>
                                <p className="mt-1 text-fuchsia-800">You confirmed with the buyer. Mark packing when the item is ready.</p>
                            </div>
                        )}

                        {isCod && itemStatus === 'shipped' && (
                            <div className="mt-4 rounded-xl border border-orange-100 bg-orange-50 p-4 text-sm text-orange-900">
                                <p className="font-semibold">Package on the way</p>
                                <p className="mt-1 text-orange-800">Collect cash on delivery, then tap Complete.</p>
                            </div>
                        )}

                        <div className="mt-4 space-y-2">
                            {sellerFlow.map((step, i) => {
                                const stepIdx = sellerFlow.findIndex((s) => s.status === itemStatus);
                                const done = stepIdx > i || itemStatus === 'delivered';
                                const active = step.status === itemStatus;

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

                        {itemStatus === 'awaiting_confirmation' && (
                            <div className="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                                <p className="font-semibold">Waiting for buyer confirmation</p>
                                <p className="mt-1 text-blue-700">
                                    The buyer must confirm receipt to complete the order. Admin can release Pending funds anytime after you start processing — or the buyer’s confirm will release them.
                                </p>
                            </div>
                        )}

                        {itemStatus === 'delivered' && orderItem.funds_release_status === 'pending' && (
                            <div className="mt-4 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
                                <p className="font-semibold">Waiting for fund release</p>
                                <p className="mt-1">Delivery is complete. Earnings stay in Pending until admin releases to Available.</p>
                            </div>
                        )}

                        {itemStatus === 'delivered' && orderItem.funds_release_status === 'held' && (
                            <div className="mt-4 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-800">
                                <p className="font-semibold">Funds on hold</p>
                                <p className="mt-1">
                                    Admin held this release
                                    {orderItem.funds_release_notes ? `: ${orderItem.funds_release_notes}` : '. A dispute may be open for review.'}
                                </p>
                            </div>
                        )}

                        {itemStatus === 'delivered' && (orderItem.funds_release_status === 'released' || orderItem.funds_release_status === 'not_applicable' || !orderItem.funds_release_status) && (
                            <div className="mt-4 rounded-xl border border-green-100 bg-green-50 p-4 text-sm text-green-800">
                                <p className="font-semibold">Order complete</p>
                                <p className="mt-1">
                                    {orderItem.funds_release_status === 'released'
                                        ? 'Funds have been released to your Available balance.'
                                        : 'Buyer confirmed delivery.'}
                                </p>
                            </div>
                        )}

                        {needsDeliveryDetails && (
                            <div
                                className="mt-4 space-y-4 rounded-xl border-2 border-orange-200 bg-orange-50/60 p-4"
                            >
                                <div>
                                    <h4 className="text-sm font-semibold text-gray-900">Delivery details (optional)</h4>
                                    <p className="mt-1 text-xs text-gray-600">
                                        Add vehicle number and driver phone if someone else is delivering. Skip if you are bringing the order yourself — the buyer will only see details you enter.
                                    </p>
                                </div>
                                {(form.errors.status || form.errors.vehicle_number || form.errors.driver_phone) && (
                                    <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                        {form.errors.status || form.errors.vehicle_number || form.errors.driver_phone}
                                    </div>
                                )}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label>Vehicle / car number</Label>
                                        <Input
                                            value={form.data.vehicle_number}
                                            onChange={(e) => {
                                                form.setData('vehicle_number', e.target.value);
                                                form.clearErrors('vehicle_number', 'status');
                                            }}
                                            className="mt-1 bg-white"
                                            placeholder="e.g. GR 1234-20"
                                        />
                                        <InputError message={form.errors.vehicle_number} />
                                    </div>
                                    <div>
                                        <Label>Driver phone</Label>
                                        <Input
                                            value={form.data.driver_phone}
                                            onChange={(e) => {
                                                form.setData('driver_phone', e.target.value);
                                                form.clearErrors('driver_phone', 'status');
                                            }}
                                            className="mt-1 bg-white"
                                            placeholder="e.g. 024 000 0000"
                                            inputMode="tel"
                                        />
                                        <InputError message={form.errors.driver_phone} />
                                    </div>
                                </div>
                                <div>
                                    <Label>Package photo (optional)</Label>
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
                                        className="mt-2 bg-white"
                                        onChange={(e) => form.setData('package_image', e.target.files?.[0] ?? null)}
                                    />
                                </div>
                            </div>
                        )}

                        {itemStatus === 'cancelled' && (
                            <div className="mt-4 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-900">
                                <p className="font-semibold">Order cancelled</p>
                                {orderItem.rejection_reason && (
                                    <p className="mt-1">Reason: {orderItem.rejection_reason}</p>
                                )}
                                {orderItem.refund_status === 'completed' && (
                                    <p className="mt-1 text-red-700">Buyer refunded to CityShop wallet.</p>
                                )}
                                {orderItem.refund_status === 'not_applicable' && (
                                    <p className="mt-1 text-red-700">No automatic wallet refund (direct payment or unpaid).</p>
                                )}
                            </div>
                        )}

                        {next && !isTerminal && (
                            <div className="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
                                <p className="text-sm font-medium text-gray-900">
                                    Next step: {sellerFlow.find((s) => s.status === next)?.label}
                                </p>
                                <p className="mt-1 text-xs text-gray-500">
                                    {needsDeliveryDetails
                                        ? 'Tap Out for delivery when ready. Driver details are optional if you deliver yourself.'
                                        : currentStep?.hint}
                                </p>
                                <Button
                                    type="button"
                                    className="mt-3 w-full bg-orange-500 hover:bg-orange-600 sm:w-auto"
                                    onClick={advanceStatus}
                                    disabled={advancing}
                                >
                                    {advancing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    {sellerFlow.find((s) => s.status === next)?.label}
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Button>
                            </div>
                        )}

                        {canCancel && !isTerminal && (
                            <div ref={cancelSectionRef} className="mt-4 rounded-xl border border-red-100 bg-white p-4">
                                {!showCancel ? (
                                    <>
                                        <p className="text-sm font-medium text-gray-900">Can&apos;t fulfill this order?</p>
                                        <p className="mt-1 text-xs text-gray-500">
                                            Cancel before shipping if you&apos;re out of stock or unable to fulfill.
                                            Paid CityShop (marketplace) orders refund the buyer from escrow.
                                            Paid Pay-to-seller orders debit your CityShop wallet available balance and refund the buyer.
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="mt-3 border-red-200 text-red-700 hover:bg-red-50"
                                            onClick={() => setShowCancel(true)}
                                        >
                                            Cancel order
                                        </Button>
                                    </>
                                ) : (
                                    <form onSubmit={submitCancel} className="space-y-3">
                                        <p className="text-sm font-semibold text-red-800">Cancel order</p>
                                        <div>
                                            <Label>Reason *</Label>
                                            <select
                                                value={cancelForm.data.cancellation_code}
                                                onChange={(e) => cancelForm.setData('cancellation_code', e.target.value)}
                                                className="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm"
                                            >
                                                {Object.entries(cancellationReasons).map(([code, label]) => (
                                                    <option key={code} value={code}>{label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <Label>
                                                {cancelForm.data.cancellation_code === 'other'
                                                    ? 'Explain *'
                                                    : 'Extra note (optional)'}
                                            </Label>
                                            <textarea
                                                value={cancelForm.data.rejection_reason}
                                                onChange={(e) => cancelForm.setData('rejection_reason', e.target.value)}
                                                className="mt-1 w-full rounded-md border border-input px-3 py-2 text-sm"
                                                rows={3}
                                                placeholder="Shown to the buyer"
                                            />
                                            <InputError message={cancelForm.errors.rejection_reason ?? cancelForm.errors.cancellation_code} />
                                        </div>
                                        <p className="text-xs text-gray-500">
                                            {order.payment_channel === 'direct'
                                                ? order.payment_status === 'paid'
                                                    ? 'Pay-to-seller: cancelling debits your CityShop wallet available balance and refunds the buyer. Top up first if your balance is too low.'
                                                    : 'Pay-to-seller: order is not paid yet — no wallet debit.'
                                                : order.payment_status === 'paid'
                                                    ? 'Buyer will get a full product refund in their CityShop wallet.'
                                                    : 'Order is not paid yet — no wallet refund needed.'}
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                disabled={cancelForm.processing}
                                                className="bg-red-600 hover:bg-red-700"
                                            >
                                                {cancelForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                                Confirm cancel
                                            </Button>
                                            <Button type="button" variant="outline" onClick={() => setShowCancel(false)}>
                                                Back
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        )}

                        {!needsDeliveryDetails && (
                            <form onSubmit={submit} className="mt-6 space-y-4 border-t border-gray-100 pt-4">
                                <div>
                                    <h4 className="text-sm font-semibold text-gray-900">Delivery details</h4>
                                    <p className="text-xs text-gray-500">Optional. Add if a driver is delivering for you; leave blank if you bring it yourself. Buyer only sees what you enter.</p>
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
                                        <InputError message={form.errors.vehicle_number} />
                                    </div>
                                    <div>
                                        <Label>Driver phone</Label>
                                        <Input
                                            value={form.data.driver_phone}
                                            onChange={(e) => form.setData('driver_phone', e.target.value)}
                                            className="mt-1"
                                            placeholder="e.g. 024 000 0000"
                                            inputMode="tel"
                                        />
                                        <InputError message={form.errors.driver_phone} />
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
                        )}
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
                        {isCod ? (
                            <>
                                <h3 className="font-semibold text-gray-900">Cash on delivery</h3>
                                <p className="mt-2 text-sm text-gray-600">
                                    Buyer pays cash when you deliver · <span className="capitalize">{order.payment_status}</span>
                                </p>
                                <p className="mt-1 text-xs text-teal-700">
                                    Call the buyer to confirm, pack, send the package, then mark complete when cash is collected.
                                </p>
                            </>
                        ) : order.payment_channel === 'direct' ? (
                            <>
                                <h3 className="font-semibold text-gray-900">Customer payment (manual)</h3>
                                <p className="mt-2 text-sm text-gray-600">
                                    Pay to seller · <span className="capitalize">{order.payment_status}</span>
                                </p>
                                <p className="mt-1 text-xs text-amber-700">
                                    Buyer pays you directly (MoMo/bank). Confirm only if you received the money.
                                </p>
                            </>
                        ) : (
                            <>
                                <h3 className="font-semibold text-gray-900">Automatic Pay</h3>
                                <p className="mt-2 text-sm text-gray-600">
                                    Secured payment to CityShop · <span className="capitalize">{order.payment_status}</span>
                                </p>
                                <p className="mt-1 text-xs text-emerald-700">
                                    Buyer paid through CityShop (Paystack / wallet). Funds settle via your seller wallet.
                                </p>
                            </>
                        )}
                        {order.direct_payment_reference && (
                            <div className="mt-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Transaction ID</p>
                                <p className="mt-0.5 break-all font-mono text-sm font-semibold text-gray-900">
                                    {order.direct_payment_reference}
                                </p>
                            </div>
                        )}
                        {order.direct_payment_proof_path && (
                            <a
                                href={productImageUrl(order.direct_payment_proof_path)}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-3 block"
                            >
                                <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Payment screenshot</p>
                                <img
                                    src={productImageUrl(order.direct_payment_proof_path)}
                                    alt="Buyer payment screenshot"
                                    className="max-h-56 w-full rounded-lg border border-gray-200 object-contain bg-white"
                                />
                                <p className="mt-1 text-xs text-orange-600 hover:underline">Open full screenshot</p>
                            </a>
                        )}
                        {order.payment_channel === 'direct' && order.payment_status === 'pending' && !hasPaymentClaim && (
                            <p className="mt-3 text-sm text-amber-700">Waiting for the buyer to submit their payment claim.</p>
                        )}
                        {needsPaymentReview && (
                            <div className="mt-3 space-y-2">
                                <p className="text-xs text-amber-800">
                                    Confirm only if you received the money. Reject if the buyer claimed payment but you did not get paid.
                                </p>
                                <Button
                                    className="w-full bg-emerald-600 hover:bg-emerald-700"
                                    onClick={() => router.post(route('seller.orders.confirm-direct-payment', order.id))}
                                >
                                    Confirm payment received
                                </Button>
                                {!showRejectPayment ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full border-red-200 text-red-600 hover:bg-red-50"
                                        onClick={() => setShowRejectPayment(true)}
                                    >
                                        Reject payment claim
                                    </Button>
                                ) : (
                                    <form onSubmit={submitRejectPayment} className="space-y-2 rounded-xl border border-red-100 bg-red-50 p-3">
                                        <Label htmlFor="reject-payment-reason">Why are you rejecting?</Label>
                                        <Input
                                            id="reject-payment-reason"
                                            placeholder="e.g. Money not received on MoMo"
                                            value={rejectPaymentForm.data.reason}
                                            onChange={(e) => rejectPaymentForm.setData('reason', e.target.value)}
                                            required
                                            minLength={5}
                                        />
                                        <InputError message={rejectPaymentForm.errors.reason} />
                                        <div className="flex gap-2">
                                            <Button
                                                type="submit"
                                                disabled={rejectPaymentForm.processing}
                                                className="flex-1 bg-red-600 hover:bg-red-700"
                                            >
                                                {rejectPaymentForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                                Reject claim
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => {
                                                    setShowRejectPayment(false);
                                                    rejectPaymentForm.reset();
                                                }}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
