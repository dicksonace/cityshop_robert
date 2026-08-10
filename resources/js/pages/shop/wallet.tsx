import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, LoaderCircle, RefreshCw, Wallet as WalletIcon } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GhanaBankPicker from '@/components/wallet/ghana-bank-picker';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import RechargeModal from '@/components/wallet/recharge-modal';
import { WalletTransactionReceiptButton } from '@/components/wallet/wallet-receipt-modal';
import WithdrawalHighlight from '@/components/wallet/withdrawal-highlight';
import ShopLayout from '@/layouts/shop-layout';
import { GHANA_BANKS, payoutNetworkLabel } from '@/lib/ghana-banks';
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
    manualTopUpEnabled?: boolean;
    withdrawalFee?: {
        enabled: boolean;
        amount: number;
        percent?: number;
        mode?: 'flat' | 'percent';
        applies_to: 'bank' | 'momo' | 'all' | 'none';
        auto_paystack?: boolean;
        bank_tiers?: { min: number; max: number | null; fee: number }[];
    };
}

type BankFeeTier = NonNullable<NonNullable<BuyerWalletProps['withdrawalFee']>['bank_tiers']>;

function feeFromBankTiers(amount: number, tiers: BankFeeTier, fallback = 0): number {
    if (amount <= 0 || tiers.length === 0) return Math.max(0, fallback);
    for (const tier of tiers) {
        if (amount + 0.0001 >= tier.min && (tier.max == null || amount <= tier.max + 0.0001)) {
            return tier.fee;
        }
    }
    if (amount < tiers[0].min) return tiers[0].fee;
    for (let i = 0; i < tiers.length - 1; i++) {
        const currMax = tiers[i].max;
        const nextMin = tiers[i + 1].min;
        if (currMax != null && amount > currMax && amount < nextMin) return tiers[i + 1].fee;
    }
    return tiers[tiers.length - 1].fee;
}

function feeForPayoutType(
    settings: BuyerWalletProps['withdrawalFee'],
    payoutType: 'momo' | 'bank',
    amount = 0,
): number {
    if (!settings?.enabled) return 0;
    if (settings.mode === 'percent') {
        const percent = settings.percent ?? 0;
        return percent > 0 ? Math.round(amount * (percent / 100) * 100) / 100 : 0;
    }
    if (settings.applies_to === 'none') return 0;
    if (!(settings.applies_to === 'all' || settings.applies_to === payoutType)) return 0;
    if (payoutType === 'bank' && (settings.bank_tiers?.length ?? 0) > 0) {
        return feeFromBankTiers(amount, settings.bank_tiers!, settings.amount ?? 0);
    }
    return settings.amount > 0 ? settings.amount : 0;
}

