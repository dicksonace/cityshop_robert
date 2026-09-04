import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Field = {
    id: number;
    name?: string | null;
    label: string;
    value: string | null;
    file_url: string | null;
};

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    processing?: boolean;
    status_presentation?: {
        header_title: string;
        header_subtitle: string;
        header_color: string;
        badge_label: string;
        badge_class: string;
    };
    payout_account?: {
        network: string | null;
        number: string | null;
        account_name: string | null;
    };
    quote: {
        rmb_amount: number;
        usd_per_rmb: number;
        ghs_per_usd: number;
        ghs_per_rmb?: number;
        ghs_payout: number;
        usd_payout: number;
        payout_currency: string;
        breakdown: Record<string, string>;
    };
    rejection_reason: string | null;
    payout_amount: number | null;
    can_cancel: boolean;
    fields: Field[];
    proofs: { id: number; type: string; url: string; original_name: string | null }[];
    created_at: string | null;
    submitted_at: string | null;
    completed_at: string | null;
};

type Props = {
    transfer: Transfer;
    onRefresh: () => Promise<void> | void;
    onCancel?: () => void;
    walletHref: string;
    historyHref?: string;
};

const TERMINAL = ['completed', 'cancelled', 'rejected', 'failed'];

function formatGhs(n: number) {
    return `GH₵ ${n.toFixed(2)}`;
}

function formatWhen(raw: string | null): string {
    if (!raw) return '—';
    try {
        return new Date(raw).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    } catch {
        return raw;
    }
}

function payoutFromFields(fields: Field[]) {
    const byName = Object.fromEntries(
        fields.filter((f) => f.name).map((f) => [f.name as string, f.value?.trim() || '']),
    );

    return {
        network: byName.payout_bank_name || '—',
        number: byName.payout_mobile || byName.payout_account_number || '—',
        account_name: byName.payout_name || '—',
    };
}

