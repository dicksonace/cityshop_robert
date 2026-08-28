import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import {
    RmbAutoRefreshChip,
    RmbTransferStatusBadge,
    rmbTransferStatusTone,
} from '@/components/china/rmb-transfer-status-badge';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

type TimelineItem = { key: string; label: string; done: boolean; current: boolean; failed: boolean };

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    quote: {
        rmb_amount: number;
        usd_per_rmb: number;
        ghs_per_usd: number;
        ghs_per_rmb?: number;
        fee_usd: number;
        usd_payout: number;
        ghs_payout: number;
        payout_currency: string;
        payout_amount: number;
        breakdown: Record<string, string>;
    };
    rejection_reason: string | null;
    payout_amount: number | null;
    payout_ref: string | null;
    payout_channel: string | null;
    can_cancel: boolean;
    timeline: TimelineItem[];
    fields: { id: number; label: string; value: string | null; file_url: string | null; group: string }[];
    proofs: { id: number; type: string; url: string; original_name: string | null; note: string | null }[];
    created_at: string | null;
    completed_at: string | null;
};

interface Props {
    transfer: Transfer;
}

function formatUsd(n: number) {
    return `$${n.toFixed(2)}`;
}

function formatGhs(n: number) {
    return `GH₵${n.toFixed(2)}`;
}

function formatWhen(raw: string | null): string {
    if (!raw) return '—';
    try {
        return new Date(raw).toLocaleString();
    } catch {
        return raw;
    }
}

const TERMINAL = ['completed', 'cancelled', 'rejected', 'failed'];

