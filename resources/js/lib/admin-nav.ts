import {
    LayoutDashboard,
    MessageSquare,
    Package,
    ShoppingCart,
    Store,
    Wallet,
} from 'lucide-react';

import { PanelNavGroup } from '@/lib/panel-nav-types';

export type AdminNavKey =
    | 'dashboard'
    | 'stores'
    | 'sellers'
    | 'invites'
    | 'products'
    | 'orders'
    | 'withdrawals'
    | 'disputes'
    | 'messages';

const sectionMap: Record<AdminNavKey, string> = {
    dashboard: 'dashboard',
    stores: 'marketplace',
    sellers: 'marketplace',
    invites: 'marketplace',
    products: 'catalog',
    orders: 'orders',
    withdrawals: 'finance',
    disputes: 'support',
    messages: 'support',
};

export function adminNavSection(active: AdminNavKey): string {
    return sectionMap[active];
}

export function adminNavGroups(active: AdminNavKey): PanelNavGroup[] {
    const section = adminNavSection(active);

    return [
        {
            key: 'dashboard',
            label: 'Dashboard',
            icon: LayoutDashboard,
            defaultOpen: true,
            items: [{ key: 'overview', label: 'Overview', href: route('admin.dashboard') }],
        },
        {
            key: 'marketplace',
            label: 'Marketplace',
            icon: Store,
            defaultOpen: section === 'marketplace',
            items: [
                { key: 'stores', label: 'Store Oversight', href: route('admin.stores.index') },
                { key: 'sellers-all', label: 'All Sellers', href: route('admin.sellers.index', { status: 'all' }) },
                { key: 'sellers-pending', label: 'Pending Approval', href: route('admin.sellers.index', { status: 'pending' }), badgeKey: 'pending_sellers', defaultOnPath: true },
                { key: 'sellers-approved', label: 'Verified Sellers', href: route('admin.sellers.index', { status: 'approved' }) },
                { key: 'sellers-rejected', label: 'Rejected', href: route('admin.sellers.index', { status: 'rejected' }) },
                { key: 'invites', label: 'Registration Invites', href: route('admin.seller-invites.index') },
            ],
        },
        {
            key: 'catalog',
            label: 'Products',
            icon: Package,
            defaultOpen: section === 'catalog',
            items: [
                { key: 'products-all', label: 'All Products', href: route('admin.products.index', { status: 'all' }) },
                { key: 'products-pending', label: 'Pending Approval', href: route('admin.products.index', { status: 'pending' }), badgeKey: 'pending_products', defaultOnPath: true },
                { key: 'products-approved', label: 'Approved', href: route('admin.products.index', { status: 'approved' }) },
                { key: 'products-rejected', label: 'Rejected', href: route('admin.products.index', { status: 'rejected' }) },
            ],
        },
        {
            key: 'orders',
            label: 'Orders',
            icon: ShoppingCart,
            defaultOpen: section === 'orders',
            items: [{ key: 'orders-all', label: 'All Orders', href: route('admin.orders.index') }],
        },
        {
            key: 'finance',
            label: 'Finance',
            icon: Wallet,
            defaultOpen: section === 'finance',
            items: [
                { key: 'withdrawals-pending', label: 'Withdrawal Requests', href: route('admin.withdrawals.index', { status: 'pending' }), badgeKey: 'pending_withdrawals', defaultOnPath: true },
                { key: 'withdrawals-all', label: 'All Withdrawals', href: route('admin.withdrawals.index', { status: 'all' }) },
            ],
        },
        {
            key: 'support',
            label: 'Support',
            icon: MessageSquare,
            defaultOpen: section === 'support',
            items: [
                { key: 'disputes-open', label: 'Disputes', href: route('admin.disputes.index', { status: 'open' }), badgeKey: 'open_disputes', defaultOnPath: true },
                { key: 'disputes-all', label: 'All Disputes', href: route('admin.disputes.index', { status: 'all' }) },
                { key: 'messages', label: 'Contact Messages', href: route('admin.contact-messages.index'), badgeKey: 'unread_messages' },
            ],
        },
    ];
}
