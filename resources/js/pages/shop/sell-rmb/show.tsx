import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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

export default function SellRmbShow({ transfer: initial }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [transfer, setTransfer] = useState(initial);

    useEffect(() => {
        setTransfer(initial);
    }, [initial]);

    useEffect(() => {
        if (['completed', 'cancelled', 'rejected', 'failed'].includes(transfer.status)) {
            return;
        }
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
    }, [transfer.id, transfer.status]);

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

    return (
        <ShopLayout hideFlash>
            <Head title={transfer.reference} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <Link href={route('wallet.sell-rmb.index')} className="text-sm font-semibold text-emerald-700">
                    ← Sell RMB
                </Link>
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
                <p className="mt-1 text-sm font-semibold text-emerald-700">{transfer.status_label}</p>

                <div className="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                    <dl className="space-y-1.5 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-500">RMB sold</dt>
                            <dd className="font-semibold">¥{transfer.quote.rmb_amount.toFixed(2)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Buying rate</dt>
                            <dd>
                                1 RMB = GH₵
                                {(
                                    transfer.quote.ghs_per_rmb ??
                                    transfer.quote.usd_per_rmb * transfer.quote.ghs_per_usd
                                ).toFixed(4)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Fee</dt>
                            <dd>{formatUsd(transfer.quote.fee_usd)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Expected payout</dt>
                            <dd className="font-semibold">{expectedPayout}</dd>
                        </div>
                        {paidLabel && (
                            <div className="flex justify-between border-t pt-2 font-bold">
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

                <ol className="mt-6 space-y-3">
                    {transfer.timeline.map((step) => (
                        <li key={step.key} className="flex items-start gap-3">
                            <span
                                className={`mt-0.5 h-3 w-3 rounded-full ${
                                    step.failed
                                        ? 'bg-red-500'
                                        : step.current
                                          ? 'bg-emerald-500'
                                          : step.done
                                            ? 'bg-emerald-500'
                                            : 'bg-gray-300'
                                }`}
                            />
                            <span className={step.current ? 'font-bold text-gray-900' : 'text-gray-600'}>
                                {step.label}
                            </span>
                        </li>
                    ))}
                </ol>

                {transfer.rejection_reason && (
                    <p className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
                        {transfer.rejection_reason}
                    </p>
                )}

                {payoutProofs.length > 0 && (
                    <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <h2 className="font-bold text-emerald-900">Payout proof</h2>
                        <div className="mt-3 space-y-2">
                            {payoutProofs.map((proof) => (
                                <a
                                    key={proof.id}
                                    href={proof.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block text-sm font-semibold text-emerald-800"
                                >
                                    {proof.original_name || 'View proof'}
                                </a>
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
                                        <a
                                            href={field.file_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-semibold text-emerald-700"
                                        >
                                            View file
                                        </a>
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
        </ShopLayout>
    );
}
