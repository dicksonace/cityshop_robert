import {
    LayoutDashboard,
    MessageSquare,
    Package,
    ShoppingCart,
    Star,
    Store,
    Tag,
    Wallet,
} from 'lucide-react';

import { PanelNavGroup } from '@/lib/panel-nav-types';
import { sellerOrdersStageHref } from '@/lib/seller-order-stages';

export type SellerNavKey =
    | 'dashboard'
    | 'appearance'
    | 'products'
    | 'orders'
    | 'payment-methods'
    | 'promotions'
    | 'reviews'
    | 'messages'
    | 'notifications'
    | 'wallet';

const sectionMap: Record<SellerNavKey, string> = {
    dashboard: 'dashboard',
    appearance: 'store',
    products: 'products',
    orders: 'orders',
    'payment-methods': 'finance',
    promotions: 'marketing',
    reviews: 'customers',
    messages: 'communication',
    notifications: 'communication',
    wallet: 'finance',
};

export function sellerNavSection(active: SellerNavKey): string {
    return sectionMap[active];
}

export function sellerNavGroups(active: SellerNavKey): PanelNavGroup[] {
    const section = sellerNavSection(active);

    return [
        {
            key: 'dashboard',
            label: 'Dashboard',
            icon: LayoutDashboard,
            defaultOpen: true,
            items: [{ key: 'overview', label: 'Overview', href: route('seller.dashboard'), mobile: true }],
        },
        {
            key: 'store',
            label: 'Store',
            icon: Store,
            defaultOpen: section === 'store',
            items: [{ key: 'appearance', label: 'Customize Store', href: route('seller.store-appearance.index') }],
        },
        {
            key: 'products',
            label: 'Products',
            icon: Package,
            defaultOpen: section === 'products',
            items: [
                { key: 'products-all', label: 'All Products', href: route('seller.products.index'), mobile: true, defaultOnPath: true },
                { key: 'products-add', label: 'Add Product', href: route('seller.products.create') },
                { key: 'products-live', label: 'Active Products', href: route('seller.products.index', { status: 'approved' }) },
                { key: 'products-draft', label: 'Hidden / Draft', href: route('seller.products.index', { status: 'draft' }) },
                { key: 'products-sold-out', label: 'Out of Stock', href: route('seller.products.index', { status: 'sold_out' }) },
                { key: 'products-deleted', label: 'Deleted', href: route('seller.products.index', { status: 'deleted' }) },
            ],
        },
        {
            key: 'orders',
            label: 'Sales',
            icon: ShoppingCart,
            defaultOpen: section === 'orders',
            items: [
                { key: 'orders-hub', label: 'Sales center', href: route('seller.orders.index'), mobile: true, defaultOnPath: true },
                { key: 'orders-new', label: 'New orders', href: sellerOrdersStageHref('new'), badgeKey: 'pending_orders' },
                { key: 'orders-processing', label: 'Processing', href: sellerOrdersStageHref('processing'), badgeKey: 'processing_orders' },
                { key: 'orders-call', label: 'Call buyer', href: sellerOrdersStageHref('call'), badgeKey: 'call_orders' },
                { key: 'orders-packing', label: 'Packing', href: sellerOrdersStageHref('packing'), badgeKey: 'packing_orders' },
                { key: 'orders-delivery', label: 'Out for delivery', href: sellerOrdersStageHref('delivery'), badgeKey: 'delivery_orders' },
                { key: 'orders-awaiting', label: 'Awaiting buyer', href: sellerOrdersStageHref('awaiting'), badgeKey: 'awaiting_orders' },
                { key: 'orders-completed', label: 'Completed', href: sellerOrdersStageHref('completed') },
                { key: 'orders-cancelled', label: 'Cancelled orders', href: sellerOrdersStageHref('cancelled'), badgeKey: 'cancelled_orders' },
                { key: 'orders-refunds', label: 'Refund requests', href: route('seller.refunds.index'), badgeKey: 'open_refunds' },
            ],
        },
        {
            key: 'marketing',
            label: 'Marketing',
            icon: Tag,
            defaultOpen: section === 'marketing',
            items: [{ key: 'promotions', label: 'Coupons & Promotions', href: route('seller.promotions.index') }],
        },
        {
            key: 'customers',
            label: 'Customers',
            icon: Star,
            defaultOpen: section === 'customers',
            items: [{ key: 'reviews', label: 'Product Reviews', href: route('seller.reviews.index') }],
        },
        {
            key: 'communication',
            label: 'Communication',
            icon: MessageSquare,
            defaultOpen: section === 'communication',
            items: [
                { key: 'messages', label: 'Inbox', href: route('chat.index'), badgeKey: 'unread_messages', mobile: true },
                { key: 'notifications', label: 'Notifications', href: route('notifications.index'), badgeKey: 'unread_notifications' },
            ],
        },
        {
            key: 'finance',
            label: 'Finance',
            icon: Wallet,
            defaultOpen: section === 'finance',
            items: [
                { key: 'wallet', label: 'Withdraw to MoMo', href: route('seller.wallet'), mobile: true },
                { key: 'payment-methods', label: 'Payment Methods', href: route('seller.payment-methods.index') },
            ],
        },
    ];
}

export function sellerMobileNavItems(active: SellerNavKey) {
    return sellerNavGroups(active)
        .flatMap((group) => group.items)
        .filter((item) => item.mobile);
}
