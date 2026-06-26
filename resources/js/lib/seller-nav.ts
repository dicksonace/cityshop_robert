export function sellerNav(active: string) {
    return [
        { label: 'Dashboard', href: route('seller.dashboard'), active: active === 'dashboard' },
        { label: 'Customize Store', href: route('seller.store-appearance.index'), active: active === 'appearance' },
        { label: 'Products', href: route('seller.products.index'), active: active === 'products' },
        { label: 'Orders', href: route('seller.orders.index'), active: active === 'orders' },
        { label: 'Payment Methods', href: route('seller.payment-methods.index'), active: active === 'payment-methods' },
        { label: 'Messages', href: route('chat.index'), active: active === 'messages' },
        { label: 'Wallet', href: route('seller.wallet'), active: active === 'wallet' },
    ];
}