export function SellRmbStatusView({ transfer, onRefresh, onCancel, walletHref, historyHref }: Props) {
    const [refreshing, setRefreshing] = useState(false);
    const presentation = transfer.status_presentation;
    const payout = transfer.payout_account ?? payoutFromFields(transfer.fields);
    const ghsPerRmb =
        transfer.quote.ghs_per_rmb ?? transfer.quote.usd_per_rmb * transfer.quote.ghs_per_usd;
    const youReceive =
        transfer.quote.payout_currency === 'ghs'
            ? formatGhs(transfer.quote.ghs_payout)
            : `$${transfer.quote.usd_payout.toFixed(2)}`;
    const payoutProofs = transfer.proofs.filter((p) => p.type === 'payout_sent');
    const isProcessing = !TERMINAL.includes(transfer.status);
    const isCompleted = transfer.status === 'completed';
    const isRejected = transfer.status === 'rejected' || transfer.status === 'failed';

    const headerColor = presentation?.header_color ?? '#ef4444';
    const headerTitle = presentation?.header_title ?? 'Awaiting Review';
    const headerSubtitle = presentation?.header_subtitle ?? 'Your RMB sell is being verified';
    const badgeLabel = presentation?.badge_label ?? transfer.status_label;
    const badgeClass = presentation?.badge_class ?? 'bg-yellow-100 text-yellow-800';

    const submittedAt = useMemo(
        () => formatWhen(transfer.submitted_at ?? transfer.created_at),
        [transfer.submitted_at, transfer.created_at],
    );

    async function handleRefresh() {
        setRefreshing(true);
        try {
            await onRefresh();
        } finally {
            setRefreshing(false);
        }
    }

    return (
        <div className="mx-auto w-full max-w-md">
            <div className="overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div className="p-8 text-center text-white transition-colors duration-500" style={{ backgroundColor: headerColor }}>
                    {isCompleted ? (
                        <div>
                            <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white/20">
                                <svg className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h1 className="text-2xl font-bold">Payout Complete!</h1>
                            <p className="mt-1 text-sm text-white/90">GHS sent to your Mobile Money account</p>
                        </div>
                    ) : isRejected ? (
                        <div>
                            <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white/20">
                                <svg className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h1 className="text-2xl font-bold">{headerTitle}</h1>
                            <p className="mt-1 text-sm text-white/90">{headerSubtitle}</p>
                        </div>
                    ) : (
                        <div>
                            <div className="relative mx-auto mb-4 inline-block">
                                <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full border-4 border-white/30">
                                    <div className="h-16 w-16 animate-spin rounded-full border-4 border-white border-t-transparent" />
                                </div>
                                <div className="absolute inset-0 mx-auto h-20 w-20 animate-ping rounded-full border-4 border-white/20" />
                            </div>
                            <h1 className="text-2xl font-bold">{headerTitle}</h1>
                            <p className="mt-1 text-sm text-white/90">{headerSubtitle}</p>
                        </div>
                    )}
                </div>

                <div className="p-6">
                    <div className="mb-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="px-4 pt-4">
                            <p className="text-[11px] font-extrabold uppercase tracking-wide text-gray-500">
                                Sell Summary
                            </p>
                        </div>
                        <div className="m-4 mt-3 rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-700 px-4 py-4 text-white">
                            <p className="text-[11px] font-extrabold uppercase tracking-wide text-white/85">
                                You receive
                            </p>
                            <p className="mt-1 text-3xl font-black tracking-tight">{youReceive}</p>
                            <p className="mt-1 text-xs font-semibold text-white/80">GHS to your MoMo</p>
                        </div>
                        <div className="space-y-2.5 border-t border-gray-100 px-4 py-3 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-gray-500">RMB Sent</span>
                                <span className="text-base font-black text-red-600">
                                    ¥ {transfer.quote.rmb_amount.toFixed(2)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3 text-xs">
                                <span className="text-gray-500">Rate</span>
                                <span className="font-bold text-gray-700">
                                    1 RMB = {ghsPerRmb.toFixed(4)} GHS
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="mb-4 rounded-xl border border-green-100 bg-green-50 p-4">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-green-800">
                            Payout Account (MoMo)
                        </p>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between gap-2">
                                <span className="text-gray-600">Network</span>
                                <span className="font-semibold text-gray-900">{payout.network ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-gray-600">Number</span>
                                <span className="font-semibold text-gray-900">{payout.number ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-gray-600">Account Name</span>
                                <span className="text-right font-semibold text-gray-900">{payout.account_name ?? '—'}</span>
                            </div>
                        </div>
                    </div>

                    <div className="mb-4 rounded-xl bg-gray-50 p-4 text-sm">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-gray-500">Reference</span>
                            <span className="max-w-[55%] truncate font-mono text-xs text-gray-800">{transfer.reference}</span>
                        </div>
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-gray-500">Request ID</span>
                            <span className="text-gray-800">#{transfer.id}</span>
                        </div>
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-gray-500">Submitted</span>
                            <span className="text-gray-800">{submittedAt}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-gray-500">Status</span>
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${badgeClass}`}>{badgeLabel}</span>
                        </div>
                    </div>

                    {transfer.rejection_reason && (
                        <div className="mb-4 rounded-r-lg border-l-4 border-red-400 bg-red-50 p-4">
                            <p className="text-sm text-red-700">{transfer.rejection_reason}</p>
                        </div>
                    )}

                    {isCompleted && payoutProofs.length > 0 && (
                        <div className="mb-4 rounded-xl border border-green-200 bg-green-50 p-4">
                            <p className="mb-2 text-sm font-semibold text-green-800">MoMo Payment Proof</p>
                            {payoutProofs.map((proof) => (
                                <a
                                    key={proof.id}
                                    href={proof.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block overflow-hidden rounded-lg border border-green-200 bg-white"
                                >
                                    <img
                                        src={proof.url}
                                        alt={proof.original_name || 'Payout proof'}
                                        className="mx-auto max-h-48 w-full object-contain"
                                    />
                                </a>
                            ))}
                        </div>
                    )}

                    {isCompleted && (
                        <div className="mb-4 rounded-r-lg border-l-4 border-green-400 bg-green-50 p-4">
                            <p className="text-sm text-green-700">
                                {youReceive} was sent to your {payout.network ?? 'MoMo'} account.
                            </p>
                        </div>
                    )}

                    {isProcessing && (
                        <div className="py-3 text-center">
                            <p className="mb-1 text-sm text-gray-500">Status updates automatically every few seconds.</p>
                            <p className="text-xs text-gray-400">Tap Refresh below or return to your wallet anytime.</p>
                        </div>
                    )}

                    <div className="mt-4 flex gap-3">
                        <button
                            type="button"
                            onClick={() => void handleRefresh()}
                            disabled={refreshing}
                            className="flex-1 rounded-xl border border-gray-200 bg-gray-100 py-3 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-200 disabled:opacity-60"
                        >
                            {refreshing ? 'Refreshing…' : 'Refresh'}
                        </button>
                        <Link
                            href={walletHref}
                            className="flex-1 rounded-xl bg-red-600 py-3 px-4 text-center text-sm font-medium text-white transition hover:bg-red-700"
                        >
                            Back to Wallet
                        </Link>
                    </div>

                    {historyHref && (
                        <Link href={historyHref} className="mt-3 block text-center text-sm text-gray-500 hover:text-gray-700">
                            View transaction history
                        </Link>
                    )}

                    {transfer.can_cancel && onCancel && (
                        <button
                            type="button"
                            onClick={onCancel}
                            className="mt-4 w-full rounded-xl border border-gray-200 py-3 text-sm font-medium text-gray-600 hover:bg-gray-50"
                        >
                            Cancel request
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

export type { Transfer as SellRmbStatusTransfer };
