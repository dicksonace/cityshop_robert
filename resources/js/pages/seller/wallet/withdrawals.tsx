import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Download } from 'lucide-react';

import SellerLayout from '@/layouts/seller-layout';
import { momoNetworkLabel } from '@/lib/momo-networks';
import {
    formatFinanceDate,
    PaginationLinks,
    withdrawalStatusColor,
    withdrawalStatusLabel,
} from '@/lib/seller-finance';
import { formatPrice, Paginated, productImageUrl, Wallet, Withdrawal } from '@/types/marketplace';

interface Props {
    wallet: Wallet;
    withdrawals: Paginated<Withdrawal>;
}

export default function SellerWithdrawals({ wallet, withdrawals }: Props) {
    return (
        <SellerLayout title="Withdrawals" active="wallet-withdrawals">
            <Head title="Withdrawal history" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link
                        href={route('seller.wallet')}
                        className="inline-flex items-center gap-1 text-sm text-orange-600 hover:underline"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Finance
                    </Link>
                    <h2 className="mt-2 text-xl font-bold text-gray-900">Withdrawal history</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Date, amount, destination, and status for every MoMo payout request.
                    </p>
                </div>
                <div className="rounded-xl border border-gray-100 bg-white px-4 py-3 text-right shadow-sm">
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">Withdrawn</p>
                    <p className="text-lg font-bold text-gray-900">{formatPrice(wallet.withdrawn_amount)}</p>
                    <p className="text-xs text-gray-500">Available {formatPrice(wallet.available_balance)}</p>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                {withdrawals.data.length === 0 ? (
                    <p className="p-8 text-center text-sm text-gray-500">No withdrawal requests yet.</p>
                ) : (
                    <>
                        <div className="hidden grid-cols-[9rem_1fr_7rem_8rem_2rem] gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 md:grid">
                            <span>Date</span>
                            <span>Destination</span>
                            <span className="text-right">Amount</span>
                            <span>Status</span>
                            <span />
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {withdrawals.data.map((w) => (
                                <li key={w.id}>
                                    <Link
                                        href={route('seller.wallet.withdrawals.show', w.id)}
                                        className="grid gap-2 px-4 py-4 transition hover:bg-orange-50/40 md:grid-cols-[9rem_1fr_7rem_8rem_2rem] md:items-center md:gap-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">
                                                {formatFinanceDate(w.created_at, false)}
                                            </p>
                                            <p className="text-xs text-gray-400">
                                                {w.created_at
                                                    ? new Date(w.created_at).toLocaleTimeString('en-GH', {
                                                          hour: '2-digit',
                                                          minute: '2-digit',
                                                      })
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="font-medium text-gray-900">
                                                {momoNetworkLabel(w.network)} · {w.momo_number}
                                            </p>
                                            <p className="truncate text-sm text-gray-500">{w.account_name}</p>
                                            {w.proof_path && (
                                                <span className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-orange-600">
                                                    <Download className="h-3 w-3" /> Proof attached
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-between md:block md:text-right">
                                            <span className="text-xs text-gray-400 md:hidden">Amount</span>
                                            <p className="text-sm font-bold text-gray-900">{formatPrice(w.amount)}</p>
                                        </div>
                                        <div>
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${withdrawalStatusColor[w.status] ?? 'bg-gray-100 text-gray-700'}`}
                                            >
                                                {withdrawalStatusLabel[w.status] ?? w.status}
                                            </span>
                                        </div>
                                        <ChevronRight className="ml-auto hidden h-4 w-4 text-gray-300 md:block" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <div className="border-t border-gray-100 px-4 pb-4">
                            <PaginationLinks links={withdrawals.links} />
                        </div>
                    </>
                )}
            </div>
        </SellerLayout>
    );
}
