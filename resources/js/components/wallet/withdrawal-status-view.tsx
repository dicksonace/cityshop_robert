import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export type WithdrawalStatusViewData = {
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
    amount: number;
    fee?: number;
    total_debited?: number;
    you_receive?: number;
    payout_type?: string;
    payout_account?: {
        network: string | null;
        number: string | null;
        account_name: string | null;
        kind?: string | null;
    };
    rejection_reason?: string | null;
    failure_reason?: string | null;
    admin_notes?: string | null;
    proof_url?: string | null;
    created_at?: string | null;
    processed_at?: string | null;
};

type Props = {
    withdrawal: WithdrawalStatusViewData;
    onRefresh: () => Promise<void> | void;
    walletHref: string;
    historyHref?: string;
};

const TERMINAL = ['paid', 'rejected'];

function formatGhs(n: number) {
    return `GH₵ ${n.toFixed(2)}`;
}

function formatWhen(raw?: string | null): string {
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

export function WithdrawalStatusView({ withdrawal, onRefresh, walletHref, historyHref }: Props) {
    const [refreshing, setRefreshing] = useState(false);
    const presentation = withdrawal.status_presentation;
    const payout = withdrawal.payout_account ?? { network: '—', number: '—', account_name: '—' };
    const youReceive = formatGhs(withdrawal.you_receive ?? withdrawal.amount);
    const isBank = withdrawal.payout_type === 'bank' || payout.kind === 'Bank';
    const isProcessing = withdrawal.processing ?? !TERMINAL.includes(withdrawal.status);
    const isCompleted = withdrawal.status === 'paid';
    const isRejected = withdrawal.status === 'rejected';

    const headerColor = presentation?.header_color ?? '#ef4444';
    const headerTitle = presentation?.header_title ?? 'Processing';
    const headerSubtitle = presentation?.header_subtitle ?? 'Your withdrawal is being sent';
    const badgeLabel = presentation?.badge_label ?? withdrawal.status_label;
    const badgeClass = presentation?.badge_class ?? 'bg-yellow-100 text-yellow-800';

    const submittedAt = useMemo(
        () => formatWhen(withdrawal.created_at),
        [withdrawal.created_at],
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
                            <h1 className="text-2xl font-bold">{headerTitle}</h1>
                            <p className="mt-1 text-sm text-white/90">{headerSubtitle}</p>
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
                                Withdrawal summary
                            </p>
                        </div>
                        <div className="m-4 mt-3 rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-700 px-4 py-4 text-white">
                            <p className="text-[11px] font-extrabold uppercase tracking-wide text-white/85">
                                You receive
                            </p>
                            <p className="mt-1 text-3xl font-black tracking-tight">{youReceive}</p>
                            <p className="mt-1 text-xs font-semibold text-white/80">
                                {isBank ? 'GHS to your bank' : 'GHS to your MoMo'}
                            </p>
                        </div>
                        <div className="space-y-2.5 border-t border-gray-100 px-4 py-3 text-sm">
                            {(withdrawal.fee ?? 0) > 0 && (
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-gray-500">Fee</span>
                                    <span className="text-base font-black text-red-600">{formatGhs(withdrawal.fee ?? 0)}</span>
                                </div>
                            )}
                            {(withdrawal.fee ?? 0) > 0 && (
                                <div className="flex items-center justify-between gap-3 text-xs">
                                    <span className="text-gray-500">Total debited</span>
                                    <span className="font-bold text-gray-700">{formatGhs(withdrawal.total_debited ?? withdrawal.amount)}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="mb-4 rounded-xl border border-green-100 bg-green-50 p-4">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-green-800">
                            Payout account ({isBank ? 'Bank' : 'MoMo'})
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
                                <span className="text-gray-600">Account name</span>
                                <span className="text-right font-semibold text-gray-900">{payout.account_name ?? '—'}</span>
                            </div>
                        </div>
                    </div>

                    <div className="mb-4 rounded-xl bg-gray-50 p-4 text-sm">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-gray-500">Reference</span>
                            <span className="max-w-[55%] truncate font-mono text-xs text-gray-800">{withdrawal.reference}</span>
                        </div>
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-gray-500">Request ID</span>
                            <span className="text-gray-800">#{withdrawal.id}</span>
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

                    {withdrawal.rejection_reason && (
                        <div className="mb-4 rounded-r-lg border-l-4 border-red-400 bg-red-50 p-4">
                            <p className="text-sm text-red-700">{withdrawal.rejection_reason}</p>
                        </div>
                    )}

                    {isCompleted && withdrawal.proof_url && (
                        <div className="mb-4 rounded-xl border border-green-200 bg-green-50 p-4">
                            <p className="mb-2 text-sm font-semibold text-green-800">Payout proof</p>
                            <a href={withdrawal.proof_url} target="_blank" rel="noopener noreferrer">
                                <img src={withdrawal.proof_url} alt="Payout proof" className="mx-auto max-h-48 w-full object-contain" />
                            </a>
                        </div>
                    )}

                    {isCompleted && (
                        <div className="mb-4 rounded-r-lg border-l-4 border-green-400 bg-green-50 p-4">
                            <p className="text-sm text-green-700">
                                {youReceive} was sent to your {payout.network ?? (isBank ? 'bank' : 'MoMo')} account.
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
                </div>
            </div>
        </div>
    );
}
