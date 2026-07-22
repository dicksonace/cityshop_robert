import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, ChevronRight, Download, LoaderCircle, Plus, RefreshCw, Trash2, Wallet as WalletIcon } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import WithdrawalHighlight from '@/components/wallet/withdrawal-highlight';
import SellerLayout from '@/layouts/seller-layout';
import { momoNetworkLabel, momoNetworkMeta } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';
import {
    formatPrice,
    formatWalletTransactionType,
    Paginated,
    Wallet,
    WalletTransaction,
    Withdrawal,
} from '@/types/marketplace';

interface PayoutMethod {
    id: number;
    network: string;
    account_number: string;
    account_name: string;
    is_default: boolean;
}

interface WalletProps {
    wallet: Wallet;
    transactions: Paginated<WalletTransaction>;
    withdrawals: Paginated<Withdrawal>;
    payoutMethods: PayoutMethod[];
    hasPendingWithdrawal: boolean;
    manualTopUpEnabled?: boolean;
}

function formatDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GH', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function SellerWallet({ wallet, transactions, withdrawals, payoutMethods, hasPendingWithdrawal, manualTopUpEnabled }: WalletProps) {
    const [withdrawStep, setWithdrawStep] = useState<'method' | 'amount' | 'review'>('method');
    const [showAddMethod, setShowAddMethod] = useState(payoutMethods.length === 0);
    const [refreshing, setRefreshing] = useState(false);

    const methodForm = useForm({
        network: 'mtn',
        account_number: '',
        account_name: '',
        is_default: true,
    });

    const withdrawForm = useForm({
        payout_method_id: payoutMethods.find((m) => m.is_default)?.id?.toString() ?? payoutMethods[0]?.id?.toString() ?? '',
        amount: '',
    });

    const selectedMethod = payoutMethods.find((m) => m.id === Number(withdrawForm.data.payout_method_id));

    const refreshBalance = () => {
        setRefreshing(true);
        router.reload({
            only: ['wallet', 'transactions', 'withdrawals', 'hasPendingWithdrawal'],
            onFinish: () => setRefreshing(false),
        });
    };

    const saveMethod: FormEventHandler = (e) => {
        e.preventDefault();
        methodForm.post(route('seller.wallet.payout-methods.store'), {
            onSuccess: () => {
                methodForm.reset();
                setShowAddMethod(false);
            },
        });
    };

    const submitWithdraw: FormEventHandler = (e) => {
        e.preventDefault();
        if (withdrawStep === 'method') {
            setWithdrawStep('amount');
            return;
        }
        if (withdrawStep === 'amount') {
            setWithdrawStep('review');
            return;
        }
        withdrawForm.post(route('seller.wallet.withdraw'), {
            onSuccess: () => {
                withdrawForm.reset();
                setWithdrawStep('method');
            },
        });
    };

    const statusColor: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-blue-100 text-blue-800',
        approved: 'bg-blue-100 text-blue-800',
        paid: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
    };

    const statusLabel: Record<string, string> = {
        pending: 'Processing',
        processing: 'Processing',
        paid: 'Paid out',
        rejected: 'Rejected',
    };

    return (
        <SellerLayout title="Finance" active="wallet">
            <Head title="Finance" />

            <div className="mb-4 flex items-center justify-between gap-3">
                <h2 className="text-sm font-semibold text-gray-900">Balances</h2>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={refreshBalance}
                    disabled={refreshing}
                >
                    <RefreshCw className={`mr-1.5 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                    Refresh
                </Button>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    { label: 'Available', value: wallet.available_balance, desc: 'Withdrawable', highlight: true },
                    { label: 'Pending', value: wallet.pending_balance, desc: 'Clearing' },
                    { label: 'Lifetime earnings', value: wallet.total_earnings, desc: 'All time' },
                    { label: 'Withdrawn', value: wallet.withdrawn_amount, desc: 'Paid out' },
                ].map((card) => (
                    <div
                        key={card.label}
                        className={cn(
                            'rounded-2xl border bg-white p-5 shadow-sm',
                            card.highlight ? 'border-orange-200 bg-gradient-to-br from-orange-50 to-white ring-1 ring-orange-100' : 'border-gray-100',
                        )}
                    >
                        <p className="text-sm text-gray-500">{card.label}</p>
                        <p className={cn('mt-1 text-2xl font-bold', card.highlight ? 'text-orange-600' : 'text-gray-900')}>{formatPrice(card.value)}</p>
                        <p className="text-xs text-gray-400">{card.desc}</p>
                    </div>
                ))}
            </div>

            {manualTopUpEnabled && (
                <div className="mb-6 rounded-xl border border-dashed border-blue-200 bg-blue-50/70 px-4 py-4">
                    <p className="font-medium text-blue-900">Need to add funds manually?</p>
                    <p className="mt-0.5 text-sm text-blue-800/80">
                        Send a larger amount to CityShop’s MoMo or bank account, then submit proof for admin credit.
                    </p>
                    <Link
                        href={route('seller.wallet.manual-top-up')}
                        className="mt-2 inline-block text-sm font-medium text-blue-700 hover:underline"
                    >
                        Open manual top-up →
                    </Link>
                </div>
            )}

            <div id="withdraw" className="scroll-mt-24">
            <WithdrawalHighlight
                subtitle={
                    wallet.available_balance >= 10
                        ? `You can withdraw up to ${formatPrice(wallet.available_balance)} to your MoMo wallet. Pick your network first — MTN MoMo is most common.`
                        : 'Add a MoMo payout method below. Minimum withdrawal is GH₵10.'
                }
                className="mb-6"
            >
                {hasPendingWithdrawal ? (
                    <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                        You have a withdrawal in processing. Please wait for it to complete (usually within 1 hour) before submitting another.
                    </p>
                ) : payoutMethods.length === 0 ? (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600">
                            Save your <strong>MoMo network and number</strong> first, then you can request a withdrawal.
                        </p>
                        <Button
                            type="button"
                            className="bg-orange-500 hover:bg-orange-600"
                            onClick={() => setShowAddMethod(true)}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add MoMo payout method
                        </Button>
                    </div>
                ) : (
                    <form onSubmit={submitWithdraw} className="space-y-5">
                        {withdrawStep === 'method' && (
                            <div className="space-y-3">
                                <Label className="text-base font-semibold">1. Choose MoMo account</Label>
                                {payoutMethods.map((method) => {
                                    const meta = momoNetworkMeta(method.network);
                                    const selected = withdrawForm.data.payout_method_id === String(method.id);

                                    return (
                                        <label
                                            key={method.id}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-3 rounded-xl border-2 p-4 transition',
                                                selected ? (meta?.selectedClass ?? 'border-orange-500 bg-orange-50') : 'border-gray-200 hover:border-gray-300',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="payout_method_id"
                                                value={method.id}
                                                checked={selected}
                                                onChange={() => withdrawForm.setData('payout_method_id', String(method.id))}
                                                className="sr-only"
                                            />
                                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm">
                                                <WalletIcon className={cn('h-5 w-5', meta?.accent ?? 'text-orange-600')} />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold text-gray-900">{momoNetworkLabel(method.network)}</p>
                                                    {method.is_default && (
                                                        <span className="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold uppercase text-orange-700">
                                                            Default
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="text-sm text-gray-600">{method.account_number}</p>
                                                <p className="text-xs text-gray-500">{method.account_name}</p>
                                            </div>
                                            {selected && <Check className="h-5 w-5 shrink-0 text-orange-600" />}
                                        </label>
                                    );
                                })}
                            </div>
                        )}

                        {withdrawStep === 'amount' && selectedMethod && (
                            <div className="space-y-4">
                                <div className="rounded-xl border border-gray-200 bg-white p-3 text-sm">
                                    <p className="text-gray-500">Payout to</p>
                                    <p className="font-semibold text-gray-900">{momoNetworkLabel(selectedMethod.network)}</p>
                                    <p className="text-gray-600">{selectedMethod.account_number} · {selectedMethod.account_name}</p>
                                </div>
                                <div>
                                    <Label className="text-base font-semibold">2. Enter amount (GH₵)</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="10"
                                        max={wallet.available_balance}
                                        value={withdrawForm.data.amount}
                                        onChange={(e) => withdrawForm.setData('amount', e.target.value)}
                                        required
                                        className="mt-2 text-lg"
                                    />
                                    <InputError message={withdrawForm.errors.amount} />
                                    <button
                                        type="button"
                                        className="mt-2 text-sm font-medium text-orange-600 hover:underline"
                                        onClick={() => withdrawForm.setData('amount', String(wallet.available_balance))}
                                    >
                                        Withdraw all ({formatPrice(wallet.available_balance)})
                                    </button>
                                    <p className="mt-2 text-xs text-gray-500">Minimum withdrawal: GH₵10</p>
                                </div>
                            </div>
                        )}

                        {withdrawStep === 'review' && selectedMethod && (
                            <div className="rounded-xl border-2 border-orange-200 bg-white p-4 text-sm space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-orange-600">Review MoMo payout</p>
                                <p><span className="text-gray-500">Network:</span> <strong>{momoNetworkLabel(selectedMethod.network)}</strong></p>
                                <p><span className="text-gray-500">Number:</span> {selectedMethod.account_number}</p>
                                <p><span className="text-gray-500">Name:</span> {selectedMethod.account_name}</p>
                                <p className="text-2xl font-bold text-orange-500">{formatPrice(parseFloat(withdrawForm.data.amount) || 0)}</p>
                                <p className="text-xs text-gray-500">Admin reviews your request and pays you manually via MoMo.</p>
                            </div>
                        )}

                        <div className="flex gap-2">
                            {withdrawStep !== 'method' && (
                                <Button type="button" variant="outline" onClick={() => setWithdrawStep(withdrawStep === 'review' ? 'amount' : 'method')}>
                                    Back
                                </Button>
                            )}
                            <Button type="submit" disabled={withdrawForm.processing} className="flex-1 bg-orange-500 py-6 text-base hover:bg-orange-600">
                                {withdrawForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                {withdrawStep === 'review' ? (
                                    <><Check className="mr-2 h-4 w-4" /> Submit withdrawal</>
                                ) : (
                                    'Continue'
                                )}
                            </Button>
                        </div>
                    </form>
                )}
            </WithdrawalHighlight>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-semibold text-gray-900">MoMo payout methods</h3>
                            <p className="mt-1 text-sm text-gray-500">Save the network and number where you receive withdrawals.</p>
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={() => setShowAddMethod(!showAddMethod)}>
                            <Plus className="mr-1 h-4 w-4" /> Add
                        </Button>
                    </div>

                    {showAddMethod && (
                        <form onSubmit={saveMethod} className="mt-4 space-y-4 rounded-xl border-2 border-dashed border-orange-200 bg-orange-50/40 p-4">
                            <MomoNetworkPicker
                                value={methodForm.data.network}
                                onChange={(network) => methodForm.setData('network', network)}
                                hint="Step 1 — pick your MoMo network. MTN MoMo is selected by default."
                            />
                            <div>
                                <Label>Mobile number</Label>
                                <Input value={methodForm.data.account_number} onChange={(e) => methodForm.setData('account_number', e.target.value)} required className="mt-1 bg-white" placeholder="0XX XXX XXXX" />
                                <InputError message={methodForm.errors.account_number} />
                            </div>
                            <div>
                                <Label>Account name</Label>
                                <Input value={methodForm.data.account_name} onChange={(e) => methodForm.setData('account_name', e.target.value)} required className="mt-1 bg-white" />
                                <InputError message={methodForm.errors.account_name} />
                            </div>
                            <Button type="submit" disabled={methodForm.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                                Save MoMo payout method
                            </Button>
                        </form>
                    )}

                    <ul className="mt-4 space-y-2">
                        {payoutMethods.map((method) => {
                            const meta = momoNetworkMeta(method.network);

                            return (
                                <li key={method.id} className="flex items-center justify-between rounded-xl border border-gray-100 p-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', meta?.badgeClass ?? 'bg-gray-100 text-gray-700')}>
                                                {meta?.shortLabel ?? method.network}
                                            </span>
                                            {method.is_default && <span className="text-xs font-medium text-orange-500">Default</span>}
                                        </div>
                                        <p className="mt-1 font-medium text-gray-900">{momoNetworkLabel(method.network)}</p>
                                        <p className="text-sm text-gray-500">{method.account_number} · {method.account_name}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-red-500"
                                        onClick={() => router.delete(route('seller.wallet.payout-methods.destroy', method.id))}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </li>
                            );
                        })}
                        {payoutMethods.length === 0 && !showAddMethod && (
                            <p className="text-sm text-gray-500">Add your MoMo network and number to withdraw funds.</p>
                        )}
                    </ul>
                </div>

                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-1">
                    <h3 className="font-semibold text-gray-900">Quick tips</h3>
                    <ul className="mt-4 space-y-3 text-sm text-gray-600">
                        <li className="rounded-lg bg-gray-50 p-3"><strong className="text-gray-900">MTN MoMo</strong> is the most used network in Ghana.</li>
                        <li className="rounded-lg bg-gray-50 p-3">Use the name registered on your MoMo account.</li>
                        <li className="rounded-lg bg-gray-50 p-3">Withdrawals are reviewed and paid within 1 hour.</li>
                    </ul>
                </div>
            </div>

            <div id="history" className="mt-8 scroll-mt-24 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-4">
                    <div>
                        <h3 className="font-semibold text-gray-900">Withdrawal history</h3>
                        <p className="text-xs text-gray-500">Date, amount, destination, and status</p>
                    </div>
                    <Link
                        href={route('seller.wallet.withdrawals')}
                        className="inline-flex items-center gap-1 text-sm font-medium text-orange-600 hover:underline"
                    >
                        View all
                        <ChevronRight className="h-4 w-4" />
                    </Link>
                </div>
                {withdrawals.data.length === 0 ? (
                    <p className="px-5 py-8 text-sm text-gray-500">No withdrawal requests yet.</p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {withdrawals.data.map((w) => (
                            <li key={w.id}>
                                <Link
                                    href={route('seller.wallet.withdrawals.show', w.id)}
                                    className="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-orange-50/40"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${statusColor[w.status] ?? 'bg-gray-100'}`}>
                                                {statusLabel[w.status] ?? w.status}
                                            </span>
                                            <span className="text-xs text-gray-400">{formatDate(w.created_at)}</span>
                                        </div>
                                        <p className="mt-1 text-sm text-gray-700">
                                            {momoNetworkLabel(w.network)} · {w.momo_number}
                                        </p>
                                        {w.proof_path && (
                                            <span className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-orange-600">
                                                <Download className="h-3 w-3" /> Proof available
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <p className="text-base font-bold text-gray-900">{formatPrice(w.amount)}</p>
                                        <ChevronRight className="h-4 w-4 text-gray-300" />
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="mt-8 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-4">
                    <div>
                        <h3 className="font-semibold text-gray-900">Transactions</h3>
                        <p className="text-xs text-gray-500">Date, amount, and balance after each entry</p>
                    </div>
                    <Link
                        href={route('seller.wallet.transactions')}
                        className="inline-flex items-center gap-1 text-sm font-medium text-orange-600 hover:underline"
                    >
                        View all
                        <ChevronRight className="h-4 w-4" />
                    </Link>
                </div>
                {transactions.data.length === 0 ? (
                    <p className="px-5 py-8 text-sm text-gray-500">No transactions yet.</p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {transactions.data.map((tx) => {
                            const isCredit = tx.amount > 0;
                            return (
                                <li key={tx.id}>
                                    <Link
                                        href={route('seller.wallet.transactions.show', tx.id)}
                                        className="flex flex-wrap items-start justify-between gap-3 px-5 py-4 transition hover:bg-orange-50/40"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                                                    {formatWalletTransactionType(tx.type)}
                                                </span>
                                                <span className="text-xs text-gray-400">{formatDate(tx.created_at)}</span>
                                            </div>
                                            <p className="mt-1 text-sm text-gray-700">{tx.description}</p>
                                        </div>
                                        <div className="flex shrink-0 items-start gap-2 text-right">
                                            <div>
                                                <p className={`text-sm font-bold ${isCredit ? 'text-emerald-600' : 'text-rose-600'}`}>
                                                    {isCredit ? '+' : ''}{formatPrice(tx.amount)}
                                                </p>
                                                <p className="mt-0.5 text-xs text-gray-500">
                                                    Bal. {formatPrice(tx.balance_after ?? 0)}
                                                </p>
                                            </div>
                                            <ChevronRight className="mt-0.5 h-4 w-4 text-gray-300" />
                                        </div>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </SellerLayout>
    );
}
