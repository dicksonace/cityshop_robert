import { Link, router } from '@inertiajs/react';
import { Download, MapPin, Printer, User } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { getSellerOrderStage } from '@/lib/seller-order-stages';
import { formatOrderStatus, formatPrice, OrderItem, productImageUrl } from '@/types/marketplace';

export type SellerOrderListItem = OrderItem & {
    order: {
        id: number;
        order_number: string;
        created_at: string;
        payment_status?: string;
        payment_method?: string | null;
        payment_channel?: string;
        direct_payment_reference?: string | null;
        direct_payment_proof_path?: string | null;
        receiver_name?: string;
        receiver_phone?: string;
        city?: string;
        region?: string;
        buyer?: { name: string };
    };
    product?: { images?: { path: string }[] };
};

interface SellerOrderCardProps {
    item: SellerOrderListItem;
    stageSlug?: string;
}

function timeAgo(date: string): string {
    const diff = Date.now() - new Date(date).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
}

const SELLER_CANCELLABLE = new Set(['pending', 'processing', 'call_confirmed', 'packed']);

export default function SellerOrderCard({ item, stageSlug }: SellerOrderCardProps) {
    const image = item.product?.images?.[0];
    const order = item.order;
    const stage = stageSlug ? getSellerOrderStage(stageSlug) : undefined;
    const isCod = order.payment_method === 'cash';
    const hasPaymentClaim = Boolean(order.direct_payment_reference || order.direct_payment_proof_path);
    const needsPaymentReview =
        order.payment_channel === 'direct' && order.payment_status === 'pending' && hasPaymentClaim;
    const canCancel = SELLER_CANCELLABLE.has(String(item.status));
    const statusLabel = formatOrderStatus(item.status);
    const isDirect = order.payment_channel === 'direct';
    const isPaid = order.payment_status === 'paid';

    const paymentLabel = isCod
        ? null
        : isDirect
            ? isPaid
                ? 'Paid to seller'
                : 'Pay to seller'
            : isPaid
                ? 'Paid · CityShop secured'
                : 'CityShop secured';

    const rejectPayment = () => {
        const reason = window.prompt(
            'Reject this customer payment claim?\nExplain why (e.g. money not received, wrong amount):',
        );
        if (reason === null) return;
        const trimmed = reason.trim();
        if (trimmed.length < 5) {
            window.alert('Please enter a short reason (at least 5 characters).');
            return;
        }
        router.post(route('seller.orders.reject-direct-payment', order.id), { reason: trimmed });
    };

    return (
        <article className="flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-orange-200 hover:shadow-md">
            <div className="flex gap-3 border-b border-gray-50 p-4">
                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-gray-50 to-white p-2 ring-1 ring-gray-100">
                    {image ? (
                        <img src={productImageUrl(image.path)} alt="" className="max-h-full max-w-full object-contain" />
                    ) : (
                        <span className="text-xs text-gray-400">No image</span>
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <p className="line-clamp-2 font-semibold text-gray-900">{item.product_name}</p>
                        <div className="flex shrink-0 flex-col items-end gap-1">
                            {isCod && (
                                <span className="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-800">
                                    Cash on delivery
                                </span>
                            )}
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                    stage ? `${stage.iconBg} ${stage.accent}` : 'bg-gray-100 text-gray-600',
                                )}
                            >
                                {statusLabel}
                            </span>
                        </div>
                    </div>
                    <p className="mt-1 text-xs text-gray-400">{order.order_number}</p>
                    <p className="mt-2 text-lg font-bold text-orange-500">{formatPrice(item.unit_price * item.quantity)}</p>
                    <p className="text-xs text-gray-400">Qty {item.quantity} · {timeAgo(order.created_at)}</p>
                    {paymentLabel && (
                        <p
                            className={cn(
                                'mt-1.5 inline-flex max-w-full rounded-md px-2 py-0.5 text-[11px] font-semibold leading-snug',
                                isDirect
                                    ? 'bg-amber-50 text-amber-800'
                                    : 'bg-emerald-50 text-emerald-700',
                            )}
                        >
                            {paymentLabel}
                        </p>
                    )}
                </div>
            </div>

            <div className="flex flex-1 flex-col gap-2 p-4 text-sm">
                <div className="flex items-center gap-2 text-gray-600">
                    <User className="h-4 w-4 shrink-0 text-gray-400" />
                    <span className="truncate font-medium">{order.buyer?.name ?? order.receiver_name ?? 'Buyer'}</span>
                </div>
                {(order.city || order.region) && (
                    <div className="flex items-center gap-2 text-gray-500">
                        <MapPin className="h-4 w-4 shrink-0 text-gray-400" />
                        <span className="truncate">{[order.city, order.region].filter(Boolean).join(', ')}</span>
                    </div>
                )}
                {(item.driver_phone || item.vehicle_number) && (
                    <p className="rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs text-blue-800">
                        {item.driver_phone && <>Driver {item.driver_phone}</>}
                        {item.vehicle_number && <> · {item.vehicle_number}</>}
                    </p>
                )}
                {needsPaymentReview && (
                    <div className="space-y-2 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2">
                        <p className="text-xs text-amber-800">
                            Customer submitted a manual payment claim — confirm only if you received the money.
                        </p>
                        {order.direct_payment_reference && (
                            <div>
                                <p className="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Transaction ID</p>
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
                                className="block"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <img
                                    src={productImageUrl(order.direct_payment_proof_path)}
                                    alt="Payment screenshot"
                                    className="max-h-36 w-full rounded-lg border border-amber-200 object-contain bg-white"
                                />
                                <p className="mt-1 text-xs font-medium text-orange-600 hover:underline">View payment screenshot</p>
                            </a>
                        )}
                    </div>
                )}

                <div className="mt-auto flex flex-wrap gap-2 pt-2">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="min-w-0 flex-1 border-orange-200 text-orange-600 hover:bg-orange-50"
                    >
                        <Link href={route('seller.orders.show', item.id)}>Manage order</Link>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="shrink-0 border-gray-200 text-gray-700 hover:bg-gray-50"
                        title="Print packing slip"
                    >
                        <a href={route('seller.orders.print', item.id)} target="_blank" rel="noreferrer">
                            <Printer className="h-4 w-4" />
                            <span className="sr-only">Print</span>
                        </a>
                    </Button>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="shrink-0 border-gray-200 text-gray-700 hover:bg-gray-50"
                        title="Download PDF"
                    >
                        <a href={route('seller.orders.pdf', item.id)}>
                            <Download className="h-4 w-4" />
                            <span className="sr-only">PDF</span>
                        </a>
                    </Button>
                    {canCancel && (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="shrink-0 border-red-200 text-red-600 hover:bg-red-50"
                        >
                            <Link href={`${route('seller.orders.show', item.id)}?cancel=1`}>Cancel</Link>
                        </Button>
                    )}
                    {needsPaymentReview && (
                        <>
                            <Button
                                size="sm"
                                className="shrink-0 bg-emerald-600 hover:bg-emerald-700"
                                onClick={() => router.post(route('seller.orders.confirm-direct-payment', order.id))}
                            >
                                Confirm
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                className="shrink-0 border-red-200 text-red-600 hover:bg-red-50"
                                onClick={rejectPayment}
                            >
                                Reject
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </article>
    );
}
