import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import SellerLayout from '@/layouts/seller-layout';
import { formatFinanceDate, transactionTypeBadgeClass } from '@/lib/seller-finance';
import {
    formatPrice,
    formatWalletTransactionType,
    Wallet,
    WalletTransaction,
} from '@/types/marketplace';

interface Props {
    wallet: Wallet;
    transaction: WalletTransaction & {
        order_item?: {
            id: number;
            product_name: string;
            status: string;
            seller_amount?: number;
            quantity?: number;
            order?: {
                id: number;
                order_number: string;
                status: string;
                payment_status: string;
                created_at?: string;
            } | null;
        } | null;
        withdrawal?: {
            id: number;
            status: string;
            amount: number;
            network: string;
            momo_number: string;
            account_name?: string;
            created_at?: string;
            processed_at?: string;
            rejection_reason?: string | null;
            admin_notes?: string | null;
            proof_path?: string | null;
        } | null;
    };
}

export default function SellerTransactionShow({ wallet, transaction }: Props) {
    const isCredit = transaction.amount > 0;

    return (
        <SellerLayout title="Transaction" active="wallet-transactions">
            <Head title={`Transaction #${transaction.id}`} />

            <Link
                href={route('seller.wallet.transactions')}
                className="inline-flex items-center gap-1 text-sm text-orange-600 hover:underline"
            >
                <ArrowLeft className="h-4 w-4" />
                All transactions
            </Link>

            <div className="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 bg-gradient-to-r from-slate-900 via-slate-800 to-orange-900 px-5 py-6 text-white">
                    <span
                        className={`inline-flex rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-semibold ring-1 ring-white/20`}
                    >
                        {formatWalletTransactionType(transaction.type)}
                    </span>
                    <p className={`mt-3 text-3xl font-bold ${isCredit ? 'text-emerald-300' : 'text-rose-200'}`}>
                        {isCredit ? '+' : ''}
                        {formatPrice(transaction.amount)}
                    </p>
                    <p className="mt-2 text-sm text-white/75">{transaction.description}</p>
                    <p className="mt-1 text-xs text-white/50">{formatFinanceDate(transaction.created_at)}</p>
                </div>

                <div className="grid gap-4 p-5 sm:grid-cols-3">
                    <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-gray-500">Balance after</p>
                        <p className="mt-1 text-xl font-bold text-gray-900">
                            {formatPrice(transaction.balance_after ?? 0)}
                        </p>
                        <p className="text-xs text-gray-500">Available + pending</p>
                    </div>
                    <div className="rounded-xl border border-orange-100 bg-orange-50/60 p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-orange-600">Available after</p>
                        <p className="mt-1 text-xl font-bold text-orange-600">
                            {formatPrice(transaction.available_after ?? 0)}
                        </p>
                    </div>
                    <div className="rounded-xl border border-amber-100 bg-amber-50/60 p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-amber-700">Pending after</p>
                        <p className="mt-1 text-xl font-bold text-amber-800">
                            {formatPrice(transaction.pending_after ?? 0)}
                        </p>
                    </div>
                </div>

                <dl className="space-y-3 border-t border-gray-100 px-5 py-5 text-sm">
                    <div className="flex justify-between gap-4">
                        <dt className="text-gray-500">Type</dt>
                        <dd>
                            <span
                                className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${transactionTypeBadgeClass(transaction.type)}`}
                            >
                                {formatWalletTransactionType(transaction.type)}
                            </span>
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-gray-500">Reference</dt>
                        <dd className="font-medium text-gray-900">{transaction.reference ?? '—'}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-gray-500">Transaction ID</dt>
                        <dd className="font-mono text-gray-900">#{transaction.id}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-gray-500">Date & time</dt>
                        <dd className="font-medium text-gray-900">{formatFinanceDate(transaction.created_at)}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-gray-500">Current available</dt>
                        <dd className="font-medium text-gray-900">{formatPrice(wallet.available_balance)}</dd>
                    </div>
                </dl>

                {transaction.order_item && (
                    <div className="border-t border-gray-100 px-5 py-5">
                        <h3 className="text-sm font-semibold text-gray-900">Related order</h3>
                        <div className="mt-3 rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm">
                            <p className="font-medium text-gray-900">{transaction.order_item.product_name}</p>
                            {transaction.order_item.order && (
                                <p className="mt-1 text-gray-600">
                                    Order {transaction.order_item.order.order_number}
                                </p>
                            )}
                            <p className="mt-1 text-xs capitalize text-gray-500">
                                Item status: {transaction.order_item.status.replace(/_/g, ' ')}
                            </p>
                            {transaction.order_item.order && (
                                <Link
                                    href={route('seller.orders.show', transaction.order_item.id)}
                                    className="mt-3 inline-block text-sm font-medium text-orange-600 hover:underline"
                                >
                                    View order →
                                </Link>
                            )}
                        </div>
                    </div>
                )}

                {transaction.withdrawal && (
                    <div className="border-t border-gray-100 px-5 py-5">
                        <h3 className="text-sm font-semibold text-gray-900">Related withdrawal</h3>
                        <div className="mt-3 rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm">
                            <p className="font-medium text-gray-900">
                                {formatPrice(transaction.withdrawal.amount)} · {transaction.withdrawal.momo_number}
                            </p>
                            <p className="mt-1 capitalize text-gray-600">
                                Status: {transaction.withdrawal.status}
                            </p>
                            <Link
                                href={route('seller.wallet.withdrawals.show', transaction.withdrawal.id)}
                                className="mt-3 inline-block text-sm font-medium text-orange-600 hover:underline"
                            >
                                View withdrawal details →
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </SellerLayout>
    );
}
