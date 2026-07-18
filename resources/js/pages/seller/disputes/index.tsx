import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Clock3,
    Inbox,
    MessageSquareWarning,
    Package,
    ShieldCheck,
    User,
    XCircle,
} from 'lucide-react';

import SellerLayout from '@/layouts/seller-layout';
import { cn } from '@/lib/utils';
import { Paginated, productImageUrl } from '@/types/marketplace';

interface SellerDispute {
    id: number;
    reason: string;
    description: string;
    status: string;
    resolution_notes?: string | null;
    created_at: string;
    resolved_at?: string | null;
    order: { order_number: string };
    buyer: { name: string };
    order_item: {
        id?: number;
        product_name: string;
        product?: { images?: { path: string }[] };
    };
}

interface SellerDisputesProps {
    disputes: Paginated<SellerDispute>;
    status: string;
    counts: {
        open: number;
        under_review: number;
        resolved_buyer: number;
        resolved_seller: number;
        cancelled: number;
        all: number;
    };
}

const tabs = [
    { key: 'open', label: 'Open', countKey: 'open' as const },
    { key: 'under_review', label: 'Under review', countKey: 'under_review' as const },
    { key: 'resolved_buyer', label: 'Refunded', countKey: 'resolved_buyer' as const },
    { key: 'resolved_seller', label: 'Declined', countKey: 'resolved_seller' as const },
    { key: 'cancelled', label: 'Cancelled', countKey: 'cancelled' as const },
    { key: 'all', label: 'All', countKey: 'all' as const },
];

function statusMeta(status: string) {
    switch (status) {
        case 'open':
            return {
                label: 'Open',
                hint: 'Waiting for CityShop review',
                badge: 'bg-amber-100 text-amber-800 ring-amber-200',
                accent: 'from-amber-500/15 via-orange-50 to-white border-amber-100',
                icon: Inbox,
                iconBg: 'bg-amber-100 text-amber-600',
            };
        case 'under_review':
            return {
                label: 'Under review',
                hint: 'Admin is reviewing this request',
                badge: 'bg-sky-100 text-sky-800 ring-sky-200',
                accent: 'from-sky-500/15 via-sky-50 to-white border-sky-100',
                icon: Clock3,
                iconBg: 'bg-sky-100 text-sky-600',
            };
        case 'resolved_buyer':
            return {
                label: 'Refunded',
                hint: 'Buyer was refunded to wallet',
                badge: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                accent: 'from-emerald-500/15 via-emerald-50 to-white border-emerald-100',
                icon: CheckCircle2,
                iconBg: 'bg-emerald-100 text-emerald-600',
            };
        case 'resolved_seller':
            return {
                label: 'Declined',
                hint: 'Refund declined in your favor',
                badge: 'bg-violet-100 text-violet-800 ring-violet-200',
                accent: 'from-violet-500/15 via-violet-50 to-white border-violet-100',
                icon: ShieldCheck,
                iconBg: 'bg-violet-100 text-violet-600',
            };
        case 'cancelled':
            return {
                label: 'Cancelled',
                hint: 'Buyer cancelled this request',
                badge: 'bg-slate-100 text-slate-700 ring-slate-200',
                accent: 'from-slate-500/10 via-slate-50 to-white border-slate-100',
                icon: XCircle,
                iconBg: 'bg-slate-100 text-slate-600',
            };
        default:
            return {
                label: status.replace(/_/g, ' '),
                hint: '',
                badge: 'bg-gray-100 text-gray-700 ring-gray-200',
                accent: 'from-gray-100 via-white to-white border-gray-100',
                icon: MessageSquareWarning,
                iconBg: 'bg-gray-100 text-gray-600',
            };
    }
}

function timeAgo(date: string): string {
    const diff = Date.now() - new Date(date).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${Math.max(mins, 1)}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;
    return new Date(date).toLocaleDateString('en-GH', { day: 'numeric', month: 'short' });
}

function reasonLabel(reason: string): string {
    return reason.replace(/_/g, ' ');
}

