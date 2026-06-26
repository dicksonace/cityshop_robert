import { Head, Link } from '@inertiajs/react';
import { DollarSign, Package, ShoppingCart, Store, Users } from 'lucide-react';

import PanelLayout from '@/layouts/panel-layout';
import { formatPrice, Order, SellerProfile } from '@/types/marketplace';

interface AdminDashboardProps {
    stats: {
        total_users: number;
        total_sellers: number;
        pending_sellers: number;
        total_products: number;
        pending_products: number;
        total_orders: number;
        total_revenue: number;
        total_commission: number;
        pending_withdrawals: number;
    };
    recentOrders: (Order & { buyer: { name: string } })[];
    pendingSellers: (SellerProfile & { user: { name: string; email: string } })[];
}

const nav = [
    { label: 'Dashboard', href: route('admin.dashboard'), active: true },
    { label: 'Sellers', href: route('admin.sellers.index') },
    { label: 'Invites', href: route('admin.seller-invites.index') },
    { label: 'Products', href: route('admin.products.index') },
    { label: 'Orders', href: route('admin.orders.index') },
    { label: 'Withdrawals', href: route('admin.withdrawals.index') },
    { label: 'Disputes', href: route('admin.disputes.index') },
    { label: 'Messages', href: route('admin.contact-messages.index') },
];

export default function AdminDashboard({ stats, recentOrders, pendingSellers }: AdminDashboardProps) {
    const cards = [
        { label: 'Total Revenue', value: formatPrice(stats.total_revenue), icon: DollarSign, color: 'text-green-500' },
        { label: 'Commission', value: formatPrice(stats.total_commission), icon: DollarSign, color: 'text-blue-500' },
        { label: 'Orders', value: stats.total_orders, icon: ShoppingCart, color: 'text-orange-500' },
        { label: 'Sellers', value: stats.total_sellers, icon: Store, color: 'text-purple-500' },
        { label: 'Users', value: stats.total_users, icon: Users, color: 'text-gray-500' },
        { label: 'Pending Sellers', value: stats.pending_sellers, icon: Store, color: 'text-yellow-500' },
        { label: 'Pending Products', value: stats.pending_products, icon: Package, color: 'text-yellow-500' },
        { label: 'Pending Payouts', value: stats.pending_withdrawals, icon: DollarSign, color: 'text-red-500' },
    ];

    return (
        <PanelLayout title="Admin Panel" nav={nav}>
            <Head title="Admin Dashboard" />
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

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Pending Seller Approvals</h3>
                        <Link href={route('admin.sellers.index')} className="text-sm text-orange-500">View all</Link>
                    </div>
                    {pendingSellers.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No pending applications.</p>
                    ) : (
                        <div className="mt-4 divide-y">
                            {pendingSellers.map((seller) => (
                                <Link key={seller.id} href={route('admin.sellers.show', seller.id)} className="flex justify-between py-3 text-sm hover:bg-gray-50">
                                    <div>
                                        <p className="font-medium">{seller.business_name ?? seller.store_name}</p>
                                        <p className="text-gray-500">{seller.user.email}</p>
                                    </div>
                                    <span className="text-orange-500">Review</span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Recent Orders</h3>
                        <Link href={route('admin.orders.index')} className="text-sm text-orange-500">View all</Link>
                    </div>
                    {recentOrders.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No orders yet.</p>
                    ) : (
                        <div className="mt-4 divide-y">
                            {recentOrders.map((order) => (
                                <div key={order.id} className="flex justify-between py-3 text-sm">
                                    <div>
                                        <p className="font-medium">{order.order_number}</p>
                                        <p className="text-gray-500">{order.buyer?.name}</p>
                                    </div>
                                    <p className="font-medium text-orange-500">{formatPrice(order.total)}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </PanelLayout>
    );
}
