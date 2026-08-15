import { Head, Link, router, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';

import RechargeModal from '@/components/wallet/recharge-modal';
import { WalletTransactionReceiptButton } from '@/components/wallet/wallet-receipt-modal';
import ShopLayout from '@/layouts/shop-layout';
import { type PaystackFeeSettings } from '@/lib/paystack-fees';
import { payoutNetworkLabel } from '@/lib/ghana-banks';
import { SharedData } from '@/types';
import { cn } from '@/lib/utils';
import {
    formatPrice,
    formatWalletTransactionType,
    Paginated,
    Wallet,
    WalletTransaction,
    Withdrawal,
} from '@/types/marketplace';

interface BuyerWalletProps {
    wallet: Wallet;
    transactions: Paginated<WalletTransaction>;
    withdrawals: Paginated<Withdrawal>;
    hasPendingWithdrawal: boolean;
    paystackConfigured: boolean;
    paystackPublicKey: string;
    paystackFee?: PaystackFeeSettings | null;
    manualTopUpEnabled?: boolean;
}

function formatDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const statusColor: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-blue-100 text-blue-800',
    approved: 'bg-blue-100 text-blue-800',
    paid: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
};

const statusLabel: Record<string, string> = {
    pending: 'Pending',
    processing: 'Processing',
    paid: 'Completed',
    rejected: 'Rejected',
};

