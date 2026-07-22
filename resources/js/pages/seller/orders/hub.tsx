import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ShoppingBag } from 'lucide-react';

import OrderPipelineCards from '@/components/seller/order-pipeline-cards';
import SellerOrderCard, { SellerOrderListItem } from '@/components/seller/seller-order-card';
import SellerLayout from '@/layouts/seller-layout';
import { sellerOrdersStageHref } from '@/lib/seller-order-stages';

interface OrdersHubProps {
    counts: Record<string, number>;
    urgentOrders: SellerOrderListItem[];
    recentCompleted: SellerOrderListItem[];
    needsAction: number;
}

export default function OrdersHub({ counts, urgentOrders, recentCompleted, needsAction }: OrdersHubProps) {
    return (
        <SellerLayout title="Sales" active="orders">
            <Head title="Sales" />

            <div className="mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-orange-900 p-6 text-white shadow-lg">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-orange-200">Order pipeline</p>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">Sales center</h1>
                        <p className="mt-2 max-w-xl text-sm text-gray-300">
                            Track every sale from new order to completed delivery. Each stage has its own queue — move orders forward as you fulfill them.
                        </p>
                    </div>
                    <div className="rounded-2xl bg-white/10 px-5 py-4 backdrop-blur">
                        <p className="text-xs uppercase tracking-wide text-orange-200">Needs action</p>
                        <p className="text-3xl font-bold tabular-nums">{needsAction}</p>
                    </div>
                </div>
            </div>

            <section className="mb-8">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-gray-900">Your queues</h2>
                    <Link
                        href={sellerOrdersStageHref('completed')}
                        className="text-sm font-medium text-orange-600 hover:text-orange-700"
                    >
                        View completed
                    </Link>
                </div>
                <OrderPipelineCards counts={counts} />
            </section>

            <div className="grid gap-6 lg:grid-cols-5">
                <section className="lg:col-span-3">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="font-semibold text-gray-900">Active orders</h2>
                            <p className="text-sm text-gray-500">New through out for delivery</p>
                        </div>
                        {urgentOrders.length > 0 && (
                            <Link
                                href={sellerOrdersStageHref('new')}
                                className="flex items-center text-sm text-orange-600 hover:underline"
                            >
                                View new
                                <ArrowRight className="ml-1 h-4 w-4" />
                            </Link>
                        )}
                    </div>

                    {urgentOrders.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-14 text-center">
                            <ShoppingBag className="mx-auto h-10 w-10 text-gray-300" />
                            <p className="mt-3 font-medium text-gray-900">All caught up</p>
                            <p className="mt-1 text-sm text-gray-500">No active orders right now. New sales will show up here.</p>
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {urgentOrders.map((item) => (
                                <SellerOrderCard key={item.id} item={item} />
                            ))}
                        </div>
                    )}
                </section>

                <aside className="space-y-4 lg:col-span-2">
                    <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Quick stats</h3>
                        <dl className="mt-4 space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Total orders</dt>
                                <dd className="font-semibold">{counts.all}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Completed</dt>
                                <dd className="font-semibold text-emerald-600">{counts.delivered}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Awaiting buyer</dt>
                                <dd className="font-semibold text-cyan-600">{counts.awaiting_confirmation}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Cancelled / refunded</dt>
                                <dd className="font-semibold text-gray-600">{counts.cancelled}</dd>
                            </div>
                        </dl>
                        <Link
                            href={sellerOrdersStageHref('cancelled')}
                            className="mt-4 block text-center text-sm font-medium text-gray-600 hover:underline"
                        >
                            Cancelled orders →
                        </Link>
                        <Link
                            href={route('seller.refunds.index')}
                            className="mt-2 block text-center text-sm font-medium text-orange-600 hover:underline"
                        >
                            Refund requests →
                        </Link>
                    </div>

                    {recentCompleted.length > 0 && (
                        <div className="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5">
                            <h3 className="font-semibold text-emerald-900">Recently completed</h3>
                            <ul className="mt-3 divide-y divide-emerald-100">
                                {recentCompleted.map((item) => (
                                    <li key={item.id}>
                                        <Link
                                            href={route('seller.orders.show', item.id)}
                                            className="flex items-center justify-between py-2.5 text-sm hover:opacity-80"
                                        >
                                            <span className="truncate font-medium text-emerald-900">{item.product_name}</span>
                                            <ArrowRight className="ml-2 h-4 w-4 shrink-0 text-emerald-600" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </aside>
            </div>
        </SellerLayout>
    );
}
