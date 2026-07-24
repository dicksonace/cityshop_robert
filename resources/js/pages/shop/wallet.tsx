import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, LoaderCircle, RefreshCw, Wallet as WalletIcon } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ManualTopUpPrompt from '@/components/wallet/manual-top-up-prompt';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import WithdrawalHighlight from '@/components/wallet/withdrawal-highlight';
import ShopLayout from '@/layouts/shop-layout';
import { momoNetworkLabel } from '@/lib/momo-networks';
import { SharedData } from '@/types';
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
    pending: 'Processing',
    processing: 'Processing',
    paid: 'Paid out',
    rejected: 'Rejected',
};

export default function BuyerWallet({
    wallet,
    transactions,
    withdrawals,
    hasPendingWithdrawal,
    paystackConfigured,
    manualTopUpEnabled,
}: BuyerWalletProps) {
    const { auth, flash } = usePage<SharedData>().props;
    const [refreshing, setRefreshing] = useState(false);
    const [withdrawStep, setWithdrawStep] = useState<'details' | 'amount' | 'review'>('details');

    const addFundsForm = useForm({ amount: '', method: 'momo' });
    const withdrawForm = useForm({
        amount: '',
        momo_number: auth.user?.mobile ?? '',
        account_name: auth.user?.name ?? '',
        network: 'mtn',
    });

    const refreshBalance = () => {
        setRefreshing(true);
        router.reload({
            only: ['wallet', 'transactions', 'withdrawals', 'hasPendingWithdrawal'],
            onFinish: () => setRefreshing(false),
        });
    };

    const submitAddFunds: FormEventHandler = (e) => {
        e.preventDefault();
        addFundsForm.post(route('wallet.add-funds'), { onSuccess: () => addFundsForm.reset() });
    };

    const submitWithdraw: FormEventHandler = (e) => {
        e.preventDefault();
        if (withdrawStep === 'details') {
            if (!withdrawForm.data.network || !withdrawForm.data.momo_number.trim() || !withdrawForm.data.account_name.trim()) {
                return;
            }
            setWithdrawStep('amount');
            return;
        }
        if (withdrawStep === 'amount') {
            if (!withdrawForm.data.amount || Number(withdrawForm.data.amount) < 10) {
                return;
            }
            setWithdrawStep('review');
            return;
        }
        withdrawForm.post(route('wallet.withdraw'), {
            onSuccess: () => {
                withdrawForm.reset('amount');
                setWithdrawStep('details');
            },
        });
    };

    return (
        <ShopLayout>
            <Head title="My Wallet" />
            <div className="mx-auto max-w-5xl px-4 py-8">
                <div className="mb-8 flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-white">
                        <WalletIcon className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">My Wallet</h1>
                        <p className="text-sm text-gray-500">Add funds, pay for orders, withdraw to MoMo, and view refunds.</p>
                    </div>
                </div>

                {(flash.success || flash.error) && (
                    <div
                        className={`mb-6 rounded-xl border px-4 py-3 text-sm ${
                            flash.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-red-200 bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div className="mb-8 rounded-2xl bg-gradient-to-r from-slate-900 via-blue-900 to-orange-900 p-6 text-white shadow-lg">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-sm text-white/70">Available balance</p>
                            <p className="mt-1 text-4xl font-bold">{formatPrice(wallet.available_balance)}</p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={refreshBalance}
                            disabled={refreshing}
                            className="shrink-0 border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                        >
                            <RefreshCw className={`mr-1.5 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    <p className="mt-2 text-xs text-white/60">Use your balance at checkout or withdraw to MoMo. Refunds are credited here.</p>
                </div>

                <div id="withdraw" className="mb-6 scroll-mt-24">
                    <WithdrawalHighlight
                        title="Withdraw to MoMo"
                        subtitle={
                            wallet.available_balance >= 10
                                ? `You can withdraw up to ${formatPrice(wallet.available_balance)}. Pick your network first — MTN MoMo is most common.`
                                : 'Pick your mobile money network first, then enter your MoMo details. Minimum withdrawal is GH₵10.'
                        }
                    >
                        {hasPendingWithdrawal ? (
                            <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                                You have a withdrawal in processing. Please wait for it to complete (usually within 1 hour) before submitting another.
                            </p>
                        ) : (
                            <form onSubmit={submitWithdraw} className="space-y-5">
                                {withdrawStep === 'details' && (
                                    <div className="space-y-4">
                                        <MomoNetworkPicker
                                            value={withdrawForm.data.network}
                                            onChange={(network) => withdrawForm.setData('network', network)}
                                            hint="Step 1 — choose MTN MoMo, Telecel, or AirtelTigo."
                                        />
                                        <div>
                                            <Label>MoMo number</Label>
                                            <Input
                                                value={withdrawForm.data.momo_number}
                                                onChange={(e) => withdrawForm.setData('momo_number', e.target.value)}
                                                required
                                                className="mt-1"
                                                placeholder="0XX XXX XXXX"
                                                inputMode="tel"
                                            />
                                            <InputError message={withdrawForm.errors.momo_number} />
                                        </div>
                                        <div>
                                            <Label>Account name</Label>
                                            <Input
                                                value={withdrawForm.data.account_name}
                                                onChange={(e) => withdrawForm.setData('account_name', e.target.value)}
                                                required
                                                className="mt-1"
                                                placeholder="Name on MoMo account"
                                            />
                                            <InputError message={withdrawForm.errors.account_name} />
                                        </div>
                                    </div>
                                )}

                                {withdrawStep === 'amount' && (
                                    <div className="space-y-4">
                                        <div className="rounded-xl border border-gray-200 bg-white p-3 text-sm">
                                            <p className="text-gray-500">Payout to</p>
                                            <p className="font-semibold text-gray-900">{momoNetworkLabel(withdrawForm.data.network)}</p>
                                            <p className="text-gray-600">
                                                {withdrawForm.data.momo_number} · {withdrawForm.data.account_name}
                                            </p>
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

                                {withdrawStep === 'review' && (
                                    <div className="space-y-4">
                                        <div className="rounded-xl border-2 border-orange-200 bg-orange-50/60 p-4">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-orange-600">Review MoMo payout</p>
                                            <p className="mt-2 text-sm text-gray-600">
                                                {momoNetworkLabel(withdrawForm.data.network)} · {withdrawForm.data.momo_number}
                                            </p>
                                            <p className="text-sm text-gray-500">{withdrawForm.data.account_name}</p>
                                            <p className="mt-3 text-2xl font-bold text-orange-500">
                                                {formatPrice(parseFloat(withdrawForm.data.amount) || 0)}
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500">Usually paid within 1 hour after admin approval.</p>
                                        </div>
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-2">
                                    {withdrawStep !== 'details' && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="flex-1"
                                            onClick={() => setWithdrawStep(withdrawStep === 'review' ? 'amount' : 'details')}
                                        >
                                            Back
                                        </Button>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={withdrawForm.processing || wallet.available_balance < 10}
                                        className="flex-1 bg-orange-500 py-6 text-base hover:bg-orange-600"
                                    >
                                        {withdrawForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                        {withdrawStep === 'details' && (
                                            <>
                                                Continue
                                                <Check className="ml-2 h-4 w-4" />
                                            </>
                                        )}
                                        {withdrawStep === 'amount' && 'Review withdrawal'}
                                        {withdrawStep === 'review' && 'Request withdrawal to MoMo'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </WithdrawalHighlight>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <form onSubmit={submitAddFunds} className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Add Funds</h3>
                        <p className="mt-1 text-sm text-gray-500">Top up via Paystack (MoMo or card).</p>
                        <div className="mt-4 space-y-3">
                            <div>
                                <Label>Amount (GH₵)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="5"
                                    value={addFundsForm.data.amount}
                                    onChange={(e) => addFundsForm.setData('amount', e.target.value)}
                                    required
                                    className="mt-1"
                                />
                                <InputError message={addFundsForm.errors.amount} />
                            </div>
                            <div>
                                <Label>Payment method</Label>
                                <select
                                    value={addFundsForm.data.method}
                                    onChange={(e) => addFundsForm.setData('method', e.target.value)}
                                    className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                >
                                    <option value="momo">Mobile Money</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                            <Button type="submit" disabled={addFundsForm.processing || !paystackConfigured} className="w-full bg-green-600 hover:bg-green-700">
                                {addFundsForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                {paystackConfigured ? 'Pay to Add Funds' : 'Top-up unavailable'}
                            </Button>
                            {!paystackConfigured && (
                                <p className="text-xs text-amber-600">Online top-up requires Paystack to be configured.</p>
                            )}
                            {manualTopUpEnabled && (
                                <ManualTopUpPrompt href={route('wallet.manual-top-up')} />
                            )}
                        </div>
                    </form>

                    <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Withdrawal requests</h3>
                        <p className="mt-1 text-sm text-gray-500">Track MoMo payouts like sellers do.</p>
                        {withdrawals.data.length === 0 ? (
                            <p className="mt-4 text-sm text-gray-500">No withdrawal requests yet.</p>
                        ) : (
                            <div className="mt-4 divide-y">
                                {withdrawals.data.map((w) => (
                                    <div key={w.id} className="flex items-start justify-between gap-3 py-3">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${statusColor[w.status] ?? 'bg-gray-100 text-gray-700'}`}>
                                                    {statusLabel[w.status] ?? w.status}
                                                </span>
                                                <span className="text-xs text-gray-400">{momoNetworkLabel(w.network)}</span>
                                            </div>
                                            <p className="mt-1 text-sm text-gray-700">{w.momo_number}</p>
                                            <p className="text-xs text-gray-400">{formatDate(w.created_at)}</p>
                                        </div>
                                        <p className="shrink-0 font-semibold text-orange-600">{formatPrice(w.amount)}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="mt-8 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Transaction History</h3>
                    {transactions.data.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No transactions yet.</p>
                    ) : (
                        <>
                            <div className="mt-4 divide-y">
                                {transactions.data.map((tx) => {
                                    const isCredit = tx.amount > 0;
                                    return (
                                        <div key={tx.id} className="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                                        {formatWalletTransactionType(tx.type)}
                                                    </span>
                                                    {tx.reference && <span className="text-xs text-gray-400">{tx.reference}</span>}
                                                </div>
                                                <p className="mt-1 text-sm text-gray-600">{tx.description}</p>
                                                <p className="text-xs text-gray-400">{formatDate(tx.created_at)}</p>
                                            </div>
                                            <p className={`shrink-0 text-sm font-semibold sm:text-base ${isCredit ? 'text-green-600' : 'text-red-600'}`}>
                                                {isCredit ? '+' : ''}{formatPrice(tx.amount)}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                            {transactions.last_page > 1 && (
                                <div className="mt-6 flex flex-wrap gap-2">
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
