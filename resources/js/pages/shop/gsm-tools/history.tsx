import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import ShopLayout from '@/layouts/shop-layout';
import { Paginated } from '@/types/marketplace';

type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    image_url?: string | null;
    eta_label?: string | null;
    created_at: string | null;
};

function orderStatus(order: Order) {
    const status = order.status === 'pending' ? 'processing' : order.status;
    const label = status === 'processing' ? 'PROCESSING' : (order.status_label || status).toUpperCase();
    const className =
        status === 'processing'
            ? 'bg-blue-50 text-blue-700'
            : status === 'completed'
              ? 'bg-emerald-50 text-emerald-800'
              : status === 'failed' || status === 'cancelled'
                ? 'bg-red-50 text-red-700'
                : 'bg-amber-50 text-amber-800';
    return { label, className };
}

export default function GsmToolsHistory({ orders }: { orders: Paginated<Order> }) {
    const [items, setItems] = useState(orders.data);
    const loadingMore = useRef(false);
    const sentinel = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (orders.current_page <= 1) {
            setItems(orders.data);
            loadingMore.current = false;
            return;
        }
        setItems((prev) => {
            const seen = new Set(prev.map((order) => order.id));
            return [...prev, ...orders.data.filter((order) => !seen.has(order.id))];
        });
        loadingMore.current = false;
    }, [orders]);

    useEffect(() => {
        const el = sentinel.current;
        if (!el) return;
        const observer = new IntersectionObserver(
            (entries) => {
                if (!entries[0]?.isIntersecting) return;
                if (loadingMore.current || orders.current_page >= orders.last_page) return;
                loadingMore.current = true;
                router.get(
                    route('gsm-tools.history'),
                    { page: orders.current_page + 1 },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        only: ['orders'],
                        onFinish: () => {
                            loadingMore.current = false;
                        },
                    },
                );
            },
            { rootMargin: '240px' },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, [orders.current_page, orders.last_page]);

    return (
        <ShopLayout>
            <Head title="Order History" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-xl font-black text-gray-900">Order History</h1>
                    <Link href={route('gsm-tools.index')} className="text-sm font-extrabold text-orange-600">
                        Place order
                    </Link>
                </div>
                <p className="mt-1 text-sm text-gray-500">Your GSM tool orders, newest first.</p>

                <div className="mt-5 space-y-2.5">
                    {items.map((order) => {
                        const { label, className } = orderStatus(order);
                        return (
                            <Link
                                key={order.id}
                                href={route('gsm-tools.orders.show', order.id)}
                                className="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white px-3 py-3"
                            >
                                {order.image_url ? (
                                    <img src={order.image_url} alt="" className="h-11 w-11 shrink-0 rounded-xl object-contain" />
                                ) : (
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-[10px] font-bold text-orange-600">
                                        GSM
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-extrabold text-gray-900">{order.service_name}</p>
                                    <p className="text-xs text-gray-500">{order.reference}</p>
                                    {order.eta_label ? (
                                        <p className="mt-0.5 text-[11px] font-extrabold text-blue-700">{order.eta_label}</p>
                                    ) : null}
                                </div>
                                <span className={`rounded-full px-2 py-1 text-[10px] font-black uppercase ${className}`}>{label}</span>
                            </Link>
                        );
                    })}
                </div>

                {items.length === 0 ? (
                    <p className="mt-8 rounded-2xl border border-dashed border-gray-200 p-8 text-center text-sm text-gray-500">
                        No orders yet.
                    </p>
                ) : null}

                {orders.current_page < orders.last_page ? (
                    <div ref={sentinel} className="py-6 text-center text-sm font-semibold text-gray-400">
                        Loading more…
                    </div>
                ) : items.length > 0 ? (
                    <p className="py-6 text-center text-xs font-semibold text-gray-400">End of history</p>
                ) : null}
            </div>
        </ShopLayout>
    );
}
