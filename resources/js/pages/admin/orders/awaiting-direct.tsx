import { Head, Link, router } from '@inertiajs/react';

import AdminOrderTabs from '@/components/admin/admin-order-tabs';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated, productImageUrl } from '@/types/marketplace';

interface AwaitingDirectOrder {
    id: number;
    order_number: string;
    total: number;
    status: string;
    payment_status: string;
    created_at: string | null;
    hours_waiting: number | null;
    claim_status: 'claimed' | 'awaiting_claim';
    direct_payment_reference: string | null;
    direct_payment_proof_path: string | null;
    direct_payment_rejection_reason: string | null;
    checkout_number?: string | null;
    buyer: { id?: number; name?: string; email?: string; mobile?: string };
    seller: { id?: number; name?: string; store?: string | null };
    payment_method: {
        type?: string | null;
        account_name?: string | null;
        account_number?: string | null;
        network?: string | null;
        bank_name?: string | null;
    } | null;
    items_count: number;
}

interface Props {
    orders: Paginated<AwaitingDirectOrder>;
    claim: 'all' | 'awaiting_claim' | 'claimed';
    count: number;
    awaiting_claim_count: number;
    claimed_count: number;
}

function formatWait(hours: number | null): string {
    if (hours == null) return '—';
    if (hours < 48) return `${hours}h`;
    const days = Math.floor(hours / 24);
    const rem = hours % 24;
    return rem > 0 ? `${days}d ${rem}h` : `${days}d`;
}

function paymentLabel(method: AwaitingDirectOrder['payment_method']): string {
    if (!method) return 'Pay to seller';
    if (method.bank_name || method.type === 'bank') {
        return method.bank_name || 'Bank transfer';
    }
    return method.network ? `${method.network} MoMo` : 'Mobile Money';
}

export default function AdminAwaitingDirectOrders({
    orders,
    claim,
    count,
    awaiting_claim_count,
    claimed_count,
}: Props) {
    const setClaim = (next: Props['claim']) => {
        router.get(
            route('admin.orders.awaiting-direct'),
            { claim: next === 'all' ? undefined : next },
            { preserveState: true },
        );
    };

    return (
        <AdminLayout title="Awaiting direct payment" active="orders-awaiting-direct">
            <Head title="Awaiting direct payment" />

            <div className="mb-6 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
                <p className="font-semibold">Pay-to-seller orders not marked paid yet</p>
                <p className="mt-1">
                    These appear for the buyer as soon as they continue to payment. Sellers only see an order after the buyer
                    submits a transaction ID or proof. This list is for ops visibility — CityShop is not holding these funds.
                </p>
                <p className="mt-2 font-medium">{count} awaiting · {awaiting_claim_count} no proof yet · {claimed_count} claimed</p>
            </div>

            <AdminOrderTabs active="awaiting-direct" />

            <div className="mb-4 flex flex-wrap gap-2">
                {(
                    [
                        { key: 'all' as const, label: `All (${count})` },
                        { key: 'awaiting_claim' as const, label: `No proof yet (${awaiting_claim_count})` },
                        { key: 'claimed' as const, label: `Claimed (${claimed_count})` },
                    ]
                ).map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        onClick={() => setClaim(tab.key)}
                        className={`rounded-full px-3 py-1 text-sm font-medium ${
                            claim === tab.key
                                ? 'bg-sky-600 text-white'
                                : 'bg-white text-gray-600 shadow-sm ring-1 ring-gray-200'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {orders.data.length === 0 ? (
                <div className="rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center text-sm text-gray-500">
                    No awaiting direct payments in this filter.
                </div>
            ) : (
                <div className="space-y-4">
                    {orders.data.map((order) => (
                        <div key={order.id} className="rounded-xl bg-white p-5 shadow-sm">
                            <div className="flex flex-col gap-3 sm:flex-row sm:justify-between">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={route('admin.orders.show', order.id)}
                                            className="font-semibold text-orange-600 hover:underline"
                                        >
                                            {order.order_number}
                                        </Link>
                                        {order.checkout_number && (
                                            <span className="text-sm text-gray-500">{order.checkout_number}</span>
                                        )}
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                order.claim_status === 'claimed'
                                                    ? 'bg-amber-100 text-amber-800'
                                                    : 'bg-gray-100 text-gray-700'
                                            }`}
                                        >
                                            {order.claim_status === 'claimed' ? 'Buyer claimed payment' : 'Waiting for buyer proof'}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-600">
                                        {formatPrice(order.total)} · {order.items_count} item{order.items_count === 1 ? '' : 's'} ·{' '}
                                        {paymentLabel(order.payment_method)}
                                    </p>
                                    {order.payment_method?.account_number && (
                                        <p className="mt-1 text-xs text-gray-500">
                                            {order.payment_method.account_name} · {order.payment_method.account_number}
                                        </p>
                                    )}
                                    <p className="mt-2 text-xs text-gray-500">
                                        Buyer: {order.buyer.name ?? '—'}
                                        {order.buyer.mobile ? ` · ${order.buyer.mobile}` : ''}
                                    </p>
                                    <p className="mt-0.5 text-xs text-gray-500">
                                        Seller: {order.seller.store || order.seller.name || '—'}
                                    </p>
                                    {order.direct_payment_reference && (
                                        <p className="mt-2 text-xs text-gray-700">
                                            Ref: <span className="font-mono">{order.direct_payment_reference}</span>
                                        </p>
                                    )}
                                    {order.direct_payment_rejection_reason && (
                                        <p className="mt-1 text-xs text-red-600">
                                            Last rejection: {order.direct_payment_rejection_reason}
                                        </p>
                                    )}
                                </div>
                                <div className="shrink-0 text-left sm:text-right">
                                    <p className="text-sm font-bold text-sky-700">Open {formatWait(order.hours_waiting)}</p>
                                    {order.created_at && (
                                        <p className="mt-1 text-xs text-gray-400">
                                            Created {new Date(order.created_at).toLocaleString()}
                                        </p>
                                    )}
                                    {order.direct_payment_proof_path && (
                                        <a
                                            href={productImageUrl(order.direct_payment_proof_path)}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="mt-2 inline-block text-xs font-medium text-orange-600 hover:underline"
                                        >
                                            View proof →
                                        </a>
                                    )}
                                    <div className="mt-3">
                                        <Link
                                            href={route('admin.orders.show', order.id)}
                                            className="text-sm font-medium text-orange-600 hover:underline"
                                        >
                                            Open order →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {orders.last_page > 1 && (
                <div className="mt-6 flex justify-center gap-2">
                    {orders.prev_page_url && (
                        <Link href={orders.prev_page_url} className="rounded-lg bg-white px-4 py-2 text-sm shadow-sm">
                            Previous
                        </Link>
                    )}
                    <span className="px-3 py-2 text-sm text-gray-500">
                        Page {orders.current_page} of {orders.last_page}
                    </span>
                    {orders.next_page_url && (
                        <Link href={orders.next_page_url} className="rounded-lg bg-white px-4 py-2 text-sm shadow-sm">
                            Next
                        </Link>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
