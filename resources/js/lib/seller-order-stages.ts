import {
    Ban,
    CheckCircle2,
    Clock,
    Inbox,
    Package,
    PackageCheck,
    Phone,
    Truck,
    type LucideIcon,
} from 'lucide-react';

export type SellerOrderStageSlug =
    | 'new'
    | 'processing'
    | 'call'
    | 'packing'
    | 'delivery'
    | 'awaiting'
    | 'completed'
    | 'cancelled'
    | 'all';

export interface SellerOrderStage {
    slug: SellerOrderStageSlug;
    countKey: string;
    label: string;
    headline: string;
    description: string;
    emptyTitle: string;
    emptyHint: string;
    cta: string;
    icon: LucideIcon;
    accent: string;
    iconBg: string;
    cardBg: string;
    ring: string;
}

export const sellerOrderStages: SellerOrderStage[] = [
    {
        slug: 'new',
        countKey: 'pending',
        label: 'New orders',
        headline: 'New orders',
        description: 'New sales waiting for you — including cash on delivery.',
        emptyTitle: 'No new orders',
        emptyHint: 'New sales will land here when buyers order or pay.',
        cta: 'Review now',
        icon: Inbox,
        accent: 'text-violet-700',
        iconBg: 'bg-violet-100 text-violet-600',
        cardBg: 'border-violet-100 bg-gradient-to-br from-violet-50 to-white',
        ring: 'ring-violet-200',
    },
    {
        slug: 'processing',
        countKey: 'processing',
        label: 'Processing',
        headline: 'Processing',
        description: 'Orders you are preparing before packing.',
        emptyTitle: 'Nothing in processing',
        emptyHint: 'Move new orders here when you start working on them.',
        cta: 'Process now',
        icon: Clock,
        accent: 'text-blue-700',
        iconBg: 'bg-blue-100 text-blue-600',
        cardBg: 'border-blue-100 bg-gradient-to-br from-blue-50 to-white',
        ring: 'ring-blue-200',
    },
    {
        slug: 'call',
        countKey: 'call_confirmed',
        label: 'Call buyer',
        headline: 'Call buyer (COD)',
        description: 'Cash-on-delivery orders — call the buyer to confirm, then pack.',
        emptyTitle: 'No COD calls pending',
        emptyHint: 'After you start a cash-on-delivery order, mark that you called the buyer here.',
        cta: 'Open calls',
        icon: Phone,
        accent: 'text-fuchsia-700',
        iconBg: 'bg-fuchsia-100 text-fuchsia-600',
        cardBg: 'border-fuchsia-100 bg-gradient-to-br from-fuchsia-50 to-white',
        ring: 'ring-fuchsia-200',
    },
    {
        slug: 'packing',
        countKey: 'packed',
        label: 'Packing',
        headline: 'Packing',
        description: 'Items being packed and ready to go out.',
        emptyTitle: 'No orders in packing',
        emptyHint: 'Mark processing orders as packing when you pack them.',
        cta: 'Pack now',
        icon: Package,
        accent: 'text-amber-700',
        iconBg: 'bg-amber-100 text-amber-600',
        cardBg: 'border-amber-100 bg-gradient-to-br from-amber-50 to-white',
        ring: 'ring-amber-200',
    },
    {
        slug: 'delivery',
        countKey: 'shipped',
        label: 'Out for delivery',
        headline: 'Out for delivery',
        description: 'Orders on the way with driver and vehicle details shared.',
        emptyTitle: 'Nothing out for delivery',
        emptyHint: 'Send packed orders with driver info when they leave your shop.',
        cta: 'Track now',
        icon: Truck,
        accent: 'text-orange-700',
        iconBg: 'bg-orange-100 text-orange-600',
        cardBg: 'border-orange-100 bg-gradient-to-br from-orange-50 to-white',
        ring: 'ring-orange-200',
    },
    {
        slug: 'awaiting',
        countKey: 'awaiting_confirmation',
        label: 'Awaiting buyer',
        headline: 'Awaiting buyer confirmation',
        description: 'You marked delivered — waiting for the buyer to confirm receipt.',
        emptyTitle: 'No orders awaiting confirmation',
        emptyHint: 'After you deliver, buyers confirm here. Admin or buyer confirm can release Pending funds.',
        cta: 'View now',
        icon: PackageCheck,
        accent: 'text-cyan-700',
        iconBg: 'bg-cyan-100 text-cyan-600',
        cardBg: 'border-cyan-100 bg-gradient-to-br from-cyan-50 to-white',
        ring: 'ring-cyan-200',
    },
    {
        slug: 'completed',
        countKey: 'delivered',
        label: 'Completed',
        headline: 'Completed orders',
        description: 'Buyer confirmed delivery. Funds are released to Available by admin or on buyer confirm.',
        emptyTitle: 'No completed orders yet',
        emptyHint: 'Finished orders appear here after buyer confirmation.',
        cta: 'Open history',
        icon: CheckCircle2,
        accent: 'text-emerald-700',
        iconBg: 'bg-emerald-100 text-emerald-600',
        cardBg: 'border-emerald-100 bg-gradient-to-br from-emerald-50 to-white',
        ring: 'ring-emerald-200',
    },
];

export const sellerOrderCancelledStage: SellerOrderStage = {
    slug: 'cancelled',
    countKey: 'cancelled',
    label: 'Cancelled orders',
    headline: 'Cancelled & refunded',
    description: 'Rejected or refunded orders.',
    emptyTitle: 'No cancelled orders',
    emptyHint: 'Cancelled and refunded orders are kept here for your records.',
    cta: 'View cancelled',
    icon: Ban,
    accent: 'text-slate-700',
    iconBg: 'bg-slate-100 text-slate-600',
    cardBg: 'border-slate-100 bg-gradient-to-br from-slate-50 to-white',
    ring: 'ring-slate-200',
};

export function getSellerOrderStage(slug: string): SellerOrderStage | undefined {
    if (slug === 'cancelled') {
        return sellerOrderCancelledStage;
    }
    return sellerOrderStages.find((s) => s.slug === slug);
}

export function sellerOrdersStageHref(slug: SellerOrderStageSlug): string {
    if (slug === 'all') {
        return route('seller.orders.index');
    }
    return route('seller.orders.stage', slug);
}