function maxWithdrawableAmount(
    balance: number,
    settings: BuyerWalletProps['withdrawalFee'],
    payoutType: 'momo' | 'bank',
): number {
    if (settings?.mode === 'percent' && (settings.percent ?? 0) > 0) {
        return Math.max(0, Math.floor((balance / (1 + (settings.percent ?? 0) / 100)) * 100) / 100);
    }
    let lo = 0;
    let hi = balance;
    for (let i = 0; i < 48; i++) {
        const mid = (lo + hi) / 2;
        const fee = feeForPayoutType(settings, payoutType, mid);
        if (mid + fee <= balance + 1e-9) lo = mid;
        else hi = mid;
    }
    let amount = Math.round(lo * 100) / 100;
    if (amount + feeForPayoutType(settings, payoutType, amount) > balance + 1e-9) {
        amount = Math.round((amount - 0.01) * 100) / 100;
    }
    return Math.max(0, amount);
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
    withdrawalFee,
}: BuyerWalletProps) {
    const { auth, flash } = usePage<SharedData>().props;
    const [refreshing, setRefreshing] = useState(false);
    const [withdrawStep, setWithdrawStep] = useState<'details' | 'amount' | 'review'>('details');
    const [rechargeOpen, setRechargeOpen] = useState(false);

    const withdrawForm = useForm({
        amount: '',
        payout_type: 'momo' as 'momo' | 'bank',
        momo_number: auth.user?.mobile ?? '',
        // Leave blank — registered MoMo/bank name must be entered (not profile name / fee leftovers).
        account_name: '',
        network: 'mtn',
        payment_pin: '',
    });

    const canRecharge = paystackConfigured || !!manualTopUpEnabled;
    const withdrawAmount = Number(withdrawForm.data.amount) || 0;
    const activeFee = feeForPayoutType(withdrawalFee, withdrawForm.data.payout_type, withdrawAmount);
    const maxWithdraw = maxWithdrawableAmount(
        wallet.available_balance,
        withdrawalFee,
        withdrawForm.data.payout_type,
    );

    const setPayoutType = (type: 'momo' | 'bank') => {
        withdrawForm.setData({
            ...withdrawForm.data,
            payout_type: type,
            network: type === 'bank' ? GHANA_BANKS[0]?.id ?? 'gcb' : 'mtn',
            // Don't carry a MoMo phone into the bank account field (or the reverse).
            momo_number: type === 'momo' ? (auth.user?.mobile ?? '') : '',
            account_name: '',
        });
    };

    const refreshBalance = () => {
        setRefreshing(true);
        router.reload({
            only: ['wallet', 'transactions', 'withdrawals', 'hasPendingWithdrawal'],
            onFinish: () => setRefreshing(false),
        });
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
                        <p className="text-sm text-gray-500">Recharge, pay for orders, withdraw to MoMo or bank, and view refunds.</p>
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

                <div className="mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 via-orange-600 to-orange-700 p-6 text-white shadow-lg">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-orange-100">Available balance</p>
                            <p className="mt-1 text-4xl font-black tracking-tight">{formatPrice(wallet.available_balance)}</p>
                            {wallet.pending_balance > 0 && (
                                <p className="mt-2 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                                    Pending {formatPrice(wallet.pending_balance)}
                                </p>
                            )}
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
                    <div className="mt-5 flex h-12 overflow-hidden rounded-full bg-white shadow-md">
                        <a
                            href="#withdraw"
                            className="flex flex-1 items-center justify-center text-sm font-extrabold text-slate-900 transition hover:bg-slate-50"
                        >
                            Withdrawal
                        </a>
                        <button
                            type="button"
                            onClick={canRecharge ? () => setRechargeOpen(true) : undefined}
                            disabled={!canRecharge}
                            className={cn(
                                'flex flex-1 items-center justify-center rounded-full text-sm font-extrabold text-white transition',
                                canRecharge
                                    ? 'bg-orange-500 hover:bg-orange-600'
                                    : 'cursor-not-allowed bg-slate-400',
                            )}
                        >
                            Recharge
                        </button>
                    </div>
                    <p className="mt-3 text-xs text-orange-50/90">
                        Use your balance at checkout or withdraw to MoMo or bank. Refunds are credited here.
                    </p>
                </div>

                <RechargeModal
                    open={rechargeOpen}
                    onClose={() => setRechargeOpen(false)}
                    paystackConfigured={paystackConfigured}
                    manualTopUpEnabled={!!manualTopUpEnabled}
                    manualHref={route('wallet.manual-top-up')}
                    paystackRoute={route('wallet.add-funds')}
                    amountInputId="buyer-recharge-amount"
                />

                <div id="withdraw" className="mb-6 scroll-mt-24">
                    <WithdrawalHighlight
                        title="Withdraw funds"
                        subtitle={
                            wallet.available_balance >= 10
                                ? `You can withdraw up to ${formatPrice(wallet.available_balance)}. Choose MoMo or bank, then enter your details.`
                                : 'Choose MoMo or a Ghana bank, then enter your payout details. Minimum withdrawal is GH₵10.'
                        }
                    >
                        {hasPendingWithdrawal ? (
                            <p className="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                                You have a withdrawal in processing (usually within 15 minutes). You can still request another with your remaining balance.
                            </p>
                        ) : null}
                        <form onSubmit={submitWithdraw} className="space-y-5">
                            {withdrawStep === 'details' && (
                                    <div className="space-y-4">
                                        <div>
                                            <Label className="text-base font-semibold">1. Payout method</Label>
                                            <div className="mt-2 grid grid-cols-2 gap-2">
                                                {([
                                                    { id: 'momo' as const, label: 'Mobile Money' },
                                                    { id: 'bank' as const, label: 'Bank account' },
                                                ]).map((option) => (
                                                    <button
                                                        key={option.id}
                                                        type="button"
                                                        onClick={() => setPayoutType(option.id)}
                                                        className={cn(
                                                            'rounded-xl border-2 px-3 py-3 text-sm font-semibold transition',
                                                            withdrawForm.data.payout_type === option.id
                                                                ? 'border-orange-500 bg-orange-50 text-orange-800'
                                                                : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300',
                                                        )}
                                                    >
                                                        {option.label}
                                                    </button>
                                                ))}
                                            </div>
                                            <InputError message={withdrawForm.errors.payout_type} />
                                        </div>
                                        {withdrawForm.data.payout_type === 'momo' ? (
                                            <MomoNetworkPicker
                                                value={withdrawForm.data.network}
                                                onChange={(network) => withdrawForm.setData('network', network)}
                                                hint="Choose MTN MoMo, Telecel, or AirtelTigo."
                                            />
                                        ) : (
                                            <div>
                                                <GhanaBankPicker
                                                    value={withdrawForm.data.network}
                                                    onChange={(network) => withdrawForm.setData('network', network)}
                                                />
                                                <InputError message={withdrawForm.errors.network} />
                                            </div>
                                        )}
                                        <div>
                                            <Label>{withdrawForm.data.payout_type === 'bank' ? 'Account number' : 'MoMo number'}</Label>
                                            <Input
                                                value={withdrawForm.data.momo_number}
                                                onChange={(e) => withdrawForm.setData('momo_number', e.target.value)}
                                                required
                                                className="mt-1"
                                                placeholder={withdrawForm.data.payout_type === 'bank' ? 'Bank account number' : '0XX XXX XXXX'}
                                                inputMode={withdrawForm.data.payout_type === 'bank' ? 'numeric' : 'tel'}
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
                                                placeholder={withdrawForm.data.payout_type === 'bank' ? 'Name on bank account' : 'Name on MoMo account'}
                                            />
                                            <InputError message={withdrawForm.errors.account_name} />
                                        </div>
                                    </div>
                                )}

                                {withdrawStep === 'amount' && (
                                    <div className="space-y-4">
                                        <div className="rounded-xl border border-gray-200 bg-white p-3 text-sm">
                                            <p className="text-gray-500">Payout to</p>
                                            <p className="font-semibold text-gray-900">{payoutNetworkLabel(withdrawForm.data.network)}</p>
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
                                                onClick={() => withdrawForm.setData('amount', String(maxWithdraw))}
                                            >
                                                Withdraw all ({formatPrice(maxWithdraw)})
                                            </button>
                                            <p className="mt-2 text-xs text-gray-500">
                                                Minimum withdrawal: GH₵10
                                                {activeFee > 0
                                                    ? withdrawalFee?.mode === 'percent'
                                                        ? ` · ${withdrawalFee.percent ?? 0}% fee`
                                                        : withdrawForm.data.payout_type === 'bank' &&
                                                            (withdrawalFee?.bank_tiers?.length ?? 0) > 0 &&
                                                            withdrawAmount <= 0
                                                          ? ' · Bank fee by amount (GH₵10–1,000 → GH₵10 · GH₵10,000–25,000 → GH₵20)'
                                                          : ` · ${withdrawForm.data.payout_type === 'bank' ? 'Bank' : 'MoMo'} fee GH₵${activeFee.toFixed(2)}`
                                                    : ''}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {withdrawStep === 'review' && (
                                    <div className="space-y-4">
                                        <div className="rounded-xl border-2 border-orange-200 bg-orange-50/60 p-4">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-orange-600">
                                                Review {withdrawForm.data.payout_type === 'bank' ? 'bank' : 'MoMo'} payout
                                            </p>
                                            <p className="mt-2 text-sm text-gray-600">
                                                {payoutNetworkLabel(withdrawForm.data.network)} · {withdrawForm.data.momo_number}
                                            </p>
                                            <p className="text-sm text-gray-500">{withdrawForm.data.account_name}</p>
                                            <p className="mt-3 text-2xl font-bold text-orange-500">
                                                {formatPrice(parseFloat(withdrawForm.data.amount) || 0)}
                                            </p>
                                            {activeFee > 0 && (
                                                <div className="mt-2 space-y-0.5 text-xs text-gray-600">
                                                    <p>Withdrawal fee: {formatPrice(activeFee)}</p>
                                                    <p className="font-semibold text-gray-800">
                                                        Total deducted: {formatPrice((parseFloat(withdrawForm.data.amount) || 0) + activeFee)}
                                                    </p>
                                                </div>
                                            )}
                                            <p className="mt-1 text-xs text-gray-500">Usually processed within 15 minutes and sometimes instant.</p>
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-gray-700">Payment PIN</label>
                                            <input
                                                type="password"
                                                inputMode="numeric"
                                                maxLength={4}
                                                value={withdrawForm.data.payment_pin}
                                                onChange={(e) =>
                                                    withdrawForm.setData(
                                                        'payment_pin',
                                                        e.target.value.replace(/\D/g, '').slice(0, 4),
                                                    )
                                                }
                                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                                                placeholder="4-digit PIN"
                                                autoComplete="off"
                                            />
                                            <InputError message={withdrawForm.errors.payment_pin} />
                                            <p className="mt-1 text-xs text-gray-500">
                                                Set or reset your PIN in{' '}
                                                <a href={route('payment-pin.edit')} className="text-orange-600 underline">
                                                    Settings → Payment PIN
                                                </a>
                                                .
                                            </p>
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
                                        {withdrawStep === 'review' && 'Request withdrawal'}
                                    </Button>
                                </div>
                            </form>
                    </WithdrawalHighlight>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Withdrawal requests</h3>
                    <p className="mt-1 text-sm text-gray-500">Track MoMo and bank payouts.</p>
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
                                            <span className="text-xs text-gray-400">{payoutNetworkLabel(w.network)}</span>
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
                                        <div key={tx.id} className="flex flex-col gap-2 py-4">
                                            <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
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
                                            <div className="grid grid-cols-2 gap-2 rounded-lg bg-gray-50 px-3 py-2 text-xs sm:text-sm">
                                                <div>
                                                    <p className="text-gray-500">Before balance</p>
                                                    <p className="font-semibold text-gray-900">{formatPrice(tx.balance_before ?? 0)}</p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-gray-500">After balance</p>
                                                    <p className="font-semibold text-gray-900">{formatPrice(tx.balance_after ?? 0)}</p>
                                                </div>
                                            </div>
                                            <WalletTransactionReceiptButton tx={tx} />
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
