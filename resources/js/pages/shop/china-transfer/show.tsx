import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type TimelineItem = { key: string; label: string; done: boolean; current: boolean; failed: boolean };

type TransferField = {
    id: number;
    name?: string | null;
    label: string;
    type?: string | null;
    group?: string | null;
    value: string | null;
    file_url: string | null;
};

type WalletReceipt = {
    currency: string;
    amount: number;
    balance_before?: number | null;
    balance_after?: number | null;
    rmb_before?: number | null;
    rmb_after?: number | null;
    debited_at?: string | null;
};

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    funding_source?: string;
    funding_source_label?: string;
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
    fields: TransferField[];
    proofs: { id: number; type: string; url: string; original_name: string | null; note: string | null; created_at?: string | null }[];
    created_at: string | null;
    sent_at?: string | null;
    completed_at?: string | null;
    wallet_receipt?: WalletReceipt | null;
};

interface Props {
    transfer: Transfer;
}

const terminal = (status: string) =>
    ['completed', 'cancelled', 'payment_rejected', 'transfer_failed', 'refunded'].includes(status);

function formatWhen(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function isQrField(field: TransferField): boolean {
    if (!field.file_url) return false;
    const blob = `${field.name ?? ''} ${field.label ?? ''}`.toLowerCase();
    // Only real QR uploads (e.g. alipay_qr) — not payment screenshots / proofs.
    return blob.includes('qr');
}

async function downloadImage(url: string, filename: string) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('download failed');
    const blob = await res.blob();
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(objectUrl);
}

function ReceiptRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex gap-3 rounded-xl bg-gray-50 px-3 py-2.5 text-sm">
            <dt className="w-28 shrink-0 text-gray-500">{label}</dt>
            <dd className="font-semibold text-gray-900">{value}</dd>
        </div>
    );
}

