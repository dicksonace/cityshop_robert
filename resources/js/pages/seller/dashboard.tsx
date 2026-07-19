import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Clock,
    Eye,
    Package,
    RefreshCw,
    ShoppingCart,
    Star,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import RevenueChart from '@/components/seller/revenue-chart';
import OrderPipelineCards from '@/components/seller/order-pipeline-cards';
import StoreShareCard from '@/components/seller/store-share-card';
import WalletBalanceCard from '@/components/seller/wallet-balance-card';
import SellerLayout from '@/layouts/seller-layout';
import { sellerOrdersStageHref } from '@/lib/seller-order-stages';
import { formatPrice, formatOrderStatus, OrderItem, SellerProfile } from '@/types/marketplace';

const AUTO_REFRESH_SECONDS = 60;
const DASHBOARD_RELOAD_KEYS = [
    'stats',
    'orderPipelineCounts',
    'recentOrders',
    'recentReviews',
    'recentWithdrawals',
    'revenueChart',
    'storeHealth',
] as const;

interface DashboardProps {
    stats: {
        total_products: number;
        live_products: number;
        pending_products: number;
        out_of_stock: number;
        total_orders: number;
        pending_orders: number;
        processing_orders: number;
        delivered_orders: number;
        cancelled_orders: number;
        available_balance: number;
        pending_balance: number;
        withdrawable_balance: number;
        total_earnings: number;
        withdrawn_amount: number;
        product_views: number;
        average_rating: number;
    };
    revenueChart: { date: string; revenue: number; orders: number }[];
    storeHealth: { score: number; stars: number; tips: string[] };
    recentOrders: OrderItem[];
    recentReviews: { id: number; rating: number; comment?: string; user?: { name: string }; product?: { name: string }; created_at: string }[];
    recentWithdrawals: { id: number; amount: number; status: string; created_at: string }[];
    profile: SellerProfile;
    storeUrl: string | null;
    orderPipelineCounts: Record<string, number>;
}