export default function SellerDisputesIndex({
    disputes,
    status,
    counts = { open: 0, under_review: 0, resolved_buyer: 0, resolved_seller: 0, cancelled: 0, all: 0 },
}: SellerDisputesProps) {
    const needsAttention = counts.open + counts.under_review;

    const summaryCards = [
        {
            label: 'Needs attention',
            value: needsAttention,
            sub: `${counts.open} open · ${counts.under_review} in review`,
            icon: MessageSquareWarning,
            tone: 'border-amber-100 bg-gradient-to-br from-amber-50 to-white',
            iconTone: 'bg-amber-100 text-amber-600',
            href: route('seller.refunds.index', { status: counts.open > 0 ? 'open' : 'under_review' }),
        },
        {
            label: 'Refunded',
            value: counts.resolved_buyer,
            sub: 'Approved for buyer',
            icon: CheckCircle2,
            tone: 'border-emerald-100 bg-gradient-to-br from-emerald-50 to-white',
            iconTone: 'bg-emerald-100 text-emerald-600',
            href: route('seller.refunds.index', { status: 'resolved_buyer' }),
        },
        {
            label: 'Declined',
            value: counts.resolved_seller,
            sub: 'Closed in your favor',
            icon: ShieldCheck,
            tone: 'border-violet-100 bg-gradient-to-br from-violet-50 to-white',
            iconTone: 'bg-violet-100 text-violet-600',
            href: route('seller.refunds.index', { status: 'resolved_seller' }),
        },
        {
            label: 'All requests',
            value: counts.all,
            sub: 'Full refund history',
            icon: Package,
            tone: 'border-sky-100 bg-gradient-to-br from-sky-50 to-white',
            iconTone: 'bg-sky-100 text-sky-600',
            href: route('seller.refunds.index', { status: 'all' }),
        },
    ];

    return (
        <SellerLayout title="Refund requests" active="orders">
            <Head title="Refund requests" />

            <div className="mb-6 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-slate-900 via-slate-800 to-orange-900 p-6 text-white shadow-lg">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-orange-200">Buyer returns</p>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">Refund requests</h1>
                        <p className="mt-2 max-w-xl text-sm text-slate-300">
                            Track buyer refund claims here. CityShop reviews each one before money is returned.
                        </p>
                    </div>
                    <div className="rounded-2xl bg-white/10 px-5 py-4 backdrop-blur">
                        <p className="text-xs uppercase tracking-wide text-orange-200">Needs you</p>
                        <p className="text-3xl font-bold tabular-nums">{needsAttention}</p>
                    </div>
                </div>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {summaryCards.map((card) => (
                    <Link
                        key={card.label}
                        href={card.href}
                        className={cn(
                            'rounded-[1.5rem] border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                            card.tone,
                        )}
                    >
                        <div className="flex items-start gap-3">
                            <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-full', card.iconTone)}>
                                <card.icon className="h-5 w-5" strokeWidth={1.75} />
                            </div>
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-slate-500">{card.label}</p>
                                <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">{card.value}</p>
                                <p className="mt-0.5 text-xs text-slate-400">{card.sub}</p>
                            </div>
                        </div>
                    </Link>
                ))}
            </div>

            <div className="scrollbar-hide -mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pb-1">
                {tabs.map((tab) => {
                    const count = counts[tab.countKey];
                    const active = status === tab.key;

                    return (
                        <Link
                            key={tab.key}
                            href={route('seller.refunds.index', { status: tab.key === 'all' ? undefined : tab.key })}
                            className={cn(
                                'inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition',
                                active
                                    ? 'bg-orange-500 text-white shadow-sm shadow-orange-200'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50',
                            )}
                        >
                            {tab.label}
                            <span
                                className={cn(
                                    'rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums',
                                    active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500',
                                )}
                            >
                                {count}
                            </span>
                        </Link>
                    );
                })}
            </div>

            <div className="space-y-4">
                {disputes.data.length === 0 ? (
                    <div className="rounded-[1.75rem] border border-dashed border-slate-200 bg-white px-6 py-16 text-center">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                            <Inbox className="h-7 w-7" />
                        </div>
                        <p className="mt-4 text-lg font-semibold text-slate-900">No refund requests here</p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                            When a buyer asks for a refund on one of your orders, it shows up in this queue.
                        </p>
                        <Link
                            href={route('seller.orders.index')}
                            className="mt-5 inline-flex items-center gap-1 text-sm font-medium text-orange-600 hover:text-orange-700"
                        >
                            Back to sales
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                ) : (
                    disputes.data.map((d) => {
                        const image = d.order_item?.product?.images?.[0]?.path;
                        const meta = statusMeta(d.status);
                        const StatusIcon = meta.icon;
                        const orderItemId = d.order_item?.id;

                        return (
                            <article
                                key={d.id}
                                className={cn(
                                    'overflow-hidden rounded-[1.75rem] border bg-gradient-to-br p-5 shadow-sm transition hover:shadow-md',
                                    meta.accent,
                                )}
                            >
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <div className="flex gap-3 sm:min-w-0 sm:flex-1">
                                        <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white ring-1 ring-black/5">
                                            {image ? (
                                                <img src={productImageUrl(image)} alt="" className="h-full w-full object-cover" />
                                            ) : (
                                                <Package className="h-6 w-6 text-slate-300" />
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="truncate text-base font-semibold text-slate-900">
                                                    {d.order_item?.product_name ?? 'Product'}
                                                </h2>
                                                <span className={cn('rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1', meta.badge)}>
                                                    {meta.label}
                                                </span>
                                            </div>

                                            <p className="mt-1 text-sm text-slate-500">
                                                Order {d.order?.order_number} · {timeAgo(d.created_at)}
                                            </p>

                                            <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1 text-slate-600 ring-1 ring-slate-200/80">
                                                    <User className="h-3.5 w-3.5" />
                                                    {d.buyer?.name ?? 'Buyer'}
                                                </span>
                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1 capitalize text-slate-600 ring-1 ring-slate-200/80">
                                                    <StatusIcon className="h-3.5 w-3.5" />
                                                    {reasonLabel(d.reason)}
                                                </span>
                                            </div>

                                            {d.description && (
                                                <p className="mt-3 line-clamp-3 rounded-2xl bg-white/70 px-3 py-2 text-sm text-slate-600 ring-1 ring-slate-100">
                                                    {d.description}
                                                </p>
                                            )}

                                            {d.resolution_notes && (
                                                <p className="mt-2 text-xs text-slate-500">
                                                    Admin note: {d.resolution_notes}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex shrink-0 flex-col items-stretch gap-2 sm:w-44">
                                        <div className="flex items-center gap-2 rounded-2xl bg-white/80 px-3 py-2 text-sm ring-1 ring-black/5">
                                            <div className={cn('flex h-8 w-8 items-center justify-center rounded-full', meta.iconBg)}>
                                                <StatusIcon className="h-4 w-4" />
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-xs font-medium text-slate-500">Status</p>
                                                <p className="truncate text-sm font-semibold text-slate-800">{meta.label}</p>
                                            </div>
                                        </div>
                                        {meta.hint && (
                                            <p className="text-xs leading-relaxed text-slate-500 sm:text-right">{meta.hint}</p>
                                        )}
                                        {orderItemId ? (
                                            <Link
                                                href={route('seller.orders.show', orderItemId)}
                                                className="inline-flex items-center justify-center gap-1 rounded-full bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                                            >
                                                View order
                                                <ArrowRight className="h-3.5 w-3.5" />
                                            </Link>
                                        ) : null}
                                    </div>
                                </div>
                            </article>
                        );
                    })
                )}
            </div>

            {disputes.last_page > 1 && (
                <div className="mt-6 flex items-center justify-center gap-2">
                    {disputes.links.map((link, index) => {
                        if (!link.url) {
                            return (
                                <span
                                    key={`${link.label}-${index}`}
                                    className="rounded-full px-3 py-1.5 text-sm text-slate-400"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            );
                        }

                        return (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url}
                                className={cn(
                                    'rounded-full px-3 py-1.5 text-sm font-medium transition',
                                    link.active
                                        ? 'bg-orange-500 text-white'
                                        : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    })}
                </div>
            )}
        </SellerLayout>
    );
}
