import { Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    FileText,
    MessageSquare,
    Package,
    PackageCheck,
    RotateCcw,
    Star,
    Truck,
    Wallet,
} from 'lucide-react';

interface OrderHubCounts {
    all: number;
    unpaid: number;
    processing: number;
    delivery: number;
    confirm: number;
    refunds: number;
    completed: number;
    cancelled: number;
    review: number;
    invoices: number;
}

interface BuyerOrderHubProps {
    counts: OrderHubCounts;
    activeTab: string;
}

const shortcuts = [
    { key: 'unpaid', label: 'Unpaid', icon: Wallet },
    { key: 'processing', label: 'Processing', icon: Clock },
    { key: 'delivery', label: 'Delivery', icon: Truck },
    { key: 'confirm', label: 'Confirm', icon: PackageCheck },
    { key: 'completed', label: 'Completed', icon: CheckCircle2 },
    { key: 'review', label: 'Review', icon: Star },
] as const;

const quickLinks = [
    { label: 'Wallet', href: () => route('wallet.index'), icon: Wallet },
    { label: 'Invoices', tab: 'invoices', icon: FileText },
    { label: 'Wishlist', href: () => route('wishlist.index'), icon: Star },
    { label: 'Messages', href: () => route('chat.index'), icon: MessageSquare },
] as const;

export default function BuyerOrderHub({ counts, activeTab }: BuyerOrderHubProps) {
    const goTab = (tab: string) => {
        router.get(route('orders.index'), tab === 'all' ? {} : { tab }, { preserveState: true, preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900">My orders</h2>
                    <button
                        type="button"
                        onClick={() => goTab('all')}
                        className="flex items-center text-sm text-gray-500 hover:text-orange-500"
                    >
                        View all
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>
                <div className="mt-4 grid grid-cols-3 gap-1 sm:grid-cols-6">
                    {shortcuts.map(({ key, label, icon: Icon }) => {
                        const count = counts[key];
                        const active = activeTab === key;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => goTab(key)}
                                className={`relative flex flex-col items-center gap-1.5 rounded-xl px-1 py-2 transition-colors ${
                                    active ? 'bg-orange-50 text-orange-600' : 'text-gray-600 hover:bg-gray-50'
                                }`}
                            >
                                <div className="relative">
                                    <Icon className="h-6 w-6" strokeWidth={1.75} />
                                    {count > 0 && (
                                        <span className="absolute -top-1.5 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                                            {count > 99 ? '99+' : count}
                                        </span>
                                    )}
                                </div>
                                <span className="text-[10px] font-medium leading-tight sm:text-xs">{label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <h3 className="mb-3 text-sm font-semibold text-gray-900">Payments & account</h3>
                <div className="grid grid-cols-4 gap-2">
                    {quickLinks.map((item) => {
                        const Icon = item.icon;
                        const isInvoices = 'tab' in item;
                        const badge = isInvoices ? counts.invoices : 0;
                        return (
                            <button
                                key={item.label}
                                type="button"
                                onClick={() => (isInvoices ? goTab(item.tab) : router.visit(item.href()))}
                                className="relative flex flex-col items-center gap-1.5 rounded-xl py-2 text-gray-600 hover:bg-gray-50"
                            >
                                <div className="relative">
                                    <Icon className="h-5 w-5" strokeWidth={1.75} />
                                    {badge > 0 && (
                                        <span className="absolute -top-1 -right-2 h-2 w-2 rounded-full bg-red-500" />
                                    )}
                                </div>
                                <span className="text-center text-[10px] leading-tight text-gray-600 sm:text-xs">{item.label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

export const orderTabs = [
    { key: 'all', label: 'All' },
    { key: 'unpaid', label: 'Unpaid' },
    { key: 'processing', label: 'Processing' },
    { key: 'delivery', label: 'Delivery' },
    { key: 'confirm', label: 'Confirm delivery' },
    { key: 'completed', label: 'Completed' },
    { key: 'review', label: 'To review' },
    { key: 'refunds', label: 'Refunds' },
    { key: 'cancelled', label: 'Cancelled' },
] as const;

export function OrderStatusTabs({
    counts,
    activeTab,
}: {
    counts: OrderHubCounts;
    activeTab: string;
}) {
    return (
        <div className="scrollbar-hide -mx-4 flex gap-1 overflow-x-auto border-b border-gray-100 px-4">
            {orderTabs.map(({ key, label }) => {
                const count = counts[key];
                const active = activeTab === key;
                return (
                    <button
                        key={key}
                        type="button"
                        onClick={() =>
                            router.get(route('orders.index'), key === 'all' ? {} : { tab: key }, {
                                preserveState: true,
                            })
                        }
                        className={`shrink-0 border-b-2 px-3 py-3 text-sm font-medium transition-colors ${
                            active
                                ? 'border-orange-500 text-orange-600'
                                : 'border-transparent text-gray-500 hover:text-gray-800'
                        }`}
                    >
                        {label}
                        {count > 0 && <span className="ml-1 text-gray-400">({count})</span>}
                    </button>
                );
            })}
        </div>
    );
}

export function orderStatusMessage(order: {
    payment_status: string;
    status: string;
    items?: { status: string; driver_phone?: string | null; vehicle_number?: string | null }[];
}): string {
    if (order.payment_status === 'pending') {
        return 'Waiting for payment';
    }
    if (order.status === 'shipped') {
        const item = order.items?.find((i) => i.driver_phone || i.vehicle_number);
        if (item?.driver_phone) {
            return `On the way · Driver ${item.driver_phone}${item.vehicle_number ? ` · ${item.vehicle_number}` : ''}`;
        }
        return 'Out for delivery';
    }
    if (order.status === 'awaiting_confirmation') {
        return 'Delivered — tap Confirm delivery when you receive your item';
    }
    if (order.status === 'delivered') {
        return 'Order completed';
    }
    if (order.status === 'packed') {
        return 'Seller is packing your order';
    }
    if (order.status === 'processing' || order.status === 'pending') {
        return 'Seller is preparing your order';
    }
    if (order.status === 'refunded' || order.payment_status === 'refunded') {
        return 'Refund processed';
    }
    if (order.status === 'cancelled') {
        return 'Order cancelled';
    }
    return 'Processing your order';
}

export type { OrderHubCounts };
