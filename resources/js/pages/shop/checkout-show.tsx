import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FileText, Star } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { LightboxTrigger, orderItemLightboxImages } from '@/components/shop/image-lightbox';
import OrderProgress from '@/components/shop/order-progress';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ShopLayout from '@/layouts/shop-layout';
import { buyerFulfillmentLabel, formatOrderStatus, formatPrice, mostAdvancedItemStatus, orderStatusBadgeClass, Order, OrderItem, productImageUrl } from '@/types/marketplace';

interface CheckoutShowProps {
    checkout: {
        id: number;
        checkout_number: string;
        payment_status: string;
        status: string;
        total: number;
        subtotal: number;
        shipping_cost: number;
        discount_amount?: number;
        receiver_name: string;
        receiver_phone: string;
        region: string;
        city: string;
        orders: (Order & {
            items?: (OrderItem & {
                dispute?: { id: number; status: string; reason: string; description?: string } | null;
                product?: { slug?: string; images?: { path: string }[] };
            })[];
            payment_channel?: string;
            seller?: {
                name: string;
                seller_profile?: { business_name?: string | null; slug?: string | null };
            };
        })[];
        invoices?: { id: number; invoice_number: string; type: string; total: number }[];
    };
    reviews: Record<string, { rating: number; comment?: string }>;
}

const invoiceTypeLabels: Record<string, string> = {
    customer_master: 'Full purchase',
    customer: 'Per seller',
};

const disputeReasons = [
    { value: 'wrong_item', label: 'Wrong item received' },
    { value: 'damaged_item', label: 'Damaged item' },
    { value: 'not_delivered', label: 'Not delivered' },
    { value: 'other', label: 'Other' },
];

