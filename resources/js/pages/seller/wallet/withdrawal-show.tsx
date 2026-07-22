import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';

import SellerLayout from '@/layouts/seller-layout';
import { momoNetworkLabel } from '@/lib/momo-networks';
import {
    formatFinanceDate,
    transactionTypeBadgeClass,
    withdrawalStatusColor,
    withdrawalStatusLabel,
} from '@/lib/seller-finance';
import {
    formatPrice,
    formatWalletTransactionType,
    productImageUrl,
    Wallet,
    WalletTransaction,
    Withdrawal,
} from '@/types/marketplace';

interface Props {
    wallet: Wallet;
    withdrawal: Withdrawal;
    ledger: WalletTransaction[];
}

export default function SellerWithdrawalShow({ wallet, withdrawal, ledger }: Props) {
    return (
        <SellerLayout title="Withdrawal" active="wallet-withdrawals">
            <Head title={`Withdrawal #${withdrawal.id}`} />

            <Link
                href={route('seller.wallet.withdrawals')}
                className="inline-flex items-center gap-1 text-sm text-orange-600 hover:underline"
            >
                <ArrowLeft className="h-4 w-4" />
                All withdrawals
            </Link>

            <div className="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 bg-gradient-to-r from-slate-900 via-slate-800 to-orange-900 px-5 py-6 text-white">
                    <span
                        className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${withdrawalStatusColor[withdrawal.status] ?? 'bg-white/15 text-white'}`}
                    >
                        {withdrawalStatusLabel[withdrawal.status] ?? withdrawal.status}
                    </span>
                    <p className="mt-3 text-3xl font-bold">{formatPrice(withdrawal.amount)}</p>
                    <p className="mt-2 text-sm text-white/80">
                        {momoNetworkLabel(withdrawal.network)} · {withdrawal.momo_number}
                    </p>
                    <p className="text-xs text-white/50">{withdrawal.account_name}</p>
                </div>

                <dl className="grid gap-4 p-5 sm:grid-cols-2">
                    <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">Requested</dt>
                        <dd className="mt-1 text-sm font-semibold text-gray-900">
                            {formatFinanceDate(withdrawal.created_at)}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">Processed</dt>
                        <dd className="mt-1 text-sm font-semibold text-gray-900">
                            {formatFinanceDate(withdrawal.processed_at)}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">Payout channel</dt>
                        <dd className="mt-1 text-sm font-semibold capitalize text-gray-900">
                            {withdrawal.payout_channel ?? 'Manual MoMo'}
                        </dd>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">Reference</dt>
                        <dd className="mt-1 font-mono text-sm font-semibold text-gray-900">WD-{withdrawal.id}</dd>
                    </div>
                </dl>

                {(withdrawal.admin_notes || withdrawal.rejection_reason || withdrawal.failure_reason) && (
                    <div className="space-y-3 border-t border-gray-100 px-5 py-5">
                        {withdrawal.admin_notes && (
                            <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-900">
                                <p className="font-semibold">Admin note</p>
                                <p className="mt-1">{withdrawal.admin_notes}</p>
                            </div>
                        )}
                        {withdrawal.rejection_reason && (
                            <div className="rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-900">
                                <p className="font-semibold">Rejection reason</p>
                                <p className="mt-1">{withdrawal.rejection_reason}</p>
                            </div>
                        )}
                        {withdrawal.failure_reason && (
                            <div className="rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
                                <p className="font-semibold">Failure reason</p>
                                <p className="mt-1">{withdrawal.failure_reason}</p>
                            </div>
                        )}
                    </div>
                )}

                {withdrawal.proof_path && (
                    <div className="border-t border-gray-100 px-5 py-5">
                        <a
                            href={productImageUrl(withdrawal.proof_path)}
                            target="_blank"
                            rel="noreferrer"
                            download
                            className="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600"
                        >
                            <Download className="h-4 w-4" />
                            View / download payout proof
                        </a>
                    </div>
                )}

                <div className="border-t border-gray-100 px-5 py-5">
                    <h3 className="text-sm font-semibold text-gray-900">Ledger entries</h3>
                    <p className="mt-1 text-xs text-gray-500">Wallet transactions linked to this withdrawal.</p>
                    {ledger.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No linked ledger entries.</p>
                    ) : (
                        <ul className="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-100">
                            {ledger.map((tx) => (
                                <li key={tx.id}>
                                    <Link
                                        href={route('seller.wallet.transactions.show', tx.id)}
                                        className="flex items-start justify-between gap-3 px-4 py-3 hover:bg-gray-50"
                                    >
                                        <div className="min-w-0">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${transactionTypeBadgeClass(tx.type)}`}
                                            >
                                                {formatWalletTransactionType(tx.type)}
                                            </span>
                                            <p className="mt-1 text-sm text-gray-700">{tx.description}</p>
                                            <p className="text-xs text-gray-400">{formatFinanceDate(tx.created_at)}</p>
                                        </div>
                                        <p
                                            className={`shrink-0 text-sm font-bold ${tx.amount > 0 ? 'text-emerald-600' : 'text-rose-600'}`}
                                        >
                                            {tx.amount > 0 ? '+' : ''}
                                            {formatPrice(tx.amount)}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-4 text-xs text-gray-400">
                        Current available balance: {formatPrice(wallet.available_balance)}
                    </p>
                </div>
            </div>
        </SellerLayout>
    );
}
