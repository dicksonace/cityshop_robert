export type AdminNavKey =
    | 'dashboard'
    | 'sellers'
    | 'invites'
    | 'products'
    | 'orders'
    | 'withdrawals'
    | 'disputes'
    | 'messages';

export function adminNav(active: AdminNavKey) {
    return [
        { label: 'Dashboard', href: route('admin.dashboard'), active: active === 'dashboard' },
        { label: 'Sellers', href: route('admin.sellers.index'), active: active === 'sellers' },
        { label: 'Invites', href: route('admin.seller-invites.index'), active: active === 'invites' },
        { label: 'Products', href: route('admin.products.index'), active: active === 'products' },
        { label: 'Orders', href: route('admin.orders.index'), active: active === 'orders' },
        { label: 'Withdrawals', href: route('admin.withdrawals.index'), active: active === 'withdrawals' },
        { label: 'Disputes', href: route('admin.disputes.index'), active: active === 'disputes' },
        { label: 'Messages', href: route('admin.contact-messages.index'), active: active === 'messages' },
    ];
}