export default function SellerDashboard({
    stats,
    revenueChart,
    storeHealth,
    recentOrders,
    recentReviews,
    recentWithdrawals,
    profile,
    storeUrl,
    orderPipelineCounts,
}: DashboardProps) {
    const [secondsLeft, setSecondsLeft] = useState(AUTO_REFRESH_SECONDS);
    const [refreshing, setRefreshing] = useState(false);

    const refreshDashboard = useCallback(() => {
        setRefreshing(true);
        router.reload({
            only: [...DASHBOARD_RELOAD_KEYS],
            onFinish: () => {
                setRefreshing(false);
                setSecondsLeft(AUTO_REFRESH_SECONDS);
            },
        });
    }, []);

    useEffect(() => {
        if (refreshing) {
            return;
        }

        if (secondsLeft <= 0) {
            refreshDashboard();
            return;
        }

        const timer = window.setTimeout(() => {
            setSecondsLeft((s) => s - 1);
        }, 1000);

        return () => window.clearTimeout(timer);
    }, [secondsLeft, refreshing, refreshDashboard]);

    const newOrdersCount = orderPipelineCounts.pending ?? stats.pending_orders ?? 0;

    const kpis = [
        {
            label: 'Pending funds',
            value: formatPrice(stats.pending_balance),
            sub: 'Awaiting admin release',
            icon: Clock,
            color: 'text-amber-600',
            bg: 'bg-amber-50',
            ring: 'border-amber-100',
            href: route('seller.wallet'),
        },
        { label: 'Orders', value: stats.total_orders, sub: `${stats.pending_orders} new`, icon: ShoppingCart, color: 'text-orange-600', bg: 'bg-orange-50', ring: 'border-orange-100', href: route('seller.orders.index') },
        { label: 'Live products', value: stats.live_products, sub: `${stats.out_of_stock} out of stock`, icon: Package, color: 'text-sky-600', bg: 'bg-sky-50', ring: 'border-sky-100', href: route('seller.products.index', { status: 'approved' }) },
        { label: 'Store views', value: stats.product_views, sub: stats.average_rating ? `${stats.average_rating}★ avg rating` : 'No ratings yet', icon: Eye, color: 'text-violet-600', bg: 'bg-violet-50', ring: 'border-violet-100', href: route('seller.store-appearance.index') },
    ];

    return (
        <SellerLayout title="Dashboard" active="dashboard">
            <Head title="Seller Dashboard" />

            <div className="mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-orange-500 via-orange-600 to-blue-600 p-6 text-white shadow-lg">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-orange-100">Welcome back</p>
                        <h2 className="text-2xl font-bold">{profile.business_name ?? profile.store_name}</h2>
                        <p className="mt-1 text-sm text-orange-100">Total earnings · {formatPrice(stats.total_earnings)}</p>
                    </div>
                    <div className="rounded-xl bg-white/15 px-4 py-3 text-center backdrop-blur">
                        <p className="text-xs text-orange-100">Store health</p>
                        <p className="text-2xl font-bold">{storeHealth.score}%</p>
                        <div className="mt-1 flex justify-center gap-0.5">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Star key={i} className={`h-3.5 w-3.5 ${i < Math.round(storeHealth.stars) ? 'fill-yellow-300 text-yellow-300' : 'text-white/30'}`} />
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {storeHealth.tips.length > 0 && (
                <div className="mb-6 rounded-xl border border-amber-100 bg-amber-50 p-4">
                    <p className="flex items-center gap-2 text-sm font-semibold text-amber-900">
                        <AlertCircle className="h-4 w-4" /> Tips to grow your store
                    </p>
                    <ul className="mt-2 space-y-1 text-sm text-amber-800">
                        {storeHealth.tips.map((tip) => (
                            <li key={tip}>· {tip}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mb-6 grid gap-4 lg:grid-cols-5">
                <WalletBalanceCard
                    className="lg:col-span-2"
                    balance={stats.available_balance}
                    pendingBalance={stats.pending_balance}
                    withdrawHref={`${route('seller.wallet')}#withdraw`}
                    historyHref={`${route('seller.wallet')}#history`}
                    onRefresh={refreshDashboard}
                    refreshing={refreshing}
                    countdownSec={secondsLeft}
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:col-span-3 lg:grid-cols-2">
                    {kpis.map((kpi) => (
                        <Link
                            key={kpi.label}
                            href={kpi.href}
                            className={`group rounded-[1.5rem] border bg-white p-4 shadow-sm transition hover:shadow-md ${kpi.ring}`}
                        >
                            <div className="flex items-start gap-3">
                                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${kpi.bg}`}>
                                    <kpi.icon className={`h-5 w-5 ${kpi.color}`} strokeWidth={1.75} />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-slate-500">{kpi.label}</p>
                                    <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">{kpi.value}</p>
                                    <p className="mt-0.5 text-xs text-slate-400">{kpi.sub}</p>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>

            <div className="mb-6">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Order pipeline</h2>
                        <p className="text-xs text-gray-500">
                            New orders: <span className="font-semibold text-orange-600">{newOrdersCount}</span>
                            {' · '}Auto refresh in {secondsLeft}s
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={refreshDashboard}
                            disabled={refreshing}
                            className="inline-flex items-center gap-1.5 rounded-full border border-orange-200 bg-white px-3 py-1.5 text-xs font-semibold text-orange-600 shadow-sm transition hover:bg-orange-50 disabled:opacity-60"
                        >
                            <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </button>
                        <Link href={sellerOrdersStageHref('new')} className="text-sm text-orange-600 hover:underline">
                            Open new orders
                        </Link>
                    </div>
                </div>
                <OrderPipelineCards counts={orderPipelineCounts} compact />
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <RevenueChart data={revenueChart} />
                </div>
                {storeUrl && profile.slug && (
                    <div className="space-y-4">
                        <StoreShareCard slug={profile.slug} storeName={profile.business_name ?? profile.store_name ?? 'My Store'} storeUrl={storeUrl} />
                        <Link href={route('seller.store-appearance.index')} className="flex items-center justify-between rounded-2xl border border-orange-100 bg-orange-50 p-5 transition hover:bg-orange-100">
                            <div>
                                <p className="font-semibold text-orange-900">Customize store</p>
                                <p className="text-sm text-orange-700">Theme, banner & layout</p>
                            </div>
                            <ArrowRight className="h-5 w-5 text-orange-500" />
                        </Link>
                    </div>
                )}
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Recent orders</h3>
                        <Link href={route('seller.orders.index')} className="text-sm text-orange-500 hover:underline">View all</Link>
                    </div>
                    {recentOrders.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No orders yet.</p>
                    ) : (
                        <div className="mt-4 divide-y">
                            {recentOrders.map((item) => (
                                <Link key={item.id} href={route('seller.orders.show', item.id)} className="flex items-center justify-between rounded-lg px-2 py-3 -mx-2 text-sm hover:bg-gray-50">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{item.product_name}</p>
                                        <p className="text-gray-500">Qty {item.quantity} · {formatOrderStatus(item.status)}</p>
                                    </div>
                                    <p className="shrink-0 font-semibold text-orange-500">{formatPrice(item.unit_price * item.quantity)}</p>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Recent reviews</h3>
                        {recentReviews.length === 0 ? (
                            <p className="mt-3 text-sm text-gray-500">No reviews yet.</p>
                        ) : (
                            <ul className="mt-3 space-y-3">
                                {recentReviews.map((r) => (
                                    <li key={r.id} className="text-sm">
                                        <div className="flex items-center gap-1 text-amber-500">
                                            {Array.from({ length: r.rating }).map((_, i) => (
                                                <Star key={i} className="h-3 w-3 fill-current" />
                                            ))}
                                        </div>
                                        <p className="mt-0.5 line-clamp-2 text-gray-600">{r.comment || 'No comment'}</p>
                                        <p className="text-xs text-gray-400">{r.product?.name}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold text-gray-900">Withdrawals</h3>
                            <Link href={`${route('seller.wallet')}#history`} className="text-sm text-orange-500 hover:underline">Finance</Link>
                        </div>
                        {recentWithdrawals.length === 0 ? (
                            <p className="mt-3 text-sm text-gray-500">No withdrawals yet.</p>
                        ) : (
                            <ul className="mt-3 divide-y text-sm">
                                {recentWithdrawals.map((w) => (
                                    <li key={w.id} className="flex justify-between py-2">
                                        <span className="capitalize text-gray-500">{w.status}</span>
                                        <span className="font-medium">{formatPrice(w.amount)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
