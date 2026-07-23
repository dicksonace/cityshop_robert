import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface PendingFundItem {
    id: number;
    product_name: string;
    quantity: number;
    seller_amount: number;
    commission_amount: number;
    status: string;
    funds_release_status: string | null;
    funds_release_notes: string | null;
    funds_released_at: string | null;
    updated_at: string | null;
    order: {
        id: number;
        order_number: string;
        payment_channel: string | null;
        shipping_cost: number;
        total: number;
        buyer: { id: number; name: string; email: string; mobile: string | null } | null;
    } | null;
    seller: { id: number; name: string; email: string; mobile: string | null } | null;
    reviewer: { id: number; name: string } | null;
}

interface Props {
    items: Paginated<PendingFundItem>;
    status: string;
    counts: { pending: number; held: number; released: number };
}

function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function PendingFundsIndex({ items, status, counts }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [busyId, setBusyId] = useState<number | null>(null);
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [notes, setNotes] = useState('');

    const setFilter = (next: string) => {
        router.get(route('admin.pending-funds.index'), { status: next === 'pending' ? undefined : next }, { preserveState: true });
    };

    const approve = (id: number) => {
        setBusyId(id);
        router.post(
            route('admin.pending-funds.approve', id),
            { admin_notes: notes || undefined },
            { onFinish: () => { setBusyId(null); setNotes(''); } },
        );
    };

    const reject = () => {
        if (!rejectId) return;
        setBusyId(rejectId);
        router.post(
            route('admin.pending-funds.reject', rejectId),
            { admin_notes: notes },
            {
                onFinish: () => {
                    setBusyId(null);
                    setRejectId(null);
                    setNotes('');
                },
            },
        );
    };

    return (
        <AdminLayout title="Pending Funds" active="pending-funds">
            <Head title="Pending Funds" />

            <div className="mb-4">
                <h1 className="text-lg font-bold text-gray-900">Pending fund releases</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Buyer confirmed delivery. You can release seller earnings to Available anytime — including held items — or reject to hold and open a dispute.
                </p>
            </div>

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

            <div className="mb-4 flex flex-wrap gap-2">
                {(
                    [
                        ['pending', `Pending (${counts.pending})`],
                        ['held', `Held (${counts.held})`],
                        ['released', `Released (${counts.released})`],
                        ['all', 'All'],
                    ] as const
                ).map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setFilter(key)}
                        className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                            status === key ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 shadow-sm'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {items.data.length === 0 ? (
                <p className="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm">No items in this view.</p>
            ) : (
                <div className="space-y-4">
                    {items.data.map((item) => {
                        const canApprove = item.funds_release_status === 'pending' || item.funds_release_status === 'held';
                        const canReject = item.funds_release_status === 'pending';

                        return (
                            <div key={item.id} className="rounded-xl bg-white p-5 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-gray-900">{item.product_name}</p>
                                        <p className="mt-0.5 text-sm text-gray-500">
                                            Qty {item.quantity} · Seller earns {formatPrice(item.seller_amount)}
                                        </p>
                                        {item.order && (
                                            <p className="mt-1 text-sm text-gray-600">
                                                Order{' '}
                                                <Link href={route('admin.orders.show', item.order.id)} className="text-blue-600 hover:underline">
                                                    {item.order.order_number}
                                                </Link>
                                                {' · '}
                                                Confirmed {formatDate(item.updated_at)}
                                            </p>
                                        )}
                                    </div>
                                    <span
                                        className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${
                                            item.funds_release_status === 'pending'
                                                ? 'bg-amber-100 text-amber-800'
                                                : item.funds_release_status === 'held'
                                                    ? 'bg-red-100 text-red-700'
                                                    : 'bg-emerald-100 text-emerald-700'
                                        }`}
                                    >
                                        {item.funds_release_status}
                                    </span>
                                </div>

                                <div className="mt-3 grid gap-2 text-sm text-gray-600 sm:grid-cols-2">
                                    <p>
                                        <span className="font-medium text-gray-800">Buyer:</span>{' '}
                                        {item.order?.buyer?.name ?? '—'}
                                        {item.order?.buyer?.mobile ? ` · ${item.order.buyer.mobile}` : ''}
                                    </p>
                                    <p>
                                        <span className="font-medium text-gray-800">Seller:</span>{' '}
                                        {item.seller?.name ?? '—'}
                                        {item.seller?.mobile ? ` · ${item.seller.mobile}` : ''}
                                    </p>
                                </div>

                                {item.funds_release_notes && (
                                    <p className="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        Notes: {item.funds_release_notes}
                                    </p>
                                )}

                                {item.funds_release_status === 'held' && (
                                    <p className="mt-3 text-sm text-amber-800">
                                        Funds are held (often after a dispute). You can still Approve anytime to release to the seller’s Available balance.
                                    </p>
                                )}

                                {canApprove && (
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <Button
                                            className="bg-emerald-600 hover:bg-emerald-700"
                                            disabled={busyId === item.id}
                                            onClick={() => approve(item.id)}
                                        >
                                            <Check className="mr-1.5 h-4 w-4" />
                                            Approve — release to Available
                                        </Button>
                                        {canReject && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="border-red-200 text-red-600 hover:bg-red-50"
                                                disabled={busyId === item.id}
                                                onClick={() => {
                                                    setRejectId(item.id);
                                                    setNotes('');
                                                }}
                                            >
                                                <X className="mr-1.5 h-4 w-4" />
                                                Reject — hold & dispute
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {items.next_page_url || items.prev_page_url ? (
                <div className="mt-4 flex justify-center gap-2">
                    {items.prev_page_url && (
                        <Button variant="outline" onClick={() => router.get(items.prev_page_url!)}>
                            Previous
                        </Button>
                    )}
                    {items.next_page_url && (
                        <Button variant="outline" onClick={() => router.get(items.next_page_url!)}>
                            Next
                        </Button>
                    )}
                </div>
            ) : null}

            {rejectId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                        <h3 className="font-semibold text-gray-900">Hold funds & open dispute</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Earnings stay in seller Pending balance. A dispute is opened for admin follow-up.
                        </p>
                        <textarea
                            className="mt-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                            rows={4}
                            placeholder="Why are you holding this release?"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                        />
                        <div className="mt-4 flex gap-2">
                            <Button
                                className="flex-1 bg-red-600 hover:bg-red-700"
                                disabled={notes.trim().length < 5 || busyId === rejectId}
                                onClick={reject}
                            >
                                Confirm hold
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setRejectId(null);
                                    setNotes('');
                                }}
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
