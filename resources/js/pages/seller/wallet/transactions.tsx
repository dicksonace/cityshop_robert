import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';

import SellerLayout from '@/layouts/seller-layout';
import {
    formatFinanceDate,
    PaginationLinks,
    transactionTypeBadgeClass,
} from '@/lib/seller-finance';
import {
    formatPrice,
    formatWalletTransactionType,
    Paginated,
    Wallet,
    WalletTransaction,
} from '@/types/marketplace';

interface Props {
    wallet: Wallet;
    transactions: Paginated<WalletTransaction>;
}

export default function SellerTransactions({ wallet, transactions }: Props) {
    return (
        <SellerLayout title="Transactions" active="wallet-transactions">
            <Head title="Transaction history" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link
                        href={route('seller.wallet')}
                        className="inline-flex items-center gap-1 text-sm text-orange-600 hover:underline"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Finance
                    </Link>
                    <h2 className="mt-2 text-xl font-bold text-gray-900">Transaction history</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Full ledger with date, amount, and wallet balance after each entry.
                    </p>
                </div>
                <div className="rounded-xl border border-orange-100 bg-gradient-to-br from-orange-50 to-white px-4 py-3 text-right shadow-sm">
                    <p className="text-xs font-medium uppercase tracking-wide text-orange-600">Available</p>
                    <p className="text-lg font-bold text-orange-600">{formatPrice(wallet.available_balance)}</p>
                    <p className="text-xs text-gray-500">Pending {formatPrice(wallet.pending_balance)}</p>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                {transactions.data.length === 0 ? (
                    <p className="p-8 text-center text-sm text-gray-500">No transactions yet.</p>
                ) : (
                    <>
                        <div className="hidden grid-cols-[9rem_1fr_7rem_7rem_2rem] gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 md:grid">
                            <span>Date</span>
                            <span>Details</span>
                            <span className="text-right">Amount</span>
                            <span className="text-right">Balance</span>
                            <span />
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {transactions.data.map((tx) => {
                                const isCredit = tx.amount > 0;
                                return (
                                    <li key={tx.id}>
                                        <Link
                                            href={route('seller.wallet.transactions.show', tx.id)}
                                            className="grid gap-2 px-4 py-4 transition hover:bg-orange-50/40 md:grid-cols-[9rem_1fr_7rem_7rem_2rem] md:items-center md:gap-3"
                                        >
                                            <div>
                                                <p className="text-sm font-medium text-gray-900 md:text-gray-700">
                                                    {formatFinanceDate(tx.created_at, false)}
                                                </p>
                                                <p className="text-xs text-gray-400">
                                                    {tx.created_at
                                                        ? new Date(tx.created_at).toLocaleTimeString('en-GH', {
                                                              hour: '2-digit',
                                                              minute: '2-digit',
                                                          })
                                                        : ''}
                                                </p>
                                            </div>
                                            <div className="min-w-0">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${transactionTypeBadgeClass(tx.type)}`}
                                                >
                                                    {formatWalletTransactionType(tx.type)}
                                                </span>
                                                <p className="mt-1 truncate text-sm text-gray-700">{tx.description}</p>
                                                {tx.reference && (
                                                    <p className="mt-0.5 text-xs text-gray-400">Ref: {tx.reference}</p>
                                                )}
                                            </div>
                                            <div className="flex items-center justify-between md:block md:text-right">
                                                <span className="text-xs text-gray-400 md:hidden">Amount</span>
                                                <p
                                                    className={`text-sm font-bold ${isCredit ? 'text-emerald-600' : 'text-rose-600'}`}
                                                >
                                                    {isCredit ? '+' : ''}
                                                    {formatPrice(tx.amount)}
                                                </p>
                                            </div>
                                            <div className="flex items-center justify-between md:block md:text-right">
                                                <span className="text-xs text-gray-400 md:hidden">Balance</span>
                                                <div>
                                                    <p className="text-sm font-semibold text-gray-900">
                                                        {formatPrice(tx.balance_after ?? 0)}
                                                    </p>
                                                    <p className="text-[11px] text-gray-400">
                                                        Avail. {formatPrice(tx.available_after ?? 0)}
                                                    </p>
                                                </div>
                                            </div>
                                            <ChevronRight className="ml-auto hidden h-4 w-4 text-gray-300 md:block" />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                        <div className="border-t border-gray-100 px-4 pb-4">
                            <PaginationLinks links={transactions.links} />
                        </div>
                    </>
                )}
            </div>
        </SellerLayout>
    );
}