export default function ChinaTransferShow({ transfer: initial }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [transfer, setTransfer] = useState(initial);

    useEffect(() => {
        setTransfer(initial);
    }, [initial]);

    useEffect(() => {
        if (terminal(transfer.status)) return;
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

    const qrFields = transfer.fields.filter(isQrField);
    const textFields = transfer.fields.filter((field) => {
        if (isQrField(field)) return false;
        const group = (field.group ?? '').toLowerCase();
        return !['payment', 'payment_proof', 'proof'].includes(group);
    });
    const rmbProofs = transfer.proofs.filter((p) => p.type === 'rmb_sent');
    const receipt = transfer.wallet_receipt;
    const completed = transfer.status === 'completed';
    const displayWhen = completed
        ? formatWhen(transfer.completed_at ?? transfer.sent_at ?? transfer.created_at)
        : formatWhen(transfer.created_at);

    const downloadReceipt = () => {
        const lines = [
            'CityShop — Buy RMB Receipt',
            `Reference: ${transfer.reference}`,
            `Status: ${transfer.status_label}`,
            `Date: ${displayWhen}`,
            transfer.funding_source === 'rmb_wallet'
                ? `Amount: ¥${transfer.quote.rmb_amount.toFixed(2)}`
                : `GHS paid: ${formatPrice(transfer.quote.total_payable_ghs)} → ¥${transfer.quote.rmb_amount.toFixed(2)}`,
            receipt?.balance_before != null ? `GHS before: ${formatPrice(receipt.balance_before)}` : '',
            receipt?.balance_after != null ? `GHS after: ${formatPrice(receipt.balance_after)}` : '',
            transfer.rmb_transfer_ref ? `Transfer ref: ${transfer.rmb_transfer_ref}` : '',
        ]
            .filter(Boolean)
            .join('\n');
        const blob = new Blob([lines], { type: 'text/plain;charset=utf-8' });
        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = objectUrl;
        anchor.download = `CityShop_RMB_${transfer.reference}.txt`;
        anchor.click();
        URL.revokeObjectURL(objectUrl);
    };

    return (
        <ShopLayout hideFlash>
            <Head title={transfer.reference} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <div className="flex items-center justify-between gap-3">
                    <Link href={route('wallet.china-transfer.index')} className="text-sm font-semibold text-orange-600">
                        ← Back
                    </Link>
                    {!terminal(transfer.status) && (
                        <span className="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700">
                            Auto refresh
                        </span>
                    )}
                </div>
                {(flash.success || flash.error) && (
                    <p className={`mt-3 rounded-xl px-4 py-3 text-sm ${flash.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </p>
                )}

                {!terminal(transfer.status) && (
                    <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                        Your RMB transfer request is {transfer.status_label}. Ref {transfer.reference}
                    </p>
                )}

                <div className="mt-4 space-y-4">
                    <div className="rounded-2xl border border-gray-200 bg-white p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-3xl font-black text-gray-900">
                                    {transfer.funding_source === 'rmb_wallet'
                                        ? `¥${transfer.quote.rmb_amount.toFixed(2)}`
                                        : formatPrice(transfer.quote.total_payable_ghs)}
                                </p>
                                <p className="mt-1 text-sm font-semibold text-gray-500">
                                    → ¥{transfer.quote.rmb_amount.toFixed(2)} to Alipay
                                </p>
                                {transfer.funding_source_label && (
                                    <p className="mt-2 text-sm font-bold text-teal-700">{transfer.funding_source_label}</p>
                                )}
                            </div>
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-bold ${
                                    completed
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : terminal(transfer.status)
                                          ? 'bg-red-100 text-red-800'
                                          : 'bg-orange-100 text-orange-800'
                                }`}
                            >
                                {transfer.status_label}
                            </span>
                        </div>
                        <dl className="mt-4 space-y-2">
                            <ReceiptRow label="Reference" value={transfer.reference} />
                            <ReceiptRow label="Date & time" value={displayWhen} />
                            {receipt && transfer.funding_source === 'rmb_wallet' ? (
                                <>
                                    {receipt.rmb_before != null && (
                                        <ReceiptRow label="RMB before" value={`¥${Number(receipt.rmb_before).toFixed(2)}`} />
                                    )}
                                    {receipt.rmb_after != null && (
                                        <ReceiptRow label="RMB after" value={`¥${Number(receipt.rmb_after).toFixed(2)}`} />
                                    )}
                                </>
                            ) : receipt ? (
                                <>
                                    {receipt.balance_before != null && (
                                        <ReceiptRow label="GHS before" value={formatPrice(receipt.balance_before)} />
                                    )}
                                    {receipt.balance_after != null && (
                                        <ReceiptRow label="GHS after" value={formatPrice(receipt.balance_after)} />
                                    )}
                                </>
                            ) : null}
                            <ReceiptRow
                                label="Exchange rate"
                                value={transfer.quote.breakdown?.rate ?? `1 RMB = GH₵${transfer.quote.ghs_per_rmb.toFixed(4)}`}
                            />
                            {transfer.funding_source !== 'rmb_wallet' && (
                                <ReceiptRow label="Fee" value={formatPrice(transfer.quote.fee_ghs)} />
                            )}
                        </dl>
                    </div>

                    <div className="rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Progress</h2>
                        <ol className="mt-3 space-y-2">
                            {transfer.timeline.map((step) => (
                                <li key={step.key} className="flex items-center gap-3 text-sm">
                                    <span
                                        className={`h-3 w-3 rounded-full ${
                                            step.current ? 'bg-orange-500' : step.done ? 'bg-emerald-500' : 'bg-gray-300'
                                        }`}
                                    />
                                    <span className={step.current ? 'font-bold text-gray-900' : 'text-gray-600'}>{step.label}</span>
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>

                {transfer.rejection_reason && (
                    <p className="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{transfer.rejection_reason}</p>
                )}

                {qrFields.map((field) => (
                    <div key={field.id} className="mt-4 rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Your Alipay QR</h2>
                        <p className="mt-1 text-sm text-gray-500">QR code you submitted for this transfer.</p>
                        <img
                            src={field.file_url!}
                            alt={field.label}
                            className="mx-auto mt-4 max-h-72 rounded-xl border border-gray-100 bg-slate-50 object-contain p-4"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            className="mt-3 w-full"
                            onClick={() =>
                                downloadImage(field.file_url!, `buyer_qr_${transfer.reference}.jpg`).catch(() =>
                                    window.open(field.file_url!, '_blank'),
                                )
                            }
                        >
                            Download QR
                        </Button>
                    </div>
                ))}

                {textFields.length > 0 && (
                    <div className="mt-4 rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Recipient details</h2>
                        <dl className="mt-3 space-y-2">
                            {textFields.map((field) => (
                                <ReceiptRow key={field.id} label={field.label} value={field.value || '—'} />
                            ))}
                        </dl>
                    </div>
                )}

                {rmbProofs.length > 0 && (
                    <div className="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <h2 className="font-bold text-emerald-900">🧾 Payment proof</h2>
                        {transfer.rmb_sent_amount != null && (
                            <p className="mt-2 text-sm font-semibold">RMB sent: ¥{Number(transfer.rmb_sent_amount).toFixed(2)}</p>
                        )}
                        {transfer.rmb_transfer_ref && <p className="text-sm">Ref: {transfer.rmb_transfer_ref}</p>}
                        {transfer.sent_at && <p className="text-sm text-emerald-800">Sent: {formatWhen(transfer.sent_at)}</p>}
                        {completed && transfer.completed_at && (
                            <p className="text-sm text-emerald-800">Completed: {formatWhen(transfer.completed_at)}</p>
                        )}
                        <div className="mt-3 space-y-4">
                            {rmbProofs.map((proof) => (
                                <div key={proof.id}>
                                    <img
                                        src={proof.url}
                                        alt={proof.original_name || 'Proof'}
                                        className="max-h-80 w-full rounded-xl border border-emerald-100 bg-white object-contain"
                                    />
                                    <p className="mt-2 text-sm font-semibold text-emerald-900">{proof.original_name || 'Proof'}</p>
                                    {proof.created_at && (
                                        <p className="text-xs text-emerald-800">{formatWhen(proof.created_at)}</p>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="mt-2 w-full"
                                        onClick={() =>
                                            downloadImage(proof.url, `rmb_proof_${transfer.reference}.jpg`).catch(() =>
                                                window.open(proof.url, '_blank'),
                                            )
                                        }
                                    >
                                        Download proof
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {completed && (
                    <Button type="button" className="mt-6 w-full bg-orange-600 hover:bg-orange-700" onClick={() => downloadReceipt().catch(() => undefined)}>
                        Download receipt
                    </Button>
                )}

                {transfer.can_cancel && (
                    <Button
                        variant="outline"
                        className="mt-3 w-full"
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
