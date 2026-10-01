import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Field = { id: number; name: string; label: string; placeholder: string | null };
type Service = {
    id: number;
    name: string;
    service_type: string;
    service_type_label?: string;
    group_id: number | null;
    image_url: string | null;
    description: string | null;
    eta_label: string;
    price_ghs: number;
    fields: Field[];
};

function typeChip(type: string): string {
    if (type === 'credit') return 'CREDIT';
    return type.replace(/_/g, ' ').toUpperCase();
}

function ServiceCard({
    service,
    fallbackImage,
    onOpen,
}: {
    service: Service;
    fallbackImage?: string | null;
    onOpen: (id: number) => void;
}) {
    const logo = service.image_url || fallbackImage || '/images/gsm/gmt-logo.jpg';

    return (
        <button
            type="button"
            onClick={() => onOpen(service.id)}
            className="flex w-full items-start gap-3 rounded-2xl border border-gray-200 bg-white px-3 py-3 text-left"
        >
            {logo ? (
                <img src={logo} alt="" className="h-[52px] w-[52px] shrink-0 rounded-xl object-contain" />
            ) : (
                <span className="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-xl bg-orange-50 text-xs font-bold text-orange-600">
                    GSM
                </span>
            )}
            <div className="min-w-0 pt-0.5">
                <p className="text-[15px] font-semibold leading-snug text-gray-900">{service.name}</p>
                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold text-emerald-800">
                        {formatPrice(service.price_ghs)}
                    </span>
                    <span className="rounded bg-orange-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-orange-500">
                        {typeChip(service.service_type)}
                    </span>
                </div>
            </div>
        </button>
    );
}

type Group = {
    id: number;
    name: string;
    service_type: string;
    image_url: string | null;
    services: Service[];
};
type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    image_url?: string | null;
    created_at: string | null;
};

interface Props {
    services: Service[];
    groups: Group[];
    serviceTypes: { value: string; label: string }[];
    orders: Order[];
    wallet: { available_balance: number } | null;
}

export default function GsmToolsIndex({ services, groups, serviceTypes, orders, wallet }: Props) {
    const { flash, auth } = usePage<SharedData>().props;
    const [query, setQuery] = useState('');
    const [serviceType, setServiceType] = useState('');
    const [categoryId, setCategoryId] = useState('');

    const categories = useMemo(
        () => (serviceType ? groups.filter((group) => group.service_type === serviceType) : groups),
        [groups, serviceType],
    );

    const visibleGroups = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return categories
            .filter((group) => !categoryId || String(group.id) === categoryId)
            .map((group) => ({
                ...group,
                services: (group.services ?? []).filter((service) => {
                    const text = `${service.name} ${service.description ?? ''} ${group.name}`.toLowerCase();
                    return needle === '' || text.includes(needle);
                }),
            }))
            .filter((group) => group.services.length > 0);
    }, [categories, categoryId, query]);

    const ungrouped = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return services.filter((service) => {
            if (service.group_id) return false;
            const typeOk = !serviceType || service.service_type === serviceType;
            const text = `${service.name} ${service.description ?? ''}`.toLowerCase();
            return typeOk && (needle === '' || text.includes(needle));
        });
    }, [services, serviceType, query]);

    const openService = (id: number) => {
        if (!auth?.user) {
            router.visit(route('login'));
            return;
        }
        router.visit(route('gsm-tools.services.show', id));
    };

    const reset = () => {
        setQuery('');
        setServiceType('');
        setCategoryId('');
    };

    return (
        <ShopLayout>
            <Head title="Place order" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <h1 className="text-xl font-bold text-gray-900">Place order</h1>
                <p className="mt-1 text-sm text-gray-500">Instantly place orders using your wallet balance.</p>
                {wallet ? (
                    <p className="mt-1 text-sm font-semibold text-emerald-700">Balance {formatPrice(wallet.available_balance)}</p>
                ) : null}

                {(flash?.success || flash?.error) && (
                    <div
                        className={`mt-4 rounded-xl border px-3 py-2 text-sm ${
                            flash.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-red-200 bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div className="mt-5 space-y-2">
                    <select
                        value={serviceType}
                        onChange={(e) => {
                            setServiceType(e.target.value);
                            setCategoryId('');
                        }}
                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm"
                    >
                        <option value="">Select Type</option>
                        {serviceTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={categoryId}
                        onChange={(e) => setCategoryId(e.target.value)}
                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm"
                    >
                        <option value="">All categories</option>
                        {categories.map((group) => (
                            <option key={group.id} value={group.id}>
                                {group.name}
                            </option>
                        ))}
                    </select>
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search services..."
                        className="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm"
                    />
                    <button
                        type="button"
                        onClick={reset}
                        className="h-10 w-full rounded-xl border border-orange-200 text-sm font-semibold text-orange-600"
                    >
                        Reset
                    </button>
                </div>

                <div className="mt-5 space-y-5">
                    {visibleGroups.map((group) => (
                        <section key={group.id} className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                            <h2 className="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-500">{group.name}</h2>
                            <div className="space-y-3 p-3">
                                {group.services.map((service) => (
                                    <ServiceCard
                                        key={service.id}
                                        service={service}
                                        fallbackImage={group.image_url}
                                        onOpen={openService}
                                    />
                                ))}
                            </div>
                        </section>
                    ))}

                    {ungrouped.length > 0 ? (
                        <section className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                            <h2 className="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-500">Other services</h2>
                            <div className="space-y-3 p-3">
                                {ungrouped.map((service) => (
                                    <ServiceCard key={service.id} service={service} onOpen={openService} />
                                ))}
                            </div>
                        </section>
                    ) : null}

                    {visibleGroups.every((g) => g.services.length === 0) && ungrouped.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500">
                            {services.length === 0 ? 'No GSM services available yet.' : 'No services match this search.'}
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
                                    className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-3 py-3"
                                >
                                    {order.image_url ? (
                                        <img src={order.image_url} alt="" className="h-10 w-10 shrink-0 rounded-lg bg-slate-950 object-contain" />
                                    ) : null}
                                    <div className="flex min-w-0 flex-1 items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-semibold text-gray-900">{order.service_name}</p>
                                            <p className="text-xs text-gray-500">{order.reference}</p>
                                        </div>
                                        <span className="text-xs font-extrabold uppercase text-amber-700">{order.status_label}</span>
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
