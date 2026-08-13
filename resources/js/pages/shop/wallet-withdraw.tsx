import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import GhanaBankPicker from '@/components/wallet/ghana-bank-picker';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import WithdrawalBalanceAlert, { withdrawalBalanceMessage } from '@/components/wallet/withdrawal-balance-alert';
import WithdrawalFeeNotice, { type WithdrawalFeeSettings } from '@/components/wallet/withdrawal-fee-notice';
import ShopLayout from '@/layouts/shop-layout';
import { GHANA_BANKS, payoutNetworkLabel } from '@/lib/ghana-banks';
import { feeForPayoutType, maxWithdrawableAmount } from '@/lib/withdrawal-fees';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';
import { formatPrice, Paginated, Wallet, Withdrawal } from '@/types/marketplace';

interface BuyerWithdrawProps {
    wallet: Wallet;
    withdrawals: Paginated<Withdrawal>;
    hasPendingWithdrawal: boolean;
    withdrawalFee?: WithdrawalFeeSettings;
    hasPaymentPin?: boolean;
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
    paid: 'Paid out',
    rejected: 'Rejected',
};

export default function BuyerWithdraw({
    wallet,
    withdrawals,
    hasPendingWithdrawal,
    withdrawalFee,
    hasPaymentPin = false,
}: BuyerWithdrawProps) {
    const { auth, flash } = usePage<SharedData>().props;
    const [step, setStep] = useState<'form' | 'review'>('form');
    const [stepError, setStepError] = useState<string | null>(null);

    const availableBalance = Number(wallet.available_balance) || 0;

    const form = useForm({
        amount: '',
        payout_type: 'momo' as 'momo' | 'bank',
        momo_number: auth.user?.mobile ?? '',
        account_name: '',
        network: 'mtn',
        payment_pin: '',
    });

    const amount = Number(form.data.amount) || 0;
    const activeFee = feeForPayoutType(withdrawalFee, form.data.payout_type, amount);
    const maxWithdraw = maxWithdrawableAmount(availableBalance, withdrawalFee, form.data.payout_type);
    const tooSmall = availableBalance < 10;
    const balanceOverLimit = !!withdrawalBalanceMessage(amount, activeFee, availableBalance);

    useEffect(() => {
        if (form.errors.payment_pin || form.errors.amount) {
            setStep('review');
        }
    }, [form.errors.payment_pin, form.errors.amount]);

    const setPayoutType = (type: 'momo' | 'bank') => {
        setStepError(null);
        form.setData({
            ...form.data,
            payout_type: type,
            network: type === 'bank' ? GHANA_BANKS[0]?.id ?? 'gcb' : 'mtn',
            momo_number: type === 'momo' ? (auth.user?.mobile ?? '') : '',
            account_name: '',
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setStepError(null);

        if (step === 'form') {
            if (!form.data.network || !form.data.momo_number.trim() || !form.data.account_name.trim()) {
                setStepError('Enter network, account number, and the name on the account to continue.');
                return;
            }
            if (!form.data.amount || amount < 10) {
                setStepError('Enter an amount of at least GH₵10.');
                return;
            }
            if (withdrawalBalanceMessage(amount, activeFee, availableBalance)) {
                setStepError(
                    activeFee > 0
                        ? `Not enough balance for amount + ${formatPrice(activeFee)} fee. Try Withdraw all (${formatPrice(maxWithdraw)}).`
                        : `Not enough available balance. You can withdraw up to ${formatPrice(maxWithdraw)}.`,
                );
                return;
            }
            setStep('review');
            return;
        }

        if (!hasPaymentPin) {
            setStepError('Set a 4-digit payment PIN in Settings before withdrawing.');
            return;
        }
        if (!/^\d{4}$/.test(form.data.payment_pin)) {
            setStepError('Enter your 4-digit payment PIN.');
            return;
        }

        form.post(route('wallet.withdraw'), {
            preserveScroll: false,
            onError: () => setStep('review'),
            onSuccess: () => {
                form.reset('amount', 'payment_pin');
                setStep('form');
                setStepError(null);
                window.setTimeout(() => {
                    document.getElementById('withdrawal-requests')?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                    });
                }, 80);
            },
        });
    };

    return (
        <ShopLayout hideFlash hideHeaderSearch>
            <Head title="Withdraw" />
            {(flash.success || flash.error) && (
                <div
                    className={`fixed inset-x-4 top-[4.75rem] z-[60] mx-auto max-w-lg rounded-xl border px-4 py-3 text-sm font-medium shadow-lg ${
                        flash.success
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                            : 'border-red-200 bg-red-50 text-red-800'
                    }`}
                    role="status"
                >
                    {flash.success ?? flash.error}
                </div>
            )}

            <div className="mx-auto max-w-lg px-4 py-4 sm:py-8">
                <div className="mb-4 flex items-center gap-1">
                    <Link
                        href={route('wallet.index')}
                        className="-ml-2 inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-900 hover:bg-gray-100"
                        aria-label="Back to wallet"
                    >
                        <ChevronLeft className="h-6 w-6" />
                    </Link>
                    <h1 className="text-xl font-bold text-gray-900">Withdraw</h1>
                </div>

                <div className="rounded-[18px] bg-gradient-to-br from-orange-600 to-orange-400 p-[18px] text-white shadow-[0_8px_18px_rgba(249,115,22,0.28)]">
                    <p className="font-semibold text-white/70">Available to withdraw</p>
                    <p className="mt-1.5 text-[30px] font-black leading-none tracking-tight">{formatPrice(availableBalance)}</p>
                    {!tooSmall && (
                        <button
                            type="button"
                            onClick={() => form.setData('amount', String(maxWithdraw))}
                            className="mt-2.5 rounded-[10px] bg-white/20 px-3 py-2 text-[13px] font-extrabold text-white"
                        >
                            Withdraw all
                        </button>
                    )}
                </div>

                {hasPendingWithdrawal && (
                    <div className="mt-4 rounded-[14px] border border-amber-200 bg-amber-50 p-3.5 text-amber-900">
                        <p className="font-extrabold">Withdrawal in processing</p>
                        <p className="mt-1 text-[12.5px] leading-snug">
                            Your earlier request is still being paid out (usually within 15 minutes). You can submit another withdrawal with your remaining balance.
                        </p>
                    </div>
                )}

                {tooSmall ? (
                    <div className="mt-4 rounded-[14px] border border-amber-200 bg-amber-50 p-3.5 text-amber-900">
                        <p className="font-extrabold">Minimum withdrawal is GH₵10.00</p>
                        <p className="mt-1 text-[12.5px] leading-snug">
                            Your available balance is {formatPrice(availableBalance)}. Add funds first, then come back to cash out.
                        </p>
                    </div>
                ) : (
                    <form onSubmit={submit} className="mt-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        {stepError && (
                            <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                                {stepError}
                            </div>
                        )}

                        {step === 'form' && (
                            <div className="space-y-5">
                                <div>
                                    <p className="text-[15px] font-extrabold text-gray-900">1. How should we pay you?</p>
                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                        {(
                                            [
                                                { id: 'momo' as const, label: 'Mobile Money' },
                                                { id: 'bank' as const, label: 'Bank' },
                                            ] as const
                                        ).map((option) => (
                                            <button
                                                key={option.id}
                                                type="button"
                                                onClick={() => setPayoutType(option.id)}
                                                className={cn(
                                                    'rounded-xl border px-3 py-3 text-[13px] font-extrabold transition',
                                                    form.data.payout_type === option.id
                                                        ? 'border-2 border-orange-500 bg-orange-50 text-orange-600'
                                                        : 'border-[1.4px] border-gray-200 bg-white text-gray-500 hover:border-gray-300',
                                                )}
                                            >
                                                {option.label}
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={form.errors.payout_type} />
                                </div>

                                {form.data.payout_type === 'momo' ? (
                                    <MomoNetworkPicker
                                        variant="list"
                                        label="Choose your network"
                                        hint="MTN MoMo is the most common. Pick the network of the number below."
                                        value={form.data.network}
                                        onChange={(network) => form.setData('network', network)}
                                    />
                                ) : (
                                    <div>
                                        <p className="text-[15px] font-extrabold text-gray-900">Choose your bank</p>
                                        <p className="mt-1 text-[13px] leading-snug text-gray-500">
                                            Tap to open the bank list, then pick with the circle on the left.
                                        </p>
                                        <div className="mt-3">
                                            <GhanaBankPicker
                                                hideHeading
                                                value={form.data.network}
                                                onChange={(network) => form.setData('network', network)}
                                            />
                                        </div>
                                        <InputError message={form.errors.network} />
                                    </div>
                                )}

                                <div className="space-y-3">
                                    <p className="text-[15px] font-extrabold text-gray-900">2. Where should the money go?</p>
                                    <div>
                                        <label className="mb-1 block text-sm text-gray-500">
                                            {form.data.payout_type === 'bank' ? 'Account number' : 'MoMo number'}
                                        </label>
                                        <Input
                                            value={form.data.momo_number}
                                            onChange={(e) => form.setData('momo_number', e.target.value)}
                                            required
                                            placeholder={form.data.payout_type === 'bank' ? 'Bank account number' : '0XX XXX XXXX'}
                                            inputMode={form.data.payout_type === 'bank' ? 'numeric' : 'tel'}
                                        />
                                        <InputError message={form.errors.momo_number} />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm text-gray-500">Account name</label>
                                        <Input
                                            value={form.data.account_name}
                                            onChange={(e) => form.setData('account_name', e.target.value)}
                                            required
                                            placeholder={
                                                form.data.payout_type === 'bank'
                                                    ? 'Name registered on the bank account'
                                                    : 'Name registered on the MoMo number'
                                            }
                                        />
                                        <InputError message={form.errors.account_name} />
                                    </div>
                                </div>

                                <div>
                                    <p className="text-[15px] font-extrabold text-gray-900">3. How much?</p>
                                    <WithdrawalBalanceAlert
                                        amount={amount}
                                        fee={activeFee}
                                        available={availableBalance}
                                        className="mt-3"
                                    />
                                    <label className="mt-3 mb-1 block text-sm text-gray-500">Amount (GH₵)</label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="10"
                                        max={maxWithdraw > 0 ? maxWithdraw : undefined}
                                        value={form.data.amount}
                                        onChange={(e) => {
                                            setStepError(null);
                                            form.setData('amount', e.target.value);
                                        }}
                                        required
                                        className="text-lg"
                                    />
                                    <InputError message={form.errors.amount} />
                                    <p className="mt-2 text-xs text-gray-500">
                                        Minimum GH₵10 · Available {formatPrice(availableBalance)}
                                    </p>
                                    <div className="mt-3">
                                        <WithdrawalFeeNotice
                                            payoutType={form.data.payout_type}
                                            fee={activeFee}
                                            amount={amount}
                                            settings={withdrawalFee}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}

                        {step === 'review' && (
                            <div className="space-y-4">
                                <div className="rounded-xl border-2 border-orange-200 bg-orange-50/60 p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-orange-600">
                                        Review {form.data.payout_type === 'bank' ? 'bank' : 'MoMo'} payout
                                    </p>
                                    <p className="mt-2 text-sm text-gray-600">
                                        {payoutNetworkLabel(form.data.network)} · {form.data.momo_number}
                                    </p>
                                    <p className="text-sm text-gray-500">{form.data.account_name}</p>
                                    <p className="mt-3 text-2xl font-black text-gray-900">{formatPrice(amount)}</p>
                                    {activeFee > 0 && (
                                        <div className="mt-2 space-y-0.5 text-xs text-gray-600">
                                            <p>Withdrawal fee: {formatPrice(activeFee)}</p>
                                            <p className="font-semibold text-gray-800">
                                                Total deducted: {formatPrice(amount + activeFee)}
                                            </p>
                                        </div>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500">Usually processed within 15 minutes and sometimes instant.</p>
                                </div>
                                {!hasPaymentPin ? (
                                    <p className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                        Set a 4-digit payment PIN before withdrawing.{' '}
                                        <a href={route('payment-pin.edit')} className="font-semibold underline">
                                            Open Payment PIN settings
                                        </a>
                                        .
                                    </p>
                                ) : (
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Payment PIN</label>
                                        <input
                                            type="password"
                                            inputMode="numeric"
                                            maxLength={4}
                                            value={form.data.payment_pin}
                                            onChange={(e) =>
                                                form.setData('payment_pin', e.target.value.replace(/\D/g, '').slice(0, 4))
                                            }
                                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                                            placeholder="4-digit PIN"
                                            autoComplete="off"
                                        />
                                        <InputError message={form.errors.payment_pin} />
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="sticky bottom-[calc(4.25rem+env(safe-area-inset-bottom,0px))] z-20 mt-5 flex gap-2 bg-white py-3 sm:static sm:bottom-auto sm:py-0">
                            {step === 'review' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="flex-1"
                                    onClick={() => {
                                        setStepError(null);
                                        setStep('form');
                                    }}
                                >
                                    Back
                                </Button>
                            )}
                            <Button
                                type="submit"
                                disabled={form.processing || (step === 'form' && balanceOverLimit) || (step === 'review' && !hasPaymentPin)}
                                className="h-[52px] flex-1 bg-orange-500 text-[15px] font-extrabold hover:bg-orange-600"
                            >
                                {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                {step === 'form' ? 'Review withdrawal' : 'Request withdrawal'}
                            </Button>
                        </div>
                    </form>
                )}

                <div id="withdrawal-requests" className="mt-5 scroll-mt-28">
                    <h2 className="text-sm font-extrabold text-gray-900">Withdrawal requests</h2>
                    {withdrawals.data.length === 0 ? (
                        <p className="mt-2 text-sm text-gray-500">No withdrawal requests yet.</p>
                    ) : (
                        <div className="mt-3 space-y-2">
                            {withdrawals.data.map((w) => (
                                <div key={w.id} className="flex items-start justify-between gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm">
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
            </div>
        </ShopLayout>
    );
}
