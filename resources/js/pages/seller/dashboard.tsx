import { Head } from '@inertiajs/react';
import { Clock, Package, ShoppingCart, Wallet } from 'lucide-react';

import StoreShareCard from '@/components/seller/store-share-card';
import PanelLayout from '@/layouts/panel-layout';
import { formatPrice, OrderItem, SellerProfile } from '@/types/marketplace';

interface DashboardProps {
    stats: {
        total_products: number;
        pending_products: number;
        total_orders: number;
        pending_orders: number;
        available_balance: number;
        pending_balance: number;
        total_earnings: number;
    };
    recentOrders: OrderItem[];
    profile: SellerProfile;
    storeUrl: string | null;
}

const sellerNav = [
    { label: 'Dashboard', href: route('seller.dashboard'), active: true },
    { label: 'Products', href: route('seller.products.index') },
    { label: 'Orders', href: route('seller.orders.index') },
    { label: 'Messages', href: route('chat.index') },
    { label: 'Wallet', href: route('seller.wallet') },
];

export default function SellerDashboard({ stats, recentOrders, profile, storeUrl }: DashboardProps) {
    const cards = [
        { label: 'Products', value: stats.total_products, icon: Package, color: 'text-blue-500' },
        { label: 'Pending Orders', value: stats.pending_orders, icon: ShoppingCart, color: 'text-orange-500' },
        { label: 'Available Balance', value: formatPrice(stats.available_balance), icon: Wallet, color: 'text-green-500' },
        { label: 'Pending Balance', value: formatPrice(stats.pending_balance), icon: Clock, color: 'text-yellow-500' },
    ];

    return (
        <PanelLayout title="Seller Dashboard" nav={sellerNav}>
            <Head title="Seller Dashboard" />
            <div className="mb-6 rounded-xl bg-gradient-to-r from-orange-500 to-blue-500 p-6 text-white">
                <h2 className="text-xl font-bold">{profile.business_name ?? profile.store_name}</h2>
                <p className="mt-1 text-sm opacity-90">Total earnings: {formatPrice(stats.total_earnings)}</p>
            </div>

            {storeUrl && profile.slug && (
                <div className="mb-6">
                    <StoreShareCard slug={profile.slug} storeName={profile.business_name ?? profile.store_name ?? 'My Store'} storeUrl={storeUrl} />
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {cards.map((card) => (
                    <div key={card.label} className="rounded-xl bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-gray-500">{card.label}</p>
                            <card.icon className={`h-5 w-5 ${card.color}`} />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-gray-900">{card.value}</p>
                    </div>
                ))}
            </div>

            <div className="mt-8 rounded-xl bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Recent Orders</h3>
                {recentOrders.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">No orders yet.</p>
                ) : (
                    <div className="mt-4 divide-y">
                        {recentOrders.map((item) => (
                            <div key={item.id} className="flex justify-between py-3 text-sm">
                                <div>
                                    <p className="font-medium">{item.product_name}</p>
                                    <p className="text-gray-500">Qty: {item.quantity}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-medium">{formatPrice(item.unit_price * item.quantity)}</p>
                                    <p className="capitalize text-gray-500">{item.status}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}