export default function SellRmbShow({ transfer: initial }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [transfer, setTransfer] = useState(initial);
    const [proofPreview, setProofPreview] = useState<string | null>(null);

    useEffect(() => {
        setTransfer(initial);
    }, [initial]);

    const autoRefresh = !TERMINAL.includes(transfer.status);

    useEffect(() => {
        if (!autoRefresh) return;
        const timer = window.setInterval(() => {
            fetch(`${route('wallet.sell-rmb.show', transfer.id)}?json=1`, {
                headers: { Accept: 'application/json' },
            })
                .then((res) => res.json())
                .then((body) => {
                    if (body?.data) setTransfer(body.data);
                })
                .catch(() => undefined);
        }, 8000);
        return () => window.clearInterval(timer);
    }, [transfer.id, transfer.status, autoRefresh]);

    const payoutProofs = transfer.proofs.filter((p) => p.type === 'payout_sent');
    const expectedPayout =
        transfer.quote.payout_currency === 'ghs'
            ? formatGhs(transfer.quote.ghs_payout)
            : formatUsd(transfer.quote.usd_payout);
    const paidLabel =
        transfer.payout_amount != null
            ? transfer.quote.payout_currency === 'ghs'
                ? formatGhs(transfer.payout_amount)
                : formatUsd(transfer.payout_amount)
            : null;
    const tone = rmbTransferStatusTone(transfer.status);

    return (
        <ShopLayout hideFlash>
            <Head title={transfer.reference} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <div className="flex items-center justify-between gap-3">
                    <Link href={route('wallet.sell-rmb.index')} className="text-sm font-semibold text-emerald-700">
                        ← Sell RMB
                    </Link>
                    {autoRefresh && <RmbAutoRefreshChip />}
                </div>
                {(flash.success || flash.error) && (
                    <p
                        className={`mt-3 rounded-xl px-4 py-3 text-sm ${
                            flash.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </p>
                )}
                <h1 className="mt-3 text-2xl font-black text-gray-900">{transfer.reference}</h1>

                {!TERMINAL.includes(transfer.status) && (
                    <div className={`mt-4 flex items-center gap-3 rounded-xl border px-4 py-3 ${tone.badge}`}>
                        <RmbTransferStatusBadge status={transfer.status} label={transfer.status_label} />
                        <p className="text-sm font-medium">We&apos;ll update this page automatically.</p>
                    </div>
                )}

                <div className="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                    <p className="text-3xl font-black text-emerald-700">¥{transfer.quote.rmb_amount.toFixed(2)}</p>
                    <p className="mt-1 text-lg font-bold text-gray-900">Expected payout: {expectedPayout}</p>
                    {transfer.quote.breakdown?.rate && (
                        <p className="mt-2 text-sm text-gray-500">{transfer.quote.breakdown.rate}</p>
                    )}
                    {transfer.status === 'completed' && transfer.completed_at && (
                        <p className="mt-2 text-sm text-gray-500">Completed {formatWhen(transfer.completed_at)}</p>
                    )}
                    <dl className="mt-4 space-y-1.5 border-t pt-4 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Fee</dt>
                            <dd>{formatUsd(transfer.quote.fee_usd)}</dd>
                        </div>
                        {paidLabel && (
                            <div className="flex justify-between font-bold">
                                <dt>Paid</dt>
                                <dd>{paidLabel}</dd>
                            </div>
                        )}
                        {transfer.payout_ref && (
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Payout ref</dt>
                                <dd>{transfer.payout_ref}</dd>
                            </div>
                        )}
                        {transfer.payout_channel && (
                            <div className="flex justify-between">
                                <dt className="text-gray-500">Channel</dt>
                                <dd>{transfer.payout_channel}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                <h2 className="mt-6 text-base font-black text-gray-900">Progress</h2>
                <ol className="mt-3 space-y-2">
                    {transfer.timeline.map((step) => (
                        <li
                            key={step.key}
                            className={`flex items-center gap-3 rounded-xl border px-3 py-2.5 ${
                                step.failed
                                    ? 'border-red-200 bg-red-50'
                                    : step.current
                                      ? 'border-emerald-200 bg-emerald-50'
                                      : step.done
                                        ? 'border-emerald-100 bg-emerald-50/50'
                                        : 'border-gray-200 bg-white'
                            }`}
                        >
                            <span
                                className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white ${
                                    step.failed
                                        ? 'bg-red-500'
                                        : step.done || step.current
                                          ? 'bg-emerald-600'
                                          : 'bg-gray-300'
                                }`}
                            >
                                {step.failed ? '!' : step.done ? '✓' : step.current ? '…' : ''}
                            </span>
                            <span className={step.current ? 'font-bold text-gray-900' : 'text-gray-600'}>
                                {step.label}
                            </span>
                        </li>
                    ))}
                </ol>

                {transfer.rejection_reason && (
                    <p className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{transfer.rejection_reason}</p>
                )}

                {payoutProofs.length > 0 && (
                    <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <h2 className="font-bold text-emerald-900">Payout proof</h2>
                        <div className="mt-3 grid gap-3">
                            {payoutProofs.map((proof) => (
                                <button
                                    key={proof.id}
                                    type="button"
                                    onClick={() => setProofPreview(proof.url)}
                                    className="overflow-hidden rounded-xl border border-emerald-200 bg-white text-left"
                                >
                                    <img
                                        src={proof.url}
                                        alt={proof.original_name || 'Payout proof'}
                                        className="max-h-64 w-full object-contain"
                                    />
                                    <p className="px-3 py-2 text-sm font-semibold text-emerald-800">
                                        {proof.original_name || 'View proof'}
                                    </p>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <div className="mt-6 rounded-2xl border border-gray-200 bg-white p-4">
                    <h2 className="font-bold text-gray-900">Submitted details</h2>
                    <dl className="mt-3 space-y-2 text-sm">
                        {transfer.fields.map((field) => (
                            <div key={field.id}>
                                <dt className="text-gray-500">{field.label}</dt>
                                <dd>
                                    {field.file_url ? (
                                        <button
                                            type="button"
                                            onClick={() => setProofPreview(field.file_url!)}
                                            className="font-semibold text-emerald-700"
                                        >
                                            View file
                                        </button>
                                    ) : (
                                        field.value || '—'
                                    )}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                {transfer.can_cancel && (
                    <Button
                        variant="outline"
                        className="mt-6 w-full"
                        onClick={() => {
                            if (confirm('Cancel this Sell RMB request?')) {
                                router.post(route('wallet.sell-rmb.cancel', transfer.id));
                            }
                        }}
                    >
                        Cancel request
                    </Button>
                )}
            </div>

            {proofPreview && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    onClick={() => setProofPreview(null)}
                    onKeyDown={(e) => e.key === 'Escape' && setProofPreview(null)}
                    role="presentation"
                >
                    <img
                        src={proofPreview}
                        alt="Proof preview"
                        className="max-h-[90vh] max-w-full rounded-lg object-contain"
                        onClick={(e) => e.stopPropagation()}
                    />
                </div>
            )}
        </ShopLayout>
    );
}
