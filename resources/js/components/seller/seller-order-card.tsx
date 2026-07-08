import { router } from '@inertiajs/react';
import { MapPin, User } from 'lucide-react';

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
        payment_channel?: string;
        direct_payment_reference?: string | null;
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

export default function SellerOrderCard({ item, stageSlug }: SellerOrderCardProps) {
    const image = item.product?.images?.[0];
    const order = item.order;
    const stage = stageSlug ? getSellerOrderStage(stageSlug) : undefined;
    const needsPaymentConfirm = order.payment_channel === 'direct' && order.payment_status === 'pending';

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
                        <span
                            className={cn(
                                'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                stage ? `${stage.iconBg} ${stage.accent}` : 'bg-gray-100 text-gray-600',
                            )}
                        >
                            {formatOrderStatus(item.status)}
                        </span>
                    </div>
                    <p className="mt-1 text-xs text-gray-400">{order.order_number}</p>
                    <p className="mt-2 text-lg font-bold text-orange-500">{formatPrice(item.unit_price * item.quantity)}</p>
                    <p className="text-xs text-gray-400">Qty {item.quantity} · {timeAgo(order.created_at)}</p>
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

                <div className="mt-auto flex gap-2 pt-2">
                    <Button
                        variant="outline"
                        size="sm"
                        className="flex-1 border-orange-200 text-orange-600 hover:bg-orange-50"
                        onClick={() => router.visit(route('seller.orders.show', item.id))}
                    >
                        Manage order
                    </Button>
                    {needsPaymentConfirm && (
                        <Button
                            size="sm"
                            className="shrink-0 bg-emerald-600 hover:bg-emerald-700"
                            onClick={() => router.post(route('seller.orders.confirm-direct-payment', order.id))}
                        >
                            Confirm pay
                        </Button>
                    )}
                </div>
            </div>
        </article>
    );
}
