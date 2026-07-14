import {
    LayoutDashboard,
    MessageSquare,
    Package,
    ShoppingCart,
    Store,
    Users,
    Wallet,
} from 'lucide-react';

import { PanelNavGroup } from '@/lib/panel-nav-types';

export type AdminNavKey =
    | 'dashboard'
    | 'stores'
    | 'sellers'
    | 'invites'
    | 'buyers'
    | 'products'
    | 'categories'
    | 'orders'
    | 'withdrawals'
    | 'wallet-funding'
    | 'manual-funding-settings'
    | 'manual-top-ups'
    | 'disputes'
    | 'messages'
    | 'chats'
    | 'seller-reports';

const sectionMap: Record<AdminNavKey, string> = {
    dashboard: 'dashboard',
    stores: 'marketplace',
    sellers: 'marketplace',
    invites: 'marketplace',
    buyers: 'users',
    products: 'catalog',
    categories: 'catalog',
    orders: 'orders',
    withdrawals: 'finance',
    'wallet-funding': 'finance',
    'manual-funding-settings': 'finance',
    'manual-top-ups': 'finance',
    disputes: 'support',
    messages: 'support',
    chats: 'support',
    'seller-reports': 'support',
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
            key: 'users',
            label: 'Buyers',
            icon: Users,
            defaultOpen: section === 'users',
            items: [{ key: 'buyers', label: 'All Buyers', href: route('admin.buyers.index') }],
        },
        {
            key: 'catalog',
            label: 'Products',
            icon: Package,
            defaultOpen: section === 'catalog',
            items: [
                { key: 'products-all', label: 'All Products', href: route('admin.products.index', { status: 'all' }) },
                { key: 'products-approved', label: 'Live Products', href: route('admin.products.index', { status: 'approved' }) },
                { key: 'products-pending', label: 'Pending Review', href: route('admin.products.index', { status: 'pending' }), badgeKey: 'pending_products' },
                { key: 'products-rejected', label: 'Rejected', href: route('admin.products.index', { status: 'rejected' }) },
                { key: 'categories', label: 'Categories', href: route('admin.categories.index'), defaultOnPath: true },
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
                { key: 'withdrawals-sellers', label: 'Seller Payouts', href: route('admin.withdrawals.index', { status: 'pending', role: 'seller' }), badgeKey: 'pending_seller_withdrawals' },
                { key: 'withdrawals-buyers', label: 'Buyer Withdrawals', href: route('admin.withdrawals.index', { status: 'pending', role: 'buyer' }) },
                { key: 'withdrawals-all', label: 'All Withdrawals', href: route('admin.withdrawals.index', { status: 'all' }) },
                { key: 'manual-top-ups', label: 'Manual Top-ups', href: route('admin.manual-top-ups.index'), badgeKey: 'pending_manual_top_ups', defaultOnPath: true },
                { key: 'manual-funding-settings', label: 'Receive Accounts', href: route('admin.manual-funding.settings') },
                { key: 'wallet-funding', label: 'Add Funds to Wallet', href: route('admin.wallet-funding.index'), defaultOnPath: true },
            ],
        },
        {
            key: 'support',
            label: 'Support',
            icon: MessageSquare,
            defaultOpen: section === 'support',
            items: [
                { key: 'disputes-open', label: 'Refund requests', href: route('admin.disputes.index', { status: 'open' }), badgeKey: 'open_disputes', defaultOnPath: true },
                { key: 'disputes-all', label: 'All refund requests', href: route('admin.disputes.index', { status: 'all' }) },
                { key: 'chats', label: 'Buyer–Seller Chats', href: route('admin.chats.index') },
                { key: 'seller-reports', label: 'Seller Reports', href: route('admin.seller-reports.index'), badgeKey: 'open_seller_reports' },
                { key: 'messages', label: 'Contact Messages', href: route('admin.contact-messages.index'), badgeKey: 'unread_messages' },
            ],
        },
    ];
}
