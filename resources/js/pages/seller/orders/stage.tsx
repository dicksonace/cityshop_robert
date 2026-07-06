import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import OrderPipelineCards from '@/components/seller/order-pipeline-cards';
import SellerOrderCard, { SellerOrderListItem } from '@/components/seller/seller-order-card';
import SellerLayout from '@/layouts/seller-layout';
import { getSellerOrderStage, SellerOrderStageSlug } from '@/lib/seller-order-stages';
import { Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface OrdersStageProps {
    orders: Paginated<SellerOrderListItem>;
    counts: Record<string, number>;
    stage: SellerOrderStageSlug;
}

export default function OrdersStage({ orders, counts, stage }: OrdersStageProps) {
    const { flash } = usePage<SharedData>().props;
    const meta = getSellerOrderStage(stage);

    if (!meta) {
        return null;
    }

    const Icon = meta.icon;

    return (
        <SellerLayout title={meta.headline} active="orders">
            <Head title={meta.headline} />

            <Link
                href={route('seller.orders.index')}
                className="mb-4 inline-flex items-center text-sm text-gray-500 hover:text-orange-600"
            >
                <ChevronLeft className="mr-1 h-4 w-4" />
                Sales center
            </Link>

            {flash.success && (
                <div className="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {flash.error}
                </div>
            )}

            <div className={`mb-6 overflow-hidden rounded-2xl border bg-white shadow-sm ring-1 ${meta.ring}`}>
                <div className="flex flex-wrap items-center gap-4 p-5 sm:p-6">
                    <div className={`rounded-2xl p-4 ${meta.iconBg}`}>
                        <Icon className={`h-8 w-8 ${meta.accent}`} strokeWidth={1.75} />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-gray-500">{meta.label}</p>
                        <h1 className="text-2xl font-bold text-gray-900">{meta.headline}</h1>
                        <p className="mt-1 text-sm text-gray-500">{meta.description}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-4xl font-bold tabular-nums text-gray-900">{counts[meta.countKey] ?? 0}</p>
                        <p className="text-xs text-gray-400">in this queue</p>
                    </div>
                </div>
            </div>

            <div className="mb-6">
                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Jump to stage</p>
                <OrderPipelineCards counts={counts} activeSlug={stage} compact />
            </div>

            {orders.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
                    <div className={`mx-auto flex h-14 w-14 items-center justify-center rounded-2xl ${meta.iconBg}`}>
                        <Icon className={`h-7 w-7 ${meta.accent}`} />
                    </div>
                    <p className="mt-4 text-lg font-semibold text-gray-900">{meta.emptyTitle}</p>
                    <p className="mx-auto mt-2 max-w-sm text-sm text-gray-500">{meta.emptyHint}</p>
                    <Link
                        href={route('seller.orders.index')}
                        className="mt-6 inline-flex items-center text-sm font-medium text-orange-600 hover:underline"
                    >
                        Back to sales center
                        <ChevronRight className="ml-1 h-4 w-4" />
                    </Link>
                </div>
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {orders.data.map((item) => (
                            <SellerOrderCard key={item.id} item={item} stageSlug={stage} />
                        ))}
                    </div>

                    {orders.last_page > 1 && (
                        <div className="mt-8 flex flex-wrap justify-center gap-2">
                            {orders.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`rounded-lg px-3 py-1.5 text-sm ${
                                        link.active ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </>
            )}
        </SellerLayout>
    );
}
