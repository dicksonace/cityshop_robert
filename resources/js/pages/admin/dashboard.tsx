import { Head, Link } from '@inertiajs/react';
import { DollarSign, ShoppingCart, Store, Users } from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Order, SellerProfile } from '@/types/marketplace';

interface PendingWithdrawalRow {
    id: number;
    amount: number;
    network: string | null;
    momo_number: string | null;
    account_name: string | null;
    created_at: string | null;
    user: { name: string; email: string; role?: string } | null;
}

interface AdminDashboardProps {
    stats: {
        total_users: number;
        total_sellers: number;
        pending_sellers: number;
        total_products: number;
        total_orders: number;
        total_revenue: number;
        pending_withdrawals: number;
    };
    recentOrders: (Order & { buyer: { name: string } })[];
    pendingSellers: (SellerProfile & { user: { name: string; email: string } })[];
    pendingWithdrawals: PendingWithdrawalRow[];
}

export default function AdminDashboard({
    stats,
    recentOrders,
    pendingSellers,
    pendingWithdrawals = [],
}: AdminDashboardProps) {
    const cards: {
        label: string;
        value: string | number;
        icon: typeof DollarSign;
        color: string;
        href?: string;
    }[] = [
        { label: 'Total Revenue', value: formatPrice(stats.total_revenue), icon: DollarSign, color: 'text-green-500' },
        { label: 'Orders', value: stats.total_orders, icon: ShoppingCart, color: 'text-orange-500', href: route('admin.orders.index') },
        { label: 'Sellers', value: stats.total_sellers, icon: Store, color: 'text-purple-500', href: route('admin.sellers.index', { status: 'all' }) },
        { label: 'Users', value: stats.total_users, icon: Users, color: 'text-gray-500', href: route('admin.buyers.index') },
        {
            label: 'Pending Sellers',
            value: stats.pending_sellers,
            icon: Store,
            color: 'text-yellow-500',
            href: route('admin.sellers.index', { status: 'pending' }),
        },
        {
            label: 'Pending Payouts',
            value: stats.pending_withdrawals,
            icon: DollarSign,
            color: 'text-red-500',
            href: route('admin.withdrawals.index', { status: 'pending' }),
        },
    ];

    return (
        <AdminLayout title="Admin Panel" active="dashboard">
            <Head title="Admin Dashboard" />
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {cards.map((card) => {
                    const inner = (
                        <>
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-500">{card.label}</p>
                                <card.icon className={`h-5 w-5 ${card.color}`} />
                            </div>
                            <p className="mt-2 text-2xl font-bold text-gray-900">{card.value}</p>
                            {card.href && (
                                <p className="mt-2 text-xs font-medium text-orange-600">Open →</p>
                            )}
                        </>
                    );

                    return card.href ? (
                        <Link
                            key={card.label}
                            href={card.href}
                            className="rounded-xl bg-white p-5 shadow-sm transition hover:ring-2 hover:ring-orange-200"
                        >
                            {inner}
                        </Link>
                    ) : (
                        <div key={card.label} className="rounded-xl bg-white p-5 shadow-sm">
                            {inner}
                        </div>
                    );
                })}
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Pending Seller Approvals</h3>
                        <Link
                            href={route('admin.sellers.index', { status: 'pending' })}
                            className="text-sm text-orange-500 hover:underline"
                        >
                            View all / Approve
                        </Link>
                    </div>
                    {pendingSellers.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No pending applications.</p>
                    ) : (
                        <div className="mt-4 divide-y">
                            {pendingSellers.map((seller) => (
                                <Link
                                    key={seller.id}
                                    href={route('admin.sellers.show', seller.id)}
                                    className="flex justify-between py-3 text-sm hover:bg-gray-50"
                                >
                                    <div>
                                        <p className="font-medium">{seller.business_name ?? seller.store_name}</p>
                                        <p className="text-gray-500">{seller.user.email}</p>
                                    </div>
                                    <span className="font-medium text-orange-500">Approve →</span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Withdrawal payouts</h3>
                        <Link
                            href={route('admin.withdrawals.index', { status: 'pending' })}
                            className="text-sm text-orange-500 hover:underline"
                        >
                            Open payouts
                        </Link>
                    </div>
                    {pendingWithdrawals.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No pending withdrawal requests.</p>
                    ) : (
                        <div className="mt-4 divide-y">
                            {pendingWithdrawals.map((w) => (
                                <Link
                                    key={w.id}
                                    href={route('admin.withdrawals.index', { status: 'pending' })}
                                    className="flex justify-between gap-3 py-3 text-sm hover:bg-gray-50"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">{w.user?.name ?? 'User'}</p>
                                        <p className="truncate text-gray-500">
                                            {w.momo_number}
                                            {w.network ? ` · ${w.network}` : ''}
                                            {w.user?.role ? ` · ${w.user.role}` : ''}
                                        </p>
                                    </div>
                                    <p className="shrink-0 font-medium text-orange-500">{formatPrice(w.amount)}</p>
                                </Link>
                            ))}
                        </div>
                    )}
                    <p className="mt-3 text-xs text-gray-500">
                        Flow: Start processing → send MoMo → Mark paid (optional proof).
                    </p>
                </div>
            </div>

            <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <div className="flex items-center justify-between">
                    <h3 className="font-semibold text-gray-900">Recent Orders</h3>
                    <Link href={route('admin.orders.index')} className="text-sm text-orange-500 hover:underline">
                        View all
                    </Link>
                </div>
                {recentOrders.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">No orders yet.</p>
                ) : (
                    <div className="mt-4 divide-y">
                        {recentOrders.map((order) => (
                            <Link
                                key={order.id}
                                href={route('admin.orders.show', order.id)}
                                className="flex justify-between py-3 text-sm hover:bg-gray-50"
                            >
                                <div>
                                    <p className="font-medium">{order.order_number}</p>
                                    <p className="text-gray-500">{order.buyer?.name}</p>
                                </div>
                                <p className="font-medium text-orange-500">{formatPrice(order.total)}</p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
