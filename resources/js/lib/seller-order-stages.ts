import {
    Ban,
    CheckCircle2,
    Clock,
    Inbox,
    Package,
    PackageCheck,
    Truck,
    type LucideIcon,
} from 'lucide-react';

export type SellerOrderStageSlug =
    | 'new'
    | 'processing'
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
    icon: LucideIcon;
    accent: string;
    iconBg: string;
    ring: string;
}

export const sellerOrderStages: SellerOrderStage[] = [
    {
        slug: 'new',
        countKey: 'pending',
        label: 'New orders',
        headline: 'New orders',
        description: 'Paid orders waiting for you to start preparing.',
        emptyTitle: 'No new orders',
        emptyHint: 'New sales will land here as soon as buyers pay.',
        icon: Inbox,
        accent: 'text-violet-600',
        iconBg: 'bg-violet-100',
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
        icon: Clock,
        accent: 'text-blue-600',
        iconBg: 'bg-blue-100',
        ring: 'ring-blue-200',
    },
    {
        slug: 'packing',
        countKey: 'packed',
        label: 'Packing',
        headline: 'Packing',
        description: 'Items being packed and ready to go out.',
        emptyTitle: 'No orders in packing',
        emptyHint: 'Mark processing orders as packing when you pack them.',
        icon: Package,
        accent: 'text-amber-600',
        iconBg: 'bg-amber-100',
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
        icon: Truck,
        accent: 'text-orange-600',
        iconBg: 'bg-orange-100',
        ring: 'ring-orange-200',
    },
    {
        slug: 'awaiting',
        countKey: 'awaiting_confirmation',
        label: 'Awaiting buyer',
        headline: 'Awaiting buyer confirmation',
        description: 'You marked delivered — waiting for the buyer to confirm receipt.',
        emptyTitle: 'No orders awaiting confirmation',
        emptyHint: 'After you deliver, buyers confirm here before payout completes.',
        icon: PackageCheck,
        accent: 'text-cyan-600',
        iconBg: 'bg-cyan-100',
        ring: 'ring-cyan-200',
    },
    {
        slug: 'completed',
        countKey: 'delivered',
        label: 'Completed',
        headline: 'Completed orders',
        description: 'Buyer confirmed delivery. Funds released to your wallet.',
        emptyTitle: 'No completed orders yet',
        emptyHint: 'Finished orders appear here after buyer confirmation.',
        icon: CheckCircle2,
        accent: 'text-emerald-600',
        iconBg: 'bg-emerald-100',
        ring: 'ring-emerald-200',
    },
];

export const sellerOrderCancelledStage: SellerOrderStage = {
    slug: 'cancelled',
    countKey: 'cancelled',
    label: 'Cancelled',
    headline: 'Cancelled & refunded',
    description: 'Rejected or refunded orders.',
    emptyTitle: 'No cancelled orders',
    emptyHint: 'Cancelled and refunded orders are kept here for your records.',
    icon: Ban,
    accent: 'text-gray-600',
    iconBg: 'bg-gray-100',
    ring: 'ring-gray-200',
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