function ReviewForm({ orderId, item }: { orderId: number; item: OrderItem }) {
    const { data, setData, post, processing } = useForm({
        order_item_id: item.id,
        rating: 5,
        comment: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('orders.reviews.store', orderId));
    };

    return (
        <form onSubmit={submit} className="mt-3 rounded-lg border border-orange-100 bg-orange-50 p-4">
            <p className="text-sm font-medium text-gray-900">Rate this product</p>
            <div className="mt-2 flex gap-1">
                {[1, 2, 3, 4, 5].map((n) => (
                    <button key={n} type="button" onClick={() => setData('rating', n)}>
                        <Star className={`h-5 w-5 ${n <= data.rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                    </button>
                ))}
            </div>
            <Input
                placeholder="Write a review (optional)"
                value={data.comment}
                onChange={(e) => setData('comment', e.target.value)}
                className="mt-2"
            />
            <Button type="submit" size="sm" disabled={processing} className="mt-2 bg-orange-500 hover:bg-orange-600">
                Submit Review
            </Button>
        </form>
    );
}

function RefundRequestForm({ orderId, item }: { orderId: number; item: OrderItem }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing } = useForm({
        order_item_id: item.id,
        reason: 'wrong_item',
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('orders.disputes.store', orderId), { onSuccess: () => setOpen(false) });
    };

    if (!open) {
        return (
            <button type="button" onClick={() => setOpen(true)} className="mt-2 flex items-center gap-1 text-sm text-red-500 hover:underline">
                <AlertTriangle className="h-4 w-4" />
                Request refund
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="mt-3 rounded-lg border border-red-100 bg-red-50 p-4">
            <p className="text-sm font-medium text-gray-900">Request a refund</p>
            <select
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                className="mt-2 w-full rounded-md border px-3 py-2 text-sm"
            >
                {disputeReasons.map((r) => (
                    <option key={r.value} value={r.value}>
                        {r.label}
                    </option>
                ))}
            </select>
            <textarea
                required
                placeholder="Explain why you need a refund..."
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                className="mt-2 w-full rounded-md border px-3 py-2 text-sm"
                rows={3}
            />
            <div className="mt-2 flex gap-2">
                <Button type="submit" size="sm" variant="destructive" disabled={processing}>
                    Submit request
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={() => setOpen(false)}>
                    Close
                </Button>
            </div>
        </form>
    );
}

function RefundStatus({ dispute }: { dispute: { id: number; status: string; reason: string; description?: string } }) {
    const canCancel = ['open', 'under_review'].includes(dispute.status);

    return (
        <div className="mt-2 rounded-lg border border-amber-100 bg-amber-50 p-3 text-sm">
            <p className="font-medium capitalize text-amber-900">Refund: {dispute.status.replace(/_/g, ' ')}</p>
            <p className="capitalize text-amber-800">{dispute.reason.replace(/_/g, ' ')}</p>
            {dispute.description && <p className="mt-1 text-amber-700">{dispute.description}</p>}
            {canCancel && (
                <Button type="button" size="sm" variant="outline" className="mt-2" onClick={() => router.post(route('disputes.cancel', dispute.id))}>
                    Cancel refund request
                </Button>
            )}
        </div>
    );
}

export default function CheckoutShow({ checkout, reviews }: CheckoutShowProps) {
    return (
        <ShopLayout>
            <Head title={`Purchase ${checkout.checkout_number}`} />
            <div className="mx-auto max-w-4xl px-4 py-8">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Purchase {checkout.checkout_number}</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {checkout.status === 'cancelled' ? (
                                <>
                                    Status: <span className="font-medium text-red-700">Cancelled</span>
                                    {' · '}
                                    Payment: <span className="capitalize">{checkout.payment_status === 'failed' ? 'not paid' : checkout.payment_status}</span>
                                </>
                            ) : (
                                <>
                                    Payment: <span className="capitalize">{checkout.payment_status}</span>
                                </>
                            )}
                            {' · '}
                            {checkout.orders.length === 1
                                ? '1 package'
                                : `${checkout.orders.length} packages from different stores`}
                        </p>
                    </div>
                    <Link href={route('orders.index')} className="text-sm text-orange-500 hover:underline">
                        ← All purchases
                    </Link>
                </div>

                <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold">Shared delivery address</h2>
                    <p className="mt-2 text-sm text-gray-600">
                        {checkout.receiver_name} · {checkout.receiver_phone}
                        <br />
                        {checkout.city}, {checkout.region}
                    </p>
                    <p className="mt-2 text-xs text-gray-500">Each store ships its package on its own timeline.</p>
                </div>

                {checkout.invoices && checkout.invoices.length > 0 && (
                    <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <FileText className="h-5 w-5 text-orange-500" />
                            Invoices
                        </h2>
                        <ul className="mt-3 divide-y text-sm">
                            {checkout.invoices.map((inv) => (
                                <li key={inv.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <div>
                                        <p className="font-medium text-gray-900">{inv.invoice_number}</p>
                                        <p className="text-xs text-gray-400">{invoiceTypeLabels[inv.type] ?? inv.type.replace(/_/g, ' ')}</p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="font-semibold text-gray-900">{formatPrice(inv.total)}</span>
                                        <Link
                                            href={route('invoices.show', inv.id)}
                                            className="rounded-lg bg-orange-50 px-3 py-1.5 text-xs font-medium text-orange-600 hover:bg-orange-100"
                                        >
                                            View invoice
                                        </Link>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="mt-6 space-y-4">
                    {checkout.orders.map((order, index) => {
                        const sellerName =
                            order.seller?.seller_profile?.business_name ?? order.seller?.name ?? 'Seller';
                        const primaryStatus = mostAdvancedItemStatus(order.items) ?? order.status;
                        const isCod = order.payment_method === 'cash';
                        const isOrderCancelled = order.status === 'cancelled';

                        return (
                            <div key={order.id} className="rounded-xl bg-white p-6 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-wide text-gray-400">
                                            Package {index + 1} of {checkout.orders.length}
                                        </p>
                                        {order.seller?.seller_profile?.slug ? (
                                            <Link
                                                href={route('store.show', order.seller.seller_profile.slug)}
                                                className="font-semibold text-gray-900 hover:text-orange-500"
                                            >
                                                {sellerName}
                                            </Link>
                                        ) : (
                                            <p className="font-semibold text-gray-900">{sellerName}</p>
                                        )}
                                        <p className="text-sm text-gray-500">{order.order_number}</p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1.5 text-right">
                                        <p className="font-bold text-orange-500">{formatPrice(order.total)}</p>
                                        {isOrderCancelled ? (
                                            <span className="inline-flex rounded-full bg-red-500 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-white">
                                                Cancelled
                                            </span>
                                        ) : (
                                            <>
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide ${
                                                        isCod
                                                            ? 'bg-teal-600 text-white'
                                                            : order.payment_status === 'paid'
                                                              ? 'bg-emerald-600 text-white'
                                                              : 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200'
                                                    }`}
                                                >
                                                    {isCod
                                                        ? 'Cash on delivery'
                                                        : order.payment_status === 'paid'
                                                          ? 'Paid'
                                                          : 'Awaiting payment'}
                                                </span>
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide ${orderStatusBadgeClass(primaryStatus)}`}
                                                >
                                                    {formatOrderStatus(primaryStatus)}
                                                </span>
                                            </>
                                        )}
                                    </div>
                                </div>

                                {(order.payment_status === 'paid' || order.payment_method === 'cash') && (
                                    <div className="mt-4 border-t pt-4">
                                        <OrderProgress status={primaryStatus} paymentMethod={order.payment_method} />
                                    </div>
                                )}

                                <ul className="mt-4 divide-y text-sm">
                                    {order.items?.map((item) => {
                                        const reviewKey = `${order.id}-${item.product_id ?? 0}`;
                                        const review = reviews[reviewKey];

                                        return (
                                            <li key={item.id} className="py-3">
                                                <div className="flex gap-3">
                                                    {(() => {
                                                        const gallery = orderItemLightboxImages(item);
                                                        const thumb =
                                                            item.product?.images?.[0]?.path
                                                            ?? item.package_image
                                                            ?? undefined;

                                                        if (!thumb || gallery.length === 0) {
                                                            return (
                                                                <img
                                                                    src={productImageUrl(thumb)}
                                                                    alt=""
                                                                    className="h-14 w-14 rounded-lg border object-contain"
                                                                />
                                                            );
                                                        }

                                                        return (
                                                            <LightboxTrigger
                                                                images={gallery}
                                                                startIndex={0}
                                                                className="shrink-0"
                                                            >
                                                                <img
                                                                    src={productImageUrl(thumb)}
                                                                    alt=""
                                                                    className="h-14 w-14 rounded-lg border object-contain"
                                                                />
                                                            </LightboxTrigger>
                                                        );
                                                    })()}
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex justify-between gap-2">
                                                            <div>
                                                                <p className="font-medium text-gray-900">
                                                                    {item.product_name} × {item.quantity}
                                                                </p>
                                                                <p className="mt-1.5">
                                                                    <span
                                                                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${orderStatusBadgeClass(item.status)}`}
                                                                    >
                                                                        {buyerFulfillmentLabel(item.status, order.payment_method)}
                                                                    </span>
                                                                </p>
                                                                {item.status === 'cancelled' && (
                                                                    <div className="mt-2 rounded-lg border border-red-100 bg-red-50 p-3 text-xs text-red-900">
                                                                        <p className="font-semibold">
                                                                            {item.cancelled_by === 'admin'
                                                                                ? 'Cancelled by CityShop support'
                                                                                : 'Cancelled by seller'}
                                                                        </p>
                                                                        {item.rejection_reason && (
                                                                            <p className="mt-1">Reason: {item.rejection_reason}</p>
                                                                        )}
                                                                        {item.refund_status === 'completed' && (
                                                                            <p className="mt-1 font-medium text-emerald-700">
                                                                                Refund completed — credited to your CityShop wallet.
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <span className="shrink-0 font-medium">
                                                                {formatPrice(item.unit_price * item.quantity)}
                                                            </span>
                                                        </div>

                                                        {(item.vehicle_number || item.driver_phone) && (
                                                            <div className="mt-2 rounded-lg bg-blue-50 p-3 text-xs text-blue-900">
                                                                <p className="font-semibold">Delivery details</p>
                                                                {item.driver_phone && <p>Driver phone: {item.driver_phone}</p>}
                                                                {item.vehicle_number && <p>Vehicle: {item.vehicle_number}</p>}
                                                            </div>
                                                        )}

                                                        {item.package_image && (() => {
                                                            const gallery = orderItemLightboxImages(item);
                                                            const packageIndex = Math.max(
                                                                0,
                                                                gallery.findIndex((img) => img.src === item.package_image),
                                                            );

                                                            return (
                                                                <LightboxTrigger
                                                                    images={gallery}
                                                                    startIndex={packageIndex}
                                                                    className="mt-2"
                                                                >
                                                                    <img
                                                                        src={productImageUrl(item.package_image)}
                                                                        alt="Delivery package — tap to enlarge"
                                                                        className="h-24 w-24 rounded-lg border object-cover shadow-sm transition group-hover:ring-2 group-hover:ring-orange-400"
                                                                    />
                                                                    <span className="mt-1 block text-[11px] text-gray-500">
                                                                        Tap to view full size
                                                                    </span>
                                                                </LightboxTrigger>
                                                            );
                                                        })()}

                                                        {item.status === 'awaiting_confirmation' && (
                                                            <div className="mt-3 rounded-xl border border-green-200 bg-green-50 p-4">
                                                                <p className="text-sm font-medium text-green-900">Has this package arrived?</p>
                                                                <Button
                                                                    type="button"
                                                                    className="mt-3 bg-green-600 hover:bg-green-700"
                                                                    onClick={() =>
                                                                        router.post(route('orders.confirm-delivery', [order.id, item.id]))
                                                                    }
                                                                >
                                                                    <CheckCircle2 className="mr-2 h-4 w-4" />
                                                                    I received this item
                                                                </Button>
                                                            </div>
                                                        )}

                                                        {item.dispute && item.dispute.status !== 'cancelled' && (
                                                            <RefundStatus dispute={item.dispute} />
                                                        )}

                                                        {item.status === 'delivered' && !review && <ReviewForm orderId={order.id} item={item} />}

                                                        {review && (
                                                            <p className="mt-2 flex items-center gap-1 text-sm text-gray-600">
                                                                <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
                                                                You rated {review.rating}/5
                                                            </p>
                                                        )}

                                                        {['shipped', 'awaiting_confirmation', 'delivered'].includes(item.status) &&
                                                            (!item.dispute || item.dispute.status === 'cancelled') && (
                                                                <RefundRequestForm orderId={order.id} item={item} />
                                                            )}
                                                    </div>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>

                                <div className="mt-3 space-y-1 border-t pt-3 text-sm text-gray-600">
                                    <div className="flex justify-between">
                                        <span>Items</span>
                                        <span>{formatPrice(order.subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Delivery</span>
                                        <span>{formatPrice(order.shipping_cost)}</span>
                                    </div>
                                    <div className="flex justify-between font-semibold text-gray-900">
                                        <span>Package total</span>
                                        <span>{formatPrice(order.total)}</span>
                                    </div>
                                </div>

                                <Link
                                    href={route('orders.show', { order: order.id, package: 1 })}
                                    className="mt-3 inline-block text-sm text-orange-500 hover:underline"
                                >
                                    Open package page →
                                </Link>
                            </div>
                        );
                    })}
                </div>

                <div className="mt-6 space-y-1 rounded-xl bg-gray-50 p-6 text-sm">
                    <div className="flex justify-between text-gray-600">
                        <span>Items</span>
                        <span>{formatPrice(checkout.subtotal)}</span>
                    </div>
                    {(checkout.discount_amount ?? 0) > 0 && (
                        <div className="flex justify-between text-gray-600">
                            <span>Discounts</span>
                            <span>-{formatPrice(checkout.discount_amount ?? 0)}</span>
                        </div>
                    )}
                    <div className="flex justify-between text-gray-600">
                        <span>Delivery (all packages)</span>
                        <span>{formatPrice(checkout.shipping_cost)}</span>
                    </div>
                    <div className="flex justify-between border-t pt-2 text-lg font-bold text-gray-900">
                        <span>Grand total</span>
                        <span className="text-orange-500">{formatPrice(checkout.total)}</span>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