export default function BuyerWallet({
    wallet,
    transactions,
    withdrawals,
    hasPendingWithdrawal,
    paystackConfigured,
    paystackFee,
    manualTopUpEnabled,
}: BuyerWalletProps) {
    const { flash } = usePage<SharedData>().props;
    const [refreshing, setRefreshing] = useState(false);
    const [rechargeOpen, setRechargeOpen] = useState(false);

    const canRecharge = paystackConfigured || !!manualTopUpEnabled;

    const refreshBalance = () => {
        setRefreshing(true);
        router.reload({
            only: ['wallet', 'transactions', 'withdrawals', 'hasPendingWithdrawal'],
            onFinish: () => setRefreshing(false),
        });
    };

    return (
        <ShopLayout hideFlash>
            <Head title="Wallet" />
            {(flash.success || flash.error) && (
                <div
                    className={`fixed inset-x-4 top-[4.75rem] z-[60] mx-auto max-w-lg rounded-xl border px-4 py-3 text-sm font-medium shadow-lg sm:max-w-2xl ${
                        flash.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                            : 'border-red-200 bg-red-50 text-red-800'
                    }`}
                    role="status"
                >
                    {flash.success ?? flash.error}
                </div>
            )}
            <div className="mx-auto max-w-lg px-4 py-6 sm:max-w-2xl sm:py-8">
                <div className="mb-5 flex items-center justify-between gap-3">
                    <h1 className="text-2xl font-black tracking-tight text-gray-900">Wallet</h1>
                    <button
                        type="button"
                        onClick={refreshBalance}
                        disabled={refreshing}
                        className="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-60"
                    >
                        <RefreshCw className={cn('h-3.5 w-3.5', refreshing && 'animate-spin')} />
                        Refresh
                    </button>
                </div>

                <div className="relative mb-4 overflow-hidden rounded-[1.25rem] bg-gradient-to-br from-orange-500 to-orange-400 p-[1.375rem] text-white shadow-[0_8px_18px_rgba(249,115,22,0.28)]">
                    <Link
                        href={route('wallet.china-rmb.index')}
                        className="absolute right-3 top-3 rounded-full bg-white/20 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide text-white backdrop-blur-sm hover:bg-white/30"
                    >
                        China / RMB
                    </Link>
                    <p className="text-sm font-semibold text-white/70">Available balance</p>
                    <p className="mt-2 text-[2.125rem] font-black leading-none tracking-tight">
                        {formatPrice(wallet.available_balance)}
                    </p>
                    <div className="mt-2.5 inline-flex rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold">
                        Pending: {formatPrice(wallet.pending_balance ?? 0)}
                    </div>
                    <div className="mt-4 flex h-12 overflow-hidden rounded-full bg-white shadow-md">
                        <Link
                            href={route('wallet.withdraw.create')}
                            className="flex flex-1 items-center justify-center text-[15px] font-extrabold text-slate-900 transition hover:bg-slate-50"
                        >
                            Withdrawal
                        </Link>
                        <button
                            type="button"
                            onClick={canRecharge ? () => setRechargeOpen(true) : undefined}
                            disabled={!canRecharge}
                            className={cn(
                                'flex flex-1 items-center justify-center rounded-full text-[15px] font-extrabold text-white transition',
                                canRecharge
                                    ? 'bg-orange-500 hover:bg-orange-600'
                                    : 'cursor-not-allowed bg-slate-400',
                            )}
                        >
                            {canRecharge ? 'Recharge' : 'Unavailable'}
                        </button>
                    </div>
                </div>

                {hasPendingWithdrawal && (
                    <Link
                        href={route('wallet.withdraw.create')}
                        className="mb-4 block rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800"
                    >
                        A withdrawal is processing. Tap to view requests.
                    </Link>
                )}

                {!canRecharge && (
                    <p className="mb-4 text-xs font-medium text-amber-700">Recharge is unavailable right now.</p>
                )}

                <RechargeModal
                    open={rechargeOpen}
                    onClose={() => setRechargeOpen(false)}
                    paystackConfigured={paystackConfigured}
                    manualTopUpEnabled={!!manualTopUpEnabled}
                    manualHref={route('wallet.manual-top-up')}
                    paystackRoute={route('wallet.add-funds')}
                    amountInputId="buyer-recharge-amount"
                    paystackFee={paystackFee}
                />

                <div id="withdrawal-requests" className="mb-5 scroll-mt-28 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h3 className="text-base font-extrabold text-gray-900">Withdrawal requests</h3>
                            <p className="mt-1 text-sm text-gray-500">Track MoMo and bank payouts.</p>
                        </div>
                        <Link href={route('wallet.withdraw.create')} className="shrink-0 text-sm font-semibold text-orange-600 hover:underline">
                            Withdraw
                        </Link>
                    </div>
                    {withdrawals.data.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No withdrawal requests yet.</p>
                    ) : (
                        <div className="mt-3 divide-y divide-gray-100">
                            {withdrawals.data.map((w) => (
                                <div key={w.id} className="flex items-start justify-between gap-3 py-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${statusColor[w.status] ?? 'bg-gray-100 text-gray-700'}`}>
                                                {statusLabel[w.status] ?? w.status}
                                            </span>
                                            <span className="text-xs text-gray-400">{payoutNetworkLabel(w.network)}</span>
                                        </div>
                                        <p className="mt-1 text-sm text-gray-700">{w.momo_number}</p>
                                        <p className="text-xs text-gray-400">{formatDate(w.created_at)}</p>
                                        {(w.fee ?? 0) > 0 && (
                                            <p className="text-xs text-gray-500">Fee {formatPrice(w.fee ?? 0)}</p>
                                        )}
                                    </div>
                                    <p className="shrink-0 font-semibold text-orange-600">{formatPrice(w.amount)}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <h3 className="text-base font-extrabold text-gray-900">Transaction History</h3>
                    {transactions.data.length === 0 ? (
                        <p className="mt-3 text-sm text-gray-500">No transactions yet.</p>
                    ) : (
                        <>
                            <div className="mt-1">
                                {transactions.data.map((tx) => {
                                    const isCredit = tx.amount > 0;
                                    return (
                                        <div
                                            key={tx.id}
                                            className="border-t border-gray-100 py-3 first:border-t-0"
                                        >
                                            <div className="flex items-start gap-3">
                                                <div
                                                    className={cn(
                                                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-lg font-extrabold',
                                                        isCredit
                                                            ? 'bg-green-100 text-green-600'
                                                            : 'bg-red-100 text-red-600',
                                                    )}
                                                >
                                                    {isCredit ? '+' : '−'}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-800">
                                                            {formatWalletTransactionType(tx.type, tx.type_label)}
                                                        </span>
                                                        {tx.reference && (
                                                            <span className="text-[11px] text-gray-400">{tx.reference}</span>
                                                        )}
                                                    </div>
                                                    {tx.description ? (
                                                        <p className="mt-1 text-[13px] text-gray-500">{tx.description}</p>
                                                    ) : null}
                                                    <p className="text-[11px] text-gray-400">{formatDate(tx.created_at)}</p>
                                                </div>
                                                <p
                                                    className={cn(
                                                        'shrink-0 text-sm font-extrabold',
                                                        isCredit ? 'text-green-600' : 'text-red-600',
                                                    )}
                                                >
                                                    {isCredit ? '+' : ''}
                                                    {formatPrice(tx.amount)}
                                                </p>
                                            </div>
                                            <div className="mt-2 grid grid-cols-2 gap-2 rounded-[10px] bg-gray-50 px-3 py-2 text-xs">
                                                <div>
                                                    <p className="text-gray-500">Before balance</p>
                                                    <p className="font-bold text-gray-900">
                                                        {formatPrice(tx.balance_before ?? 0)}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-gray-500">After balance</p>
                                                    <p className="font-bold text-gray-900">
                                                        {formatPrice(tx.balance_after ?? 0)}
                                                    </p>
                                                </div>
                                            </div>
                                            <WalletTransactionReceiptButton tx={tx} />
                                        </div>
                                    );
                                })}
                            </div>
                            {transactions.last_page > 1 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {transactions.links.map((link, i) => (
                                        <Link
                                            key={i}
                                            href={link.url ?? '#'}
                                            preserveScroll
                                            className={`rounded-md px-3 py-1.5 text-sm ${
                                                link.active
                                                    ? 'bg-orange-500 text-white'
                                                    : link.url
                                                      ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                                      : 'cursor-not-allowed bg-gray-50 text-gray-400'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
