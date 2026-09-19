import { Head, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Reply = { id: number; body: string; admin: string | null; created_at: string | null };
type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    price_ghs: number;
    admin_result_note: string | null;
    failure_reason: string | null;
    can_cancel: boolean;
    fields: { name: string; label: string; value: string | null }[];
    replies?: Reply[];
};

interface Props {
    order: Order;
}

function statusClass(status: string): string {
    return {
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-600',
    }[status] ?? 'bg-gray-100 text-gray-700';
}

export default function GsmToolShow({ order }: Props) {
    const { flash } = usePage<SharedData>().props;
    const replies = order.replies ?? [];
    const open = order.status === 'pending' || order.status === 'processing';

    useEffect(() => {
        if (!open) return;
        const timer = window.setInterval(() => {
            router.reload({ only: ['order'] });
        }, 8000);
        return () => window.clearInterval(timer);
    }, [open, order.id]);

    return (
        <ShopLayout>
            <Head title={order.reference} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <button type="button" onClick={() => router.visit(route('gsm-tools.index'))} className="mb-3 text-sm text-orange-600">
                    ← GSM Tools
                </button>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">{order.service_name}</h1>
                        <p className="mt-1 text-sm text-gray-500">{order.reference}</p>
                    </div>
                    <span className={`rounded-full px-3 py-1 text-xs font-extrabold uppercase tracking-wide ${statusClass(order.status)}`}>
                        {order.status_label}
                    </span>
                </div>
                <p className="mt-2 text-sm text-gray-700">Paid {formatPrice(order.price_ghs)}</p>
                {order.status === 'processing' ? (
                    <p className="mt-1 text-sm text-blue-700">Your request is Processing. The admin reply will appear below.</p>
                ) : null}
                {order.status === 'pending' ? (
                    <p className="mt-1 text-sm text-amber-700">Pending — waiting for admin to start Processing.</p>
                ) : null}

                {(flash?.success || flash?.error) && (
                    <div
                        className={`mt-3 rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}
                    >
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div className="mt-5 space-y-2 rounded-2xl border border-gray-100 bg-white p-4">
                    {order.fields.map((field) => (
                        <div key={field.name}>
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">{field.label}</p>
                            <p className="text-sm text-gray-900">{field.value || '—'}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-4 rounded-2xl border border-emerald-100 bg-white p-4">
                    <h2 className="text-sm font-bold text-gray-900">Admin reply</h2>
                    {replies.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500">
                            {order.status === 'completed' && order.admin_result_note
                                ? order.admin_result_note
                                : 'No reply yet. Status will change to Processing, then Completed when done.'}
                        </p>
                    ) : (
                        <div className="mt-3 space-y-2">
                            {replies.map((reply) => (
                                <div key={reply.id} className="rounded-xl bg-emerald-50 px-3 py-2">
                                    <p className="whitespace-pre-wrap text-sm text-emerald-950">{reply.body}</p>
                                    <p className="mt-1 text-[11px] text-emerald-700">
                                        {reply.admin ?? 'Admin'}
                                        {reply.created_at ? ` · ${new Date(reply.created_at).toLocaleString()}` : ''}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {order.failure_reason ? (
                    <div className="mt-4 rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800">{order.failure_reason}</div>
                ) : null}

                {order.can_cancel ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="mt-5 w-full"
                        onClick={() => {
                            if (confirm('Cancel this order and refund your wallet?')) {
                                router.post(route('gsm-tools.orders.cancel', order.id));
                            }
                        }}
                    >
                        Cancel &amp; refund
                    </Button>
                ) : null}
            </div>
        </ShopLayout>
    );
}
