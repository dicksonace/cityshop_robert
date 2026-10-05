import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import RechargeModal from '@/components/wallet/recharge-modal';
import { type FundingAccount } from '@/components/wallet/manual-top-up-form';
import { type PaystackFeeSettings } from '@/lib/paystack-fees';
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
    const logo = service.image_url || fallbackImage || '';

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
                    {service.eta_label ? (
                        <span className="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700">
                            {service.eta_label}
                        </span>
                    ) : null}
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

interface Props {
    services: Service[];
    groups: Group[];
    serviceTypes: { value: string; label: string }[];
    wallet: { available_balance: number } | null;
    paystackConfigured?: boolean;
    flutterwaveConfigured?: boolean;
    paystackFee?: PaystackFeeSettings | null;
    manualTopUpEnabled?: boolean;
    manualFundingAccounts?: FundingAccount[];
}

export default function GsmToolsIndex({
    services,
    groups,
    serviceTypes,
    wallet,
    paystackConfigured = false,
    flutterwaveConfigured = false,
    paystackFee = null,
    manualTopUpEnabled = false,
    manualFundingAccounts = [],
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const [query, setQuery] = useState('');
    const [serviceType, setServiceType] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [rechargeOpen, setRechargeOpen] = useState(false);

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
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-xl font-bold text-gray-900">Place order</h1>
                    {auth?.user ? (
                        <Link href={route('gsm-tools.history')} className="text-sm font-extrabold text-orange-600">
                            Order History
                        </Link>
                    ) : null}
                </div>
                {wallet ? (
                    <div className="mt-3 flex items-center justify-between gap-3 rounded-2xl bg-orange-600 p-4 text-white">
                        <div>
                            <p className="text-[11px] font-extrabold tracking-wide text-white">WALLET BALANCE</p>
                            <p className="mt-1 text-[28px] font-black leading-none">{formatPrice(wallet.available_balance)}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setRechargeOpen(true)}
                            className="shrink-0 rounded-xl bg-white px-4 py-2.5 text-sm font-extrabold text-orange-600"
                        >
                            Recharge
                        </button>
                    </div>
                ) : (
                    <p className="mt-1 text-sm text-gray-500">Instantly place orders using your wallet balance.</p>
                )}

                <RechargeModal
                    open={rechargeOpen}
                    onClose={() => setRechargeOpen(false)}
                    paystackConfigured={paystackConfigured}
                    flutterwaveConfigured={flutterwaveConfigured}
                    manualTopUpEnabled={manualTopUpEnabled}
                    manualFundingAccounts={manualFundingAccounts}
                    manualHref={route('wallet.manual-top-up')}
                    paystackRoute={route('wallet.add-funds')}
                    flutterwaveRoute={route('wallet.add-funds.flutterwave')}
                    amountInputId="gsm-recharge-amount"
                    paystackFee={paystackFee}
                />

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
            </div>
        </ShopLayout>
    );
}
