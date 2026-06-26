import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Eye, ShoppingCart, Heart, TrendingUp } from 'lucide-react';

import SellerLayout from '@/layouts/seller-layout';
import { formatPrice, Product, productImageUrl } from '@/types/marketplace';

interface AnalyticsProps {
    product: Product;
    stats: {
        views: number;
        cart_adds: number;
        wishlist_adds: number;
        purchases: number;
        revenue: number;
        conversion_rate: number;
        chart: { date: string; views: number; cart_adds: number; purchases: number }[];
    };
}

export default function ProductAnalytics({ product, stats }: AnalyticsProps) {
    const image = product.images?.[0];
    const maxViews = Math.max(...stats.chart.map((d) => d.views), 1);

    return (
        <SellerLayout title="Product analytics" active="products">
            <Head title={`Analytics — ${product.name}`} />
            <Link href={route('seller.products.index')} className="inline-flex items-center gap-1 text-sm text-orange-500 hover:underline">
                <ArrowLeft className="h-4 w-4" /> Back to products
            </Link>

            <div className="mt-4 flex gap-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div className="flex h-20 w-20 items-center justify-center rounded-xl bg-gray-50 p-2">
                    {image && <img src={productImageUrl(image.path)} alt="" className="max-h-full max-w-full object-contain" />}
                </div>
                <div>
                    <h1 className="text-xl font-bold text-gray-900">{product.name}</h1>
                    <p className="text-sm text-gray-500">{product.category?.name}</p>
                </div>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {[
                    { label: 'Views', value: stats.views, icon: Eye, color: 'text-blue-600' },
                    { label: 'Cart adds', value: stats.cart_adds, icon: ShoppingCart, color: 'text-orange-600' },
                    { label: 'Wishlists', value: stats.wishlist_adds, icon: Heart, color: 'text-pink-600' },
                    { label: 'Purchases', value: stats.purchases, icon: TrendingUp, color: 'text-emerald-600' },
                    { label: 'Revenue', value: formatPrice(stats.revenue), icon: TrendingUp, color: 'text-green-600' },
                    { label: 'Conversion', value: `${stats.conversion_rate}%`, icon: TrendingUp, color: 'text-purple-600' },
                ].map((kpi) => (
                    <div key={kpi.label} className="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <kpi.icon className={`h-5 w-5 ${kpi.color}`} />
                        <p className="mt-2 text-2xl font-bold text-gray-900">{kpi.value}</p>
                        <p className="text-xs text-gray-500">{kpi.label}</p>
                    </div>
                ))}
            </div>

            <div className="mt-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="font-semibold text-gray-900">Views (30 days)</h2>
                <div className="mt-6 flex h-32 items-end gap-1">
                    {stats.chart.slice(-14).map((point) => (
                        <div
                            key={point.date}
                            className="flex-1 rounded-t bg-blue-400"
                            style={{ height: `${Math.max(4, (point.views / maxViews) * 100)}%` }}
                            title={`${point.date}: ${point.views} views`}
                        />
                    ))}
                </div>
            </div>
        </SellerLayout>
    );
}
