import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import { WithdrawalStatusView, type WithdrawalStatusViewData } from '@/components/wallet/withdrawal-status-view';
import SellerLayout from '@/layouts/seller-layout';
import { SharedData } from '@/types';
import { formatPrice, formatWalletTransactionType, Wallet, WalletTransaction } from '@/types/marketplace';
import { formatFinanceDate, transactionTypeBadgeClass } from '@/lib/seller-finance';

interface Props {
    wallet: Wallet;
    withdrawal: WithdrawalStatusViewData;
    ledger: WalletTransaction[];
}

const TERMINAL = ['paid', 'rejected'];

export default function SellerWithdrawalShow({ wallet, withdrawal: initial, ledger }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [withdrawal, setWithdrawal] = useState(initial);

    useEffect(() => {
        setWithdrawal(initial);
    }, [initial]);

    const autoRefresh = !TERMINAL.includes(withdrawal.status);

    const refreshWithdrawal = useCallback(async () => {
        const res = await fetch(`${route('seller.wallet.withdrawals.show', withdrawal.id)}?json=1`, {
            headers: { Accept: 'application/json' },
        });
        const body = await res.json();
        if (body?.data) {
            setWithdrawal(body.data);
        }
    }, [withdrawal.id]);

    useEffect(() => {
        if (!autoRefresh) return;
        const timer = window.setInterval(() => {
            void refreshWithdrawal();
        }, 8000);
        return () => window.clearInterval(timer);
    }, [withdrawal.id, withdrawal.status, autoRefresh, refreshWithdrawal]);

    return (
        <SellerLayout title="Withdrawal" active="wallet-withdrawals">
            <Head title={`Withdrawal #${withdrawal.id}`} />
            <div className="min-h-[70vh] bg-gray-50 px-4 py-6">
                {(flash.success || flash.error) && (
                    <p
                        className={`mx-auto mb-4 max-w-md rounded-xl px-4 py-3 text-sm ${
                            flash.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </p>
                )}
                <WithdrawalStatusView
                    withdrawal={withdrawal}
                    onRefresh={refreshWithdrawal}
                    walletHref={route('seller.wallet')}
                    historyHref={route('seller.wallet.withdrawals')}
                />

                {ledger.length > 0 && (
                    <div className="mx-auto mt-6 w-full max-w-md rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold text-gray-900">Ledger entries</h3>
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
                                                {formatWalletTransactionType(tx.type, tx.type_label)}
                                            </span>
                                            <p className="mt-1 text-sm text-gray-700">{tx.description}</p>
                                            <p className="text-xs text-gray-400">{formatFinanceDate(tx.created_at)}</p>
                                        </div>
                                        <p className={`shrink-0 text-sm font-bold ${tx.amount > 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                                            {tx.amount > 0 ? '+' : ''}
                                            {formatPrice(tx.amount)}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-4 text-xs text-gray-400">
                            Current available balance: {formatPrice(wallet.available_balance)}
                        </p>
                    </div>
                )}
            </div>
        </SellerLayout>
    );
}
