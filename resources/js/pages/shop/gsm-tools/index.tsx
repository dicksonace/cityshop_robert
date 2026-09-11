import { Head, Link, router, usePage } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';

import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Field = { id: number; name: string; label: string; placeholder: string | null };
type Service = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_ghs: number;
    fields: Field[];
};
type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    price_ghs: number;
    created_at: string | null;
};

interface Props {
    services: Service[];
    orders: Order[];
    wallet: { available_balance: number } | null;
}

export default function GsmToolsIndex({ services, orders, wallet }: Props) {
    const { flash, auth } = usePage<SharedData>().props;

    return (
        <ShopLayout>
            <Head title="GSM Tools" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <div className="mb-5 flex items-start gap-3">
                    <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-100 text-orange-600">
                        <Smartphone className="h-5 w-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">GSM Tools</h1>
                        <p className="mt-0.5 text-sm text-gray-500">
                            Unlock &amp; device services — paid from your CityShop wallet.
                        </p>
                        {wallet ? (
                            <p className="mt-1 text-sm font-semibold text-emerald-700">
                                Balance {formatPrice(wallet.available_balance)}
                            </p>
                        ) : null}
                    </div>
                </div>

                {(flash?.success || flash?.error) && (
                    <div
                        className={`mb-4 rounded-xl border px-3 py-2 text-sm ${
                            flash.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-red-200 bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div className="space-y-3">
                    {services.map((service) => (
                        <button
                            key={service.id}
                            type="button"
                            onClick={() => {
                                if (!auth?.user) {
                                    router.visit(route('login'));
                                    return;
                                }
                                router.visit(route('gsm-tools.services.show', service.id));
                            }}
                            className="w-full rounded-2xl border border-orange-100 bg-white p-4 text-left shadow-sm transition hover:border-orange-300"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-bold text-gray-900">{service.name}</h2>
                                    {service.description ? (
                                        <p className="mt-1 line-clamp-2 text-xs text-gray-500">{service.description}</p>
                                    ) : null}
                                </div>
                                <span className="shrink-0 text-sm font-extrabold text-orange-600">
                                    {formatPrice(service.price_ghs)}
                                </span>
                            </div>
                        </button>
                    ))}
                    {services.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500">
                            No GSM services available yet.
                        </p>
                    ) : null}
                </div>

                {orders.length > 0 ? (
                    <div className="mt-8">
                        <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500">My orders</h2>
                        <div className="space-y-2">
                            {orders.map((order) => (
                                <Link
                                    key={order.id}
                                    href={route('gsm-tools.orders.show', order.id)}
                                    className="block rounded-xl border border-gray-100 bg-white px-3 py-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-semibold text-gray-900">{order.service_name}</p>
                                            <p className="text-xs text-gray-500">{order.reference}</p>
                                        </div>
                                        <span className="text-xs font-bold text-orange-600">{order.status_label}</span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                ) : null}
            </div>
        </ShopLayout>
    );
}
