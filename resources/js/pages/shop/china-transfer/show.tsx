import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type TimelineItem = { key: string; label: string; done: boolean; current: boolean; failed: boolean };

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    quote: {
        ghs_amount: number;
        ghs_per_rmb: number;
        rmb_amount: number;
        fee_ghs: number;
        total_payable_ghs: number;
        breakdown: Record<string, string>;
    };
    rejection_reason: string | null;
    rmb_sent_amount: number | null;
    rmb_transfer_ref: string | null;
    can_cancel: boolean;
    timeline: TimelineItem[];
    fields: { id: number; label: string; value: string | null; file_url: string | null; group: string }[];
    proofs: { id: number; type: string; url: string; original_name: string | null; note: string | null }[];
    created_at: string | null;
};

interface Props {
    transfer: Transfer;
}

export default function ChinaTransferShow({ transfer: initial }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [transfer, setTransfer] = useState(initial);

    useEffect(() => {
        setTransfer(initial);
    }, [initial]);

    useEffect(() => {
        if (['completed', 'cancelled', 'payment_rejected', 'transfer_failed', 'refunded'].includes(transfer.status)) {
            return;
        }
        const timer = window.setInterval(() => {
            fetch(`${route('wallet.china-transfer.show', transfer.id)}?json=1`, {
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

    const rmbProofs = transfer.proofs.filter((p) => p.type === 'rmb_sent');

    return (
        <ShopLayout hideFlash>
            <Head title={transfer.reference} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <Link href={route('wallet.china-transfer.index')} className="text-sm font-semibold text-orange-600">
                    ← Transfers
                </Link>
                {(flash.success || flash.error) && (
                    <p className={`mt-3 rounded-xl px-4 py-3 text-sm ${flash.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </p>
                )}
                <h1 className="mt-3 text-2xl font-black text-gray-900">{transfer.reference}</h1>
                <p className="mt-1 text-sm font-semibold text-orange-700">{transfer.status_label}</p>

                <div className="mt-5 rounded-2xl border border-gray-200 bg-white p-4">
                    <dl className="space-y-1.5 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-500">GHS paid</dt>
                            <dd className="font-semibold">{formatPrice(transfer.quote.total_payable_ghs)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Exchange rate</dt>
                            <dd>1 RMB = GH₵{transfer.quote.ghs_per_rmb.toFixed(4)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">RMB amount</dt>
                            <dd>¥{transfer.quote.rmb_amount.toFixed(2)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Fee</dt>
                            <dd>{formatPrice(transfer.quote.fee_ghs)}</dd>
                        </div>
                    </dl>
                </div>

                <ol className="mt-6 space-y-3">
                    {transfer.timeline.map((step) => (
                        <li key={step.key} className="flex items-start gap-3">
                            <span
                                className={`mt-0.5 h-3 w-3 rounded-full ${
                                    step.current ? 'bg-orange-500' : step.done ? 'bg-emerald-500' : 'bg-gray-300'
                                }`}
                            />
                            <span className={step.current ? 'font-bold text-gray-900' : 'text-gray-600'}>{step.label}</span>
                        </li>
                    ))}
                </ol>

                {transfer.rejection_reason && (
                    <p className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{transfer.rejection_reason}</p>
                )}

                {rmbProofs.length > 0 && (
                    <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <h2 className="font-bold text-emerald-900">RMB sent — proof</h2>
                        {transfer.rmb_sent_amount != null && (
                            <p className="mt-1 text-sm">¥{Number(transfer.rmb_sent_amount).toFixed(2)}</p>
                        )}
                        {transfer.rmb_transfer_ref && <p className="text-sm">Ref: {transfer.rmb_transfer_ref}</p>}
                        <div className="mt-3 space-y-2">
                            {rmbProofs.map((proof) => (
                                <a key={proof.id} href={proof.url} target="_blank" rel="noreferrer" className="block text-sm font-semibold text-orange-700">
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
                                        <a href={field.file_url} target="_blank" rel="noreferrer" className="font-semibold text-orange-700">
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
                            if (confirm('Cancel this transfer?')) {
                                router.post(route('wallet.china-transfer.cancel', transfer.id));
                            }
                        }}
                    >
                        Cancel transfer
                    </Button>
                )}
            </div>
        </ShopLayout>
    );
}
