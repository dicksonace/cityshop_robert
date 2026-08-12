import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronRight, Download, LoaderCircle, Plus, RefreshCw, Trash2, Wallet as WalletIcon } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import GhanaBankPicker from '@/components/wallet/ghana-bank-picker';
import WithdrawalFeeNotice from '@/components/wallet/withdrawal-fee-notice';
import WithdrawalHighlight from '@/components/wallet/withdrawal-highlight';
import WalletBalanceCard from '@/components/seller/wallet-balance-card';
import SellerLayout from '@/layouts/seller-layout';
import { GHANA_BANKS, isGhanaBank, payoutNetworkLabel } from '@/lib/ghana-banks';
import { momoNetworkMeta } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';
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
    type?: string;
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
    paystackConfigured?: boolean;
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

type BankFeeTier = NonNullable<NonNullable<WalletProps['withdrawalFee']>['bank_tiers']>;

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
    settings: WalletProps['withdrawalFee'],
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
    settings: WalletProps['withdrawalFee'],
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

/** Drop numeric leftovers (e.g. fee/minimum "10") from payout account name fields. */
function usablePayoutAccountName(value?: string | null): string {
    const name = (value ?? '').trim();
    if (!name) return '';
    if (/^\d+([.,]\d+)?$/.test(name)) return '';
    return name;
}

function formatDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GH', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function SellerWallet({
    wallet,
    transactions,
    withdrawals,
    payoutMethods,
    hasPendingWithdrawal,
    paystackConfigured = false,
    manualTopUpEnabled = false,
    withdrawalFee,
}: WalletProps) {
    const { auth } = usePage<SharedData>().props;
    const [withdrawStep, setWithdrawStep] = useState<'details' | 'amount' | 'review'>('details');
    const [showAddMethod, setShowAddMethod] = useState(false);
    const [refreshing, setRefreshing] = useState(false);

    const defaultMethod = payoutMethods.find((m) => m.is_default) ?? payoutMethods[0];
    const defaultIsBank = defaultMethod
        ? defaultMethod.type === 'bank' || isGhanaBank(defaultMethod.network)
        : false;

    const methodForm = useForm({
        payout_type: 'momo' as 'momo' | 'bank',
        network: 'mtn',
        account_number: '',
        account_name: '',
        is_default: true,
    });

    const setMethodPayoutType = (type: 'momo' | 'bank') => {
        methodForm.setData({
            ...methodForm.data,
            payout_type: type,
            network: type === 'bank' ? GHANA_BANKS[0]?.id ?? 'gcb' : 'mtn',
            account_number: '',
        });
    };

    const withdrawForm = useForm({
        amount: '',
        payout_type: (defaultIsBank ? 'bank' : 'momo') as 'momo' | 'bank',
        momo_number: defaultMethod?.account_number ?? auth.user?.mobile ?? '',
        // Never prefill — MoMo/bank registered name must be typed (avoids fee/min leftovers like "10").
        account_name: usablePayoutAccountName(defaultMethod?.account_name),
        network: defaultMethod?.network ?? 'mtn',
        payment_pin: '',
        payout_method_id: defaultMethod?.id?.toString() ?? '',
    });

    const withdrawAmount = Number(withdrawForm.data.amount) || 0;
    const activeFee = feeForPayoutType(withdrawalFee, withdrawForm.data.payout_type, withdrawAmount);
    const maxWithdraw = maxWithdrawableAmount(
        wallet?.available_balance ?? 0,
        withdrawalFee,
        withdrawForm.data.payout_type,
    );

    const setPayoutType = (type: 'momo' | 'bank') => {
        withdrawForm.setData({
            ...withdrawForm.data,
            payout_type: type,
            network: type === 'bank' ? GHANA_BANKS[0]?.id ?? 'gcb' : 'mtn',
            momo_number: type === 'momo' ? (auth.user?.mobile ?? '') : '',
            account_name: '',
            payout_method_id: '',
        });
    };

    const applySavedMethod = (method: PayoutMethod) => {
        const isBank = method.type === 'bank' || isGhanaBank(method.network);
        withdrawForm.setData({
            ...withdrawForm.data,
            payout_type: isBank ? 'bank' : 'momo',
            network: method.network,
            momo_number: method.account_number,
            account_name: usablePayoutAccountName(method.account_name),
            payout_method_id: String(method.id),
        });
        setWithdrawStep('details');
    };

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
                methodForm.setData({
                    payout_type: 'momo',
                    network: 'mtn',
                    account_number: '',
                    account_name: '',
                    is_default: true,
                });
                setShowAddMethod(false);
            },
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
        withdrawForm.post(route('seller.wallet.withdraw'), {
            onSuccess: () => {
                withdrawForm.reset('amount');
                withdrawForm.setData('payment_pin', '');
                setWithdrawStep('details');
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

            <div className="mb-6">
                <WalletBalanceCard
                    balance={wallet.available_balance}
                    pendingBalance={wallet.pending_balance}
                    withdrawHref="#withdraw"
                    historyHref="#history"
                    onRefresh={refreshBalance}
                    refreshing={refreshing}
                    paystackConfigured={paystackConfigured}
                    manualTopUpEnabled={manualTopUpEnabled}
                />
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                {[
                    { label: 'Lifetime earnings', value: wallet.total_earnings, desc: 'All time' },
                    { label: 'Withdrawn', value: wallet.withdrawn_amount, desc: 'Paid out' },
                    { label: 'Pending', value: wallet.pending_balance, desc: 'Clearing' },
                ].map((card) => (
                    <div
                        key={card.label}
                        className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm"
                    >
                        <p className="text-sm text-gray-500">{card.label}</p>
                        <p className="mt-1 text-2xl font-bold text-gray-900">{formatPrice(card.value)}</p>
                        <p className="text-xs text-gray-400">{card.desc}</p>
                    </div>
                ))}
            </div>

            <div id="withdraw" className="scroll-mt-24">
            <WithdrawalHighlight
                title="Withdraw funds"
                subtitle={
                    wallet.available_balance >= 10
                        ? `You can withdraw up to ${formatPrice(maxWithdraw)} to MoMo or bank.`
                        : 'Choose MoMo or a Ghana bank, then enter your payout details. Minimum withdrawal is GH₵10.'
                }
                className="mb-6"
            >
                <div className="mb-5 flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-4 text-white shadow-sm">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-orange-100">Available to withdraw</p>
                        <p className="mt-1 text-2xl font-black tracking-tight">{formatPrice(maxWithdraw)}</p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        className="shrink-0 bg-orange-700 text-white hover:bg-orange-800"
                        disabled={maxWithdraw < 10}
                        onClick={() => {
                            withdrawForm.setData('amount', String(maxWithdraw));
                            if (withdrawStep === 'details') setWithdrawStep('amount');
                        }}
                    >
                        Withdraw all
                    </Button>
                </div>

                {hasPendingWithdrawal ? (
                    <p className="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                        You have a withdrawal in processing (usually within 15 minutes). You can still request another with your remaining balance.
                    </p>
                ) : null}

                {payoutMethods.length > 0 && withdrawStep === 'details' && (
                    <div className="mb-4 space-y-2">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Quick fill from saved</p>
                        <div className="flex flex-wrap gap-2">
                            {payoutMethods.map((method) => {
                                const isBank = method.type === 'bank' || isGhanaBank(method.network);
                                const selected = withdrawForm.data.payout_method_id === String(method.id);
                                return (
                                    <button
                                        key={method.id}
                                        type="button"
                                        onClick={() => applySavedMethod(method)}
                                        className={cn(
                                            'rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                                            selected
                                                ? 'border-orange-500 bg-orange-50 text-orange-800'
                                                : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300',
                                        )}
                                    >
                                        {isBank ? 'Bank' : 'MoMo'} · {payoutNetworkLabel(method.network)} · {method.account_number}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                <form onSubmit={submitWithdraw} className="space-y-5">
                    {withdrawStep === 'details' && (
                        <div className="space-y-4">
                            <div>
                                <Label className="text-base font-semibold">1. How should we pay you?</Label>
                                <div className="mt-2 grid grid-cols-2 gap-2">
                                    {([
                                        { id: 'momo' as const, label: 'Mobile Money' },
                                        { id: 'bank' as const, label: 'Bank' },
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
                            <WithdrawalFeeNotice
                                payoutType={withdrawForm.data.payout_type}
                                fee={activeFee}
                                amount={withdrawAmount}
                                settings={withdrawalFee}
                            />
                            {withdrawForm.data.payout_type === 'momo' ? (
                                <MomoNetworkPicker
                                    value={withdrawForm.data.network}
                                    onChange={(network) =>
                                        withdrawForm.setData({
                                            ...withdrawForm.data,
                                            network,
                                            payout_method_id: '',
                                        })
                                    }
                                    hint="Choose your network. MTN MoMo is the most common. Pick the network of the number below."
                                />
                            ) : (
                                <div>
                                    <GhanaBankPicker
                                        value={withdrawForm.data.network}
                                        onChange={(network) =>
                                            withdrawForm.setData({
                                                ...withdrawForm.data,
                                                network,
                                                payout_method_id: '',
                                            })
                                        }
                                    />
                                    <InputError message={withdrawForm.errors.network} />
                                </div>
                            )}
                            <div>
                                <Label className="text-base font-semibold">2. Where should the money go?</Label>
                                <div className="mt-2 space-y-3">
                                    <div>
                                        <Label>{withdrawForm.data.payout_type === 'bank' ? 'Account number' : 'MoMo number'}</Label>
                                        <Input
                                            value={withdrawForm.data.momo_number}
                                            onChange={(e) =>
                                                withdrawForm.setData({
                                                    ...withdrawForm.data,
                                                    momo_number: e.target.value,
                                                    payout_method_id: '',
                                                })
                                            }
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
                                            onChange={(e) =>
                                                withdrawForm.setData({
                                                    ...withdrawForm.data,
                                                    account_name: e.target.value,
                                                    payout_method_id: '',
                                                })
                                            }
                                            required
                                            className="mt-1"
                                            placeholder={
                                                withdrawForm.data.payout_type === 'bank'
                                                    ? 'Name on bank account'
                                                    : 'Name on MoMo account'
                                            }
                                        />
                                        <InputError message={withdrawForm.errors.account_name} />
                                    </div>
                                </div>
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
                                <Label className="text-base font-semibold">3. How much?</Label>
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
                                <p className="mt-2 text-xs text-gray-500">Minimum withdrawal: GH₵10</p>
                                <div className="mt-3">
                                    <WithdrawalFeeNotice
                                        payoutType={withdrawForm.data.payout_type}
                                        fee={activeFee}
                                        amount={withdrawAmount}
                                        settings={withdrawalFee}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {withdrawStep === 'review' && (
                        <div className="space-y-4">
                            <div className="rounded-xl border-2 border-orange-200 bg-white p-4 text-sm space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    Review {withdrawForm.data.payout_type === 'bank' ? 'bank' : 'MoMo'} payout
                                </p>
                                <p>
                                    <span className="text-gray-500">Destination:</span>{' '}
                                    <strong>{payoutNetworkLabel(withdrawForm.data.network)}</strong>
                                </p>
                                <p>
                                    <span className="text-gray-500">Number:</span> {withdrawForm.data.momo_number}
                                </p>
                                <p>
                                    <span className="text-gray-500">Name:</span> {withdrawForm.data.account_name}
                                </p>
                                <p className="text-2xl font-bold text-orange-500">
                                    {formatPrice(parseFloat(withdrawForm.data.amount) || 0)}
                                </p>
                                {activeFee > 0 && (
                                    <div className="space-y-0.5 text-xs text-gray-600">
                                        <p>Withdrawal fee: {formatPrice(activeFee)}</p>
                                        <p className="font-semibold text-gray-800">
                                            Total deducted:{' '}
                                            {formatPrice((parseFloat(withdrawForm.data.amount) || 0) + activeFee)}
                                        </p>
                                    </div>
                                )}
                                <p className="text-xs text-gray-500">Usually processed within 15 minutes and sometimes instant.</p>
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
                                    Manage your PIN in{' '}
                                    <a href={route('payment-pin.edit')} className="text-orange-600 underline">
                                        Settings → Payment PIN
                                    </a>
                                    .
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="flex gap-2">
                        {withdrawStep !== 'details' && (
                            <Button
                                type="button"
                                variant="outline"
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
                            {withdrawStep === 'review' ? (
                                <>
                                    <Check className="mr-2 h-4 w-4" /> Submit withdrawal
                                </>
                            ) : withdrawStep === 'amount' ? (
                                'Review withdrawal'
                            ) : (
                                'Continue'
                            )}
                        </Button>
                    </div>
                </form>
            </WithdrawalHighlight>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-semibold text-gray-900">Payout methods</h3>
                            <p className="mt-1 text-sm text-gray-500">Save MoMo or bank accounts where you receive withdrawals.</p>
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={() => setShowAddMethod(!showAddMethod)}>
                            <Plus className="mr-1 h-4 w-4" /> Add
                        </Button>
                    </div>

                    {showAddMethod && (
                        <form onSubmit={saveMethod} className="mt-4 space-y-4 rounded-xl border-2 border-dashed border-orange-200 bg-orange-50/40 p-4">
                            <div>
                                <Label className="font-semibold">Payout type</Label>
                                <div className="mt-2 grid grid-cols-2 gap-2">
                                    {([
                                        { id: 'momo' as const, label: 'Mobile Money' },
                                        { id: 'bank' as const, label: 'Bank account' },
                                    ]).map((option) => (
                                        <button
                                            key={option.id}
                                            type="button"
                                            onClick={() => setMethodPayoutType(option.id)}
                                            className={cn(
                                                'rounded-xl border-2 px-3 py-3 text-sm font-semibold transition',
                                                methodForm.data.payout_type === option.id
                                                    ? 'border-orange-500 bg-orange-50 text-orange-800'
                                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300',
                                            )}
                                        >
                                            {option.label}
                                        </button>
                                    ))}
                                </div>
                                <InputError message={methodForm.errors.payout_type} />
                            </div>
                            {methodForm.data.payout_type === 'momo' ? (
                                <MomoNetworkPicker
                                    value={methodForm.data.network}
                                    onChange={(network) => methodForm.setData('network', network)}
                                    hint="Pick your MoMo network. MTN MoMo is selected by default."
                                />
                            ) : (
                                <div>
                                    <GhanaBankPicker
                                        value={methodForm.data.network}
                                        onChange={(network) => methodForm.setData('network', network)}
                                    />
                                    <InputError message={methodForm.errors.network} />
                                </div>
                            )}
                            <div>
                                <Label>{methodForm.data.payout_type === 'bank' ? 'Account number' : 'Mobile number'}</Label>
                                <Input
                                    value={methodForm.data.account_number}
                                    onChange={(e) => methodForm.setData('account_number', e.target.value)}
                                    required
                                    className="mt-1 bg-white"
                                    placeholder={methodForm.data.payout_type === 'bank' ? 'Bank account number' : '0XX XXX XXXX'}
                                />
                                <InputError message={methodForm.errors.account_number} />
                            </div>
                            <div>
                                <Label>Account name</Label>
                                <Input value={methodForm.data.account_name} onChange={(e) => methodForm.setData('account_name', e.target.value)} required className="mt-1 bg-white" />
                                <InputError message={methodForm.errors.account_name} />
                            </div>
                            <Button type="submit" disabled={methodForm.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                                Save payout method
                            </Button>
                        </form>
                    )}

                    <ul className="mt-4 space-y-2">
                        {payoutMethods.map((method) => {
                            const meta = momoNetworkMeta(method.network);
                            const isBank = method.type === 'bank' || isGhanaBank(method.network);

                            return (
                                <li key={method.id} className="flex items-center justify-between rounded-xl border border-gray-100 p-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', meta?.badgeClass ?? 'bg-gray-100 text-gray-700')}>
                                                {meta?.shortLabel ?? (isBank ? 'Bank' : method.network)}
                                            </span>
                                            {method.is_default && <span className="text-xs font-medium text-orange-500">Default</span>}
                                        </div>
                                        <p className="mt-1 font-medium text-gray-900">{payoutNetworkLabel(method.network)}</p>
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
                            <p className="text-sm text-gray-500">Add a MoMo or bank account to withdraw funds.</p>
                        )}
                    </ul>
                </div>

                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-1">
                    <h3 className="font-semibold text-gray-900">Quick tips</h3>
                    <ul className="mt-4 space-y-3 text-sm text-gray-600">
                        <li className="rounded-lg bg-gray-50 p-3"><strong className="text-gray-900">MoMo or bank</strong> — choose whichever account you want paid into. Bank withdrawals include a fee (GH₵10 below GH₵1,000, GH₵20 from GH₵1,000).</li>
                        <li className="rounded-lg bg-gray-50 p-3">Use the name registered on the MoMo or bank account.</li>
                        <li className="rounded-lg bg-gray-50 p-3">Usually processed within 15 minutes and sometimes instant.</li>
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
                                            {payoutNetworkLabel(w.network)} · {w.momo_number}
                                        </p>
                                        {(w.fee ?? 0) > 0 && (
                                            <p className="mt-0.5 text-xs text-gray-500">Fee {formatPrice(w.fee ?? 0)}</p>
                                        )}
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
                                                    {formatPrice(tx.balance_before ?? 0)} → {formatPrice(tx.balance_after ?? 0)}
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
