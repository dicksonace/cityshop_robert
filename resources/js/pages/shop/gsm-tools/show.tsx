import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import ShopLayout from '@/layouts/shop-layout';
import { formatPrice } from '@/types/marketplace';

type Reply = { id: number; body: string; admin: string | null; created_at: string | null };
type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    image_url?: string | null;
    price_ghs: number;
    quantity?: number;
    contact_email?: string | null;
    admin_result_note: string | null;
    failure_reason: string | null;
    can_cancel: boolean;
    fields: { name: string; label: string; type?: string; value: string | null }[];
    replies?: Reply[];
};

interface Props {
    order: Order;
}

function displayStatus(status: string): string {
    return status === 'pending' ? 'processing' : status;
}

function CopyReply({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <button
            type="button"
            onClick={async () => {
                await navigator.clipboard.writeText(text);
                setCopied(true);
                window.setTimeout(() => setCopied(false), 1500);
            }}
            className="shrink-0 rounded-lg px-2 py-1 text-[11px] font-extrabold text-orange-700 hover:bg-orange-100"
        >
            {copied ? 'Copied' : 'Copy'}
        </button>
    );
}

function statusClass(status: string): string {
    return {
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-600',
    }[status] ?? 'bg-blue-100 text-blue-800';
}

function statusLabel(status: string, fallback?: string): string {
    if (status === 'processing' || status === 'pending') return 'Processing';
    return fallback || status;
}

export default function GsmToolShow({ order }: Props) {
    const replies = order.replies ?? [];
    const status = displayStatus(order.status);
    const open = order.status === 'pending' || order.status === 'processing';
    const [checking, setChecking] = useState(false);

    const refreshOrder = () => {
        setChecking(true);
        router.reload({
            only: ['order', 'flash'],
            preserveScroll: true,
            onFinish: () => setChecking(false),
        });
    };

    useEffect(() => {
        if (!open) return;
        const timer = window.setInterval(refreshOrder, 4000);
        return () => window.clearInterval(timer);
    }, [open, order.id]);

    return (
        <ShopLayout>
            <Head title={order.reference} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <div className="mb-4 flex items-center justify-between">
                    <button type="button" onClick={() => router.visit(route('gsm-tools.index'))} className="text-sm font-semibold text-orange-600">
                        ← Place order
                    </button>
                    <button type="button" onClick={refreshOrder} className="text-sm font-bold text-slate-500">
                        {checking ? 'Refreshing…' : 'Refresh'}
                    </button>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex items-start gap-3">
                        {order.image_url ? (
                            <img src={order.image_url} alt="" className="h-14 w-14 shrink-0 rounded-2xl bg-slate-950 object-contain" />
                        ) : (
                            <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-50 text-xs font-black text-orange-600">
                                GSM
                            </span>
                        )}
                        <div className="min-w-0 flex-1">
                            <h1 className="text-lg font-black leading-snug text-gray-900">{order.service_name}</h1>
                            <p className="mt-1 text-xs font-semibold text-gray-400">{order.reference}</p>
                            <span className={`mt-2 inline-flex rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wide ${statusClass(status)}`}>
                                {statusLabel(order.status, order.status_label)}
                            </span>
                        </div>
                    </div>
                    <div className="mt-4 rounded-2xl bg-orange-50 px-4 py-3">
                        <p className="text-[10px] font-extrabold uppercase tracking-wider text-orange-700">Paid from wallet</p>
                        <p className="mt-0.5 text-2xl font-black text-orange-950">
                            {formatPrice(order.price_ghs)}
                            {order.quantity && order.quantity > 1 ? ` × ${order.quantity}` : ''}
                        </p>
                        {open ? <p className="mt-1 text-xs font-semibold text-orange-800">Processing now. Watch System Reply below.</p> : null}
                    </div>
                </section>

                <section className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="text-sm font-black text-gray-900">Your details</h2>
                    <div className="mt-3 space-y-3">
                        {order.fields.map((field) => (
                            <div key={field.name}>
                                <p className="text-[11px] font-extrabold uppercase tracking-wide text-gray-400">{field.label}</p>
                                {field.type === 'image' && field.value ? (
                                    <a href={field.value} target="_blank" rel="noreferrer">
                                        <img src={field.value} alt="" className="mt-1 max-h-56 rounded-xl border border-gray-200" />
                                    </a>
                                ) : (
                                    <p className="mt-1 break-all rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm font-semibold text-gray-900">
                                        {field.value || '—'}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                <section className="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center gap-3 bg-slate-900 px-4 py-3">
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-600 text-sm text-white">✉</span>
                        <div className="min-w-0 flex-1">
                            <h2 className="text-sm font-extrabold text-white">System Reply</h2>
                            <p className="text-[11px] font-semibold text-slate-400">Updates automatically</p>
                        </div>
                        {open ? (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/10 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wide text-emerald-300">
                                <span className={`h-1.5 w-1.5 rounded-full bg-emerald-400 ${checking ? 'animate-ping' : 'animate-pulse'}`} />
                                {checking ? 'Refreshing' : 'Live'}
                            </span>
                        ) : null}
                    </div>
                    <div className="p-4">
                        {replies.length === 0 && !(order.status === 'completed' && order.admin_result_note) ? (
                            <div className="flex flex-col items-center py-6 text-center">
                                <span className="h-8 w-8 animate-spin rounded-full border-[3px] border-orange-200 border-t-orange-600" />
                                <p className="mt-3 text-sm font-extrabold text-gray-900">
                                    {open ? 'Waiting for a system reply…' : 'No system reply on this order.'}
                                </p>
                                <p className="mt-1 max-w-xs text-xs text-gray-500">
                                    {open ? 'We check every few seconds. Tap Refresh anytime.' : 'There is nothing more from the system.'}
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {replies.length === 0 && order.admin_result_note ? (
                                    <div className="rounded-xl border border-orange-200 bg-orange-50 px-3 py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="whitespace-pre-wrap text-sm font-semibold text-orange-950">{order.admin_result_note}</p>
                                            <CopyReply text={order.admin_result_note} />
                                        </div>
                                        <p className="mt-1 text-[11px] font-bold text-orange-700">System</p>
                                    </div>
                                ) : null}
                                {replies.map((reply) => (
                                    <div key={reply.id} className="rounded-xl border border-orange-200 bg-orange-50 px-3 py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="whitespace-pre-wrap text-sm font-semibold text-orange-950">{reply.body}</p>
                                            <CopyReply text={reply.body} />
                                        </div>
                                        <p className="mt-1 text-[11px] font-bold text-orange-700">
                                            {reply.admin ?? 'System'}
                                            {reply.created_at ? ` · ${new Date(reply.created_at).toLocaleString()}` : ''}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </section>

                {order.failure_reason ? (
                    <div className="mt-4 rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800">{order.failure_reason}</div>
                ) : null}

            </div>
        </ShopLayout>
    );
}
