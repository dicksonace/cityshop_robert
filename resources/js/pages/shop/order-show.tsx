import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ChevronRight, Star } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { LightboxTrigger, orderItemLightboxImages } from '@/components/shop/image-lightbox';
import OrderProgress from '@/components/shop/order-progress';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ShopLayout from '@/layouts/shop-layout';
import { buyerFulfillmentLabel, formatPrice, formatOrderStatus, mostAdvancedItemStatus, orderStatusBadgeClass, Order, OrderItem, productImageUrl } from '@/types/marketplace';

interface OrderShowProps {
    order: Order & {
        items?: (OrderItem & { dispute?: { id: number; status: string; reason: string; description?: string } })[];
        seller?: {
            name?: string;
            seller_profile?: {
                business_name?: string | null;
                store_name?: string | null;
                slug?: string | null;
                shop_photo?: string | null;
                rating?: number;
                total_sales?: number;
            } | null;
        } | null;
    };
    reviews: Record<number, { rating: number; comment?: string }>;
}

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
            <p className="mt-1 text-xs text-gray-500">Admin will review before any refund is approved. You can cancel while it is pending.</p>
            <select
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                className="mt-2 w-full rounded-md border px-3 py-2 text-sm"
            >
                {disputeReasons.map((r) => (
                    <option key={r.value} value={r.value}>{r.label}</option>
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
                <Button type="submit" size="sm" variant="destructive" disabled={processing}>Submit request</Button>
                <Button type="button" size="sm" variant="outline" onClick={() => setOpen(false)}>Close</Button>
            </div>
        </form>
    );
}

function RefundStatus({ dispute }: { dispute: { id: number; status: string; reason: string; description?: string } }) {
    const canCancel = ['open', 'under_review'].includes(dispute.status);

    return (
        <div className="mt-2 rounded-lg border border-amber-100 bg-amber-50 p-3 text-sm">
            <p className="font-medium text-amber-900 capitalize">Refund: {dispute.status.replace(/_/g, ' ')}</p>
            <p className="text-amber-800 capitalize">{dispute.reason.replace(/_/g, ' ')}</p>
            {dispute.description && <p className="mt-1 text-amber-700">{dispute.description}</p>}
            {canCancel && (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="mt-2"
                    onClick={() => router.post(route('disputes.cancel', dispute.id))}
                >
                    Cancel refund request
                </Button>
            )}
        </div>
    );
}

export default function OrderShow({ order, reviews, checkoutNumber, checkoutId }: OrderShowProps & { checkoutNumber?: string | null; checkoutId?: number | null }) {
    const isCancelled =
        order.status === 'cancelled'
        || (order.items?.length > 0 && order.items.every((item) => item.status === 'cancelled'));
    const isCod = order.payment_method === 'cash';
    const paymentPending = order.payment_status === 'pending' && !isCancelled && !isCod;
    const primaryStatus = mostAdvancedItemStatus(order.items) ?? order.status;

    const paymentBadge = (() => {
        if (isCancelled) {
            return { label: 'Cancelled', className: 'bg-red-500 text-white' };
        }
        if (isCod) {
            return { label: 'Cash on delivery', className: 'bg-teal-600 text-white' };
        }
        if (order.payment_status === 'paid') {
            return { label: 'Paid', className: 'bg-emerald-600 text-white' };
        }
        if (order.payment_status === 'refunded') {
            return { label: 'Refunded', className: 'bg-blue-100 text-blue-800' };
        }
        if (order.payment_status === 'failed') {
            return { label: 'Not paid', className: 'bg-gray-100 text-gray-700' };
        }
        return {
            label: 'Awaiting payment',
            className: 'bg-yellow-100 text-yellow-800',
        };
    })();

    const fulfillmentLabel = formatOrderStatus(primaryStatus);

    return (
        <ShopLayout>
            <Head title={`Order ${order.order_number}`} />
            <div className="mx-auto max-w-3xl px-4 py-8">
                <Link
                    href={checkoutId ? route('checkouts.show', checkoutId) : route('orders.index')}
                    className="text-sm text-orange-500 hover:underline"
                >
                    &larr; {checkoutId ? `Back to purchase ${checkoutNumber ?? ''}`.trim() : 'Back to Orders'}
                </Link>

                {paymentPending && (
                    <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p className="font-medium text-amber-800">Payment pending</p>
                        <Link
                            href={checkoutId ? route('checkout.payment', checkoutId) : route('checkout.payment', order.id)}
                            className="mt-2 inline-block text-sm text-orange-600 hover:underline"
                        >
                            Complete payment &rarr;
                        </Link>
                    </div>
                )}

                <div className="mt-4 rounded-xl bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0 flex-1 sm:pr-4">
                            <h1 className="break-all text-xl font-bold text-gray-900 sm:truncate">{order.order_number}</h1>
                            <p className="mt-1 text-sm text-gray-500">Placed on {new Date(order.created_at).toLocaleString()}</p>
                        </div>
                        <div className="flex shrink-0 flex-col items-start gap-1.5 sm:items-end">
                            <span className={`rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${paymentBadge.className}`}>
                                {paymentBadge.label}
                            </span>
                            {!isCancelled && (
                                <span
                                    className={`rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${orderStatusBadgeClass(primaryStatus)}`}
                                >
                                    {fulfillmentLabel}
                                </span>
                            )}
                        </div>
                    </div>

                    {(order.payment_status === 'paid' || order.payment_method === 'cash') && (
                        <div className="mt-6 border-t pt-6">
                            <h3 className="mb-4 text-sm font-semibold text-gray-900">Order progress</h3>
                            <OrderProgress status={primaryStatus} paymentMethod={order.payment_method} />
                        </div>
                    )}

                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900">Deliver to</h3>
                            <p className="mt-1 text-sm text-gray-600">{order.receiver_name}</p>
                            <p className="text-sm text-gray-600">{order.receiver_phone}</p>
                            <p className="text-sm text-gray-600">{order.city}, {order.region}</p>
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900">Payment</h3>
                            <p className="mt-1 text-sm text-gray-600 capitalize">
                                {isCod ? 'Cash on delivery' : order.payment_method}
                            </p>
                        </div>
                    </div>

                    {order.seller && (
                        <div className="mt-6 rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <h3 className="text-sm font-semibold text-gray-900">Seller information</h3>
                            <div className="mt-3 flex items-center gap-3">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-lg font-bold text-white">
                                    {order.seller.seller_profile?.shop_photo ? (
                                        <img
                                            src={productImageUrl(order.seller.seller_profile.shop_photo)}
                                            alt=""
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        (
                                            order.seller.seller_profile?.business_name
                                            ?? order.seller.seller_profile?.store_name
                                            ?? order.seller.name
                                            ?? 'S'
                                        ).charAt(0).toUpperCase()
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold text-gray-900">
                                        {order.seller.seller_profile?.business_name
                                            ?? order.seller.seller_profile?.store_name
                                            ?? order.seller.name
                                            ?? 'Seller'}
                                    </p>
                                    {isCod && (
                                        <p className="mt-0.5 text-xs text-teal-700">Cash on delivery order</p>
                                    )}
                                </div>
                                {order.seller.seller_profile?.slug && (
                                    <Link
                                        href={route('store.show', order.seller.seller_profile.slug)}
                                        className="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-orange-50 px-2.5 py-1.5 text-xs font-semibold text-orange-600 ring-1 ring-orange-100 hover:bg-orange-100"
                                    >
                                        Visit
                                        <ChevronRight className="h-3.5 w-3.5" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}

                    <div className="mt-6 border-t pt-4">
                        <h3 className="font-semibold text-gray-900">Items</h3>
                        {order.items?.map((item) => (
                            <div key={item.id} className="mt-4 border-b border-gray-50 pb-4 last:border-0">
                                <div className="flex justify-between text-sm">
                                    <div className="min-w-0 flex-1">
                                        <span className="font-medium">{item.product_name} x {item.quantity}</span>
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
                                                {item.refund_status === 'not_applicable' && (
                                                    <p className="mt-1">
                                                        No automatic wallet refund for this payment type. Contact support if you need help.
                                                    </p>
                                                )}
                                                {item.refund_status === 'failed' && (
                                                    <p className="mt-1 font-medium text-red-700">
                                                        Refund failed — please contact CityShop support.
                                                    </p>
                                                )}
                                            </div>
                                        )}

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
                                                <p className="text-sm font-medium text-green-900">Has your item arrived?</p>
                                                <p className="mt-1 text-xs text-green-700">
                                                    Confirm delivery only after you have received the item in good condition.
                                                </p>
                                                <Button
                                                    type="button"
                                                    className="mt-3 w-full bg-green-600 hover:bg-green-700 sm:w-auto"
                                                    onClick={() => router.post(route('orders.confirm-delivery', [order.id, item.id]))}
                                                >
                                                    <CheckCircle2 className="mr-2 h-4 w-4" />
                                                    I received my item
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                    <span className="ml-4 shrink-0 font-medium">{formatPrice(item.unit_price * item.quantity)}</span>
                                </div>

                                {item.dispute && item.dispute.status !== 'cancelled' && (
                                    <RefundStatus dispute={item.dispute} />
                                )}

                                {item.status === 'delivered' && !reviews[item.product_id ?? 0] && (
                                    <ReviewForm orderId={order.id} item={item} />
                                )}

                                {reviews[item.product_id ?? 0] && (
                                    <p className="mt-2 flex items-center gap-1 text-sm text-gray-600">
                                        <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
                                        You rated {reviews[item.product_id ?? 0].rating}/5
                                    </p>
                                )}

                                {['shipped', 'awaiting_confirmation', 'delivered'].includes(item.status) && (!item.dispute || item.dispute.status === 'cancelled') && (
                                    <RefundRequestForm orderId={order.id} item={item} />
                                )}
                            </div>
                        ))}
                        <div className="mt-4 flex justify-between border-t pt-4 text-sm">
                            <span className="text-gray-600">Items</span>
                            <span>{formatPrice(order.subtotal)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Delivery</span>
                            <span>{formatPrice(order.shipping_cost)}</span>
                        </div>
                        <div className="mt-2 flex justify-between border-t pt-2 text-lg font-bold">
                            <span>Total</span>
                            <span className="text-orange-500">{formatPrice(order.total)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
