import { Link, useForm } from '@inertiajs/react';
import { ArrowDownToLine, History, LoaderCircle, Plus, RefreshCw, Smartphone, Upload, Wallet, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { formatPrice } from '@/types/marketplace';

interface WalletBalanceCardProps {
    balance: number;
    pendingBalance?: number;
    withdrawHref: string;
    historyHref: string;
    className?: string;
    onRefresh?: () => void;
    refreshing?: boolean;
    countdownSec?: number;
    paystackConfigured?: boolean;
    manualTopUpEnabled?: boolean;
}

export default function WalletBalanceCard({
    balance,
    pendingBalance,
    withdrawHref,
    historyHref,
    className,
    onRefresh,
    refreshing = false,
    countdownSec,
    paystackConfigured = false,
    manualTopUpEnabled = false,
}: WalletBalanceCardProps) {
    const canTopUp = paystackConfigured || manualTopUpEnabled;
    const [topUpOpen, setTopUpOpen] = useState(false);
    const [step, setStep] = useState<'choose' | 'paystack'>('choose');

    const paystackForm = useForm({
        amount: '',
        method: 'momo' as 'momo' | 'card',
    });

    const openTopUp = () => {
        setStep('choose');
        paystackForm.reset();
        paystackForm.clearErrors();
        setTopUpOpen(true);
    };

    const closeTopUp = () => {
        setTopUpOpen(false);
        setStep('choose');
        paystackForm.reset();
    };

    const submitPaystack: FormEventHandler = (e) => {
        e.preventDefault();
        paystackForm.post(route('seller.wallet.add-funds'), {
            onSuccess: () => closeTopUp(),
        });
    };

    return (
        <>
            <div
                className={cn(
                    'rounded-[1.75rem] border border-sky-100 bg-gradient-to-br from-sky-50/90 via-white to-slate-50 p-5 shadow-sm sm:p-6',
                    className,
                )}
            >
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                            <Wallet className="h-5 w-5" strokeWidth={1.75} />
                        </div>
                        <div>
                            <p className="text-base font-medium text-slate-500">Wallet Balance</p>
                            {countdownSec != null && (
                                <p className="text-xs text-slate-400">Auto refresh in {countdownSec}s</p>
                            )}
                        </div>
                    </div>
                    {onRefresh && (
                        <button
                            type="button"
                            onClick={onRefresh}
                            disabled={refreshing}
                            className="inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-white px-3 py-1.5 text-xs font-semibold text-sky-600 shadow-sm transition hover:bg-sky-50 disabled:opacity-60"
                        >
                            <RefreshCw className={cn('h-3.5 w-3.5', refreshing && 'animate-spin')} />
                            Refresh
                        </button>
                    )}
                </div>

                <p className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    {formatPrice(balance)}
                </p>
                {pendingBalance != null && pendingBalance > 0 && (
                    <p className="mt-1 text-sm text-slate-500">
                        {formatPrice(pendingBalance)} clearing
                    </p>
                )}

                <div className="mt-6 flex flex-wrap items-center gap-3 sm:gap-4">
                    {canTopUp && (
                        <button
                            type="button"
                            onClick={openTopUp}
                            className="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 py-1.5 pl-1.5 pr-3.5 text-xs font-semibold text-orange-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-100 sm:text-sm"
                        >
                            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-orange-500 text-white sm:h-8 sm:w-8">
                                <Plus className="h-3.5 w-3.5 sm:h-4 sm:w-4" strokeWidth={2.25} />
                            </span>
                            Top Up
                        </button>
                    )}

                    <Link
                        href={withdrawHref}
                        className="inline-flex items-center gap-2.5 rounded-full border border-sky-200 bg-white py-2 pl-2 pr-5 text-sm font-semibold text-sky-600 shadow-sm transition hover:border-sky-300 hover:bg-sky-50"
                    >
                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-sky-500 text-white">
                            <ArrowDownToLine className="h-4 w-4" strokeWidth={2.25} />
                        </span>
                        Withdraw
                    </Link>

                    <Link
                        href={historyHref}
                        className="inline-flex items-center gap-2 text-sm font-medium text-slate-700 transition hover:text-sky-600"
                    >
                        <History className="h-4 w-4" strokeWidth={1.75} />
                        History
                    </Link>
                </div>
            </div>

            {topUpOpen && (
                <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-3 pt-14 sm:items-start sm:pt-20">
                    <div className="w-full max-w-sm rounded-xl bg-white p-3.5 shadow-xl sm:p-4">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <h3 className="text-sm font-bold text-gray-900">
                                    {step === 'choose' ? 'Top up wallet' : 'Auto Paystack'}
                                </h3>
                                {step === 'choose' && (
                                    <p className="mt-0.5 text-[11px] leading-snug text-gray-500">
                                        For refunds after Pay-to-seller cancel.
                                    </p>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={closeTopUp}
                                className="shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                aria-label="Close"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        {step === 'choose' ? (
                            <div className="mt-3 space-y-2">
                                {paystackConfigured && (
                                    <button
                                        type="button"
                                        onClick={() => setStep('paystack')}
                                        className="flex w-full items-center gap-2.5 rounded-lg border border-orange-200 bg-orange-50/60 px-3 py-2.5 text-left transition hover:border-orange-300 hover:bg-orange-50"
                                    >
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-500 text-white">
                                            <Smartphone className="h-4 w-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold text-gray-900">Auto Paystack</span>
                                            <span className="block text-[11px] text-gray-500">Instant MoMo or card</span>
                                        </span>
                                    </button>
                                )}

                                {manualTopUpEnabled && (
                                    <Link
                                        href={route('seller.wallet.manual-top-up')}
                                        onClick={closeTopUp}
                                        className="flex w-full items-center gap-2.5 rounded-lg border border-sky-100 bg-white px-3 py-2.5 text-left transition hover:border-sky-200 hover:bg-sky-50"
                                    >
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-500 text-white">
                                            <Upload className="h-4 w-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold text-gray-900">Manual</span>
                                            <span className="block text-[11px] text-gray-500">MoMo / bank + upload proof</span>
                                        </span>
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <form onSubmit={submitPaystack} className="mt-3 space-y-3">
                                <div>
                                    <Label htmlFor="topup-amount">Amount (GH₵)</Label>
                                    <Input
                                        id="topup-amount"
                                        type="number"
                                        min="5"
                                        step="0.01"
                                        value={paystackForm.data.amount}
                                        onChange={(e) => paystackForm.setData('amount', e.target.value)}
                                        className="mt-1"
                                        placeholder="e.g. 100"
                                        autoFocus
                                    />
                                    <InputError message={paystackForm.errors.amount} />
                                </div>
                                <div>
                                    <Label>Pay with</Label>
                                    <div className="mt-1.5 flex gap-2">
                                        {(['momo', 'card'] as const).map((method) => (
                                            <button
                                                key={method}
                                                type="button"
                                                onClick={() => paystackForm.setData('method', method)}
                                                className={cn(
                                                    'flex-1 rounded-lg px-3 py-2 text-sm font-medium ring-1',
                                                    paystackForm.data.method === method
                                                        ? 'bg-orange-500 text-white ring-orange-500'
                                                        : 'bg-white text-gray-700 ring-gray-200',
                                                )}
                                            >
                                                {method === 'momo' ? 'Mobile Money' : 'Card'}
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={paystackForm.errors.method} />
                                </div>
                                <div className="flex gap-2 pt-0.5">
                                    <Button type="button" variant="outline" size="sm" className="flex-1" onClick={() => setStep('choose')}>
                                        Back
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={paystackForm.processing}
                                        className="flex-1 bg-orange-500 hover:bg-orange-600"
                                    >
                                        {paystackForm.processing && <LoaderCircle className="mr-2 h-3.5 w-3.5 animate-spin" />}
                                        Continue
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
