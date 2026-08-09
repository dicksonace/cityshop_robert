import { Link, useForm } from '@inertiajs/react';
import { History, LoaderCircle, RefreshCw, Smartphone, Upload, X } from 'lucide-react';
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
    const canRecharge = paystackConfigured || manualTopUpEnabled;
    const [rechargeOpen, setRechargeOpen] = useState(false);
    const [step, setStep] = useState<'choose' | 'paystack'>('choose');

    const paystackForm = useForm({
        amount: '',
        method: 'momo' as 'momo' | 'card',
    });

    const openRecharge = () => {
        setStep('choose');
        paystackForm.reset();
        paystackForm.clearErrors();
        setRechargeOpen(true);
    };

    const closeRecharge = () => {
        setRechargeOpen(false);
        setStep('choose');
        paystackForm.reset();
    };

    const submitPaystack: FormEventHandler = (e) => {
        e.preventDefault();
        paystackForm.post(route('seller.wallet.add-funds'), {
            onSuccess: () => closeRecharge(),
        });
    };

    return (
        <>
            <div
                className={cn(
                    'relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-orange-500 via-orange-600 to-orange-700 p-5 text-white shadow-lg sm:p-6',
                    className,
                )}
            >
                <div className="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full bg-white/10" />
                <div className="pointer-events-none absolute -bottom-12 -left-8 h-40 w-40 rounded-full bg-black/10" />

                <div className="relative flex items-start justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium text-orange-100">Available balance</p>
                        {countdownSec != null && (
                            <p className="mt-0.5 text-xs text-orange-100/80">Auto refresh in {countdownSec}s</p>
                        )}
                    </div>
                    {onRefresh && (
                        <button
                            type="button"
                            onClick={onRefresh}
                            disabled={refreshing}
                            className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur transition hover:bg-white/25 disabled:opacity-60"
                        >
                            <RefreshCw className={cn('h-3.5 w-3.5', refreshing && 'animate-spin')} />
                            Refresh
                        </button>
                    )}
                </div>

                <p className="relative mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    {formatPrice(balance)}
                </p>
                {pendingBalance != null && pendingBalance > 0 && (
                    <p className="relative mt-1.5 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                        {formatPrice(pendingBalance)} clearing
                    </p>
                )}

                <div className="relative mt-5 flex h-12 overflow-hidden rounded-full bg-white shadow-md">
                    <Link
                        href={withdrawHref}
                        className="flex flex-1 items-center justify-center text-sm font-extrabold text-slate-900 transition hover:bg-slate-50"
                    >
                        Withdrawal
                    </Link>
                    <button
                        type="button"
                        onClick={canRecharge ? openRecharge : undefined}
                        disabled={!canRecharge}
                        className={cn(
                            'flex flex-1 items-center justify-center rounded-full text-sm font-extrabold text-white transition',
                            canRecharge
                                ? 'bg-orange-500 hover:bg-orange-600'
                                : 'cursor-not-allowed bg-slate-400',
                        )}
                    >
                        {canRecharge ? 'Recharge' : 'Unavailable'}
                    </button>
                </div>

                <div className="relative mt-3 flex items-center justify-between gap-3">
                    <Link
                        href={historyHref}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-orange-50 transition hover:text-white"
                    >
                        <History className="h-4 w-4" strokeWidth={1.75} />
                        History
                    </Link>
                    {!canRecharge && (
                        <p className="text-[11px] text-orange-100/90">Recharge unavailable right now</p>
                    )}
                </div>
            </div>

            {rechargeOpen && (
                <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-3 pt-14 sm:items-start sm:pt-20">
                    <div className="w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl sm:p-5">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <h3 className="text-base font-bold text-gray-900">
                                    {step === 'choose' ? 'Recharge' : 'Paystack recharge'}
                                </h3>
                                {step === 'choose' && (
                                    <p className="mt-0.5 text-xs leading-snug text-gray-500">
                                        Top up for refunds after Pay-to-seller cancel.
                                    </p>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={closeRecharge}
                                className="shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                aria-label="Close"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        {step === 'choose' ? (
                            <div className="mt-4 space-y-2.5">
                                {paystackConfigured && (
                                    <button
                                        type="button"
                                        onClick={() => setStep('paystack')}
                                        className="flex w-full items-center gap-3 rounded-xl border border-orange-200 bg-orange-50/60 px-3.5 py-3 text-left transition hover:border-orange-300 hover:bg-orange-50"
                                    >
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-500 text-white">
                                            <Smartphone className="h-5 w-5" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold text-gray-900">Auto Paystack</span>
                                            <span className="block text-xs text-gray-500">Instant MoMo or card</span>
                                        </span>
                                    </button>
                                )}

                                {manualTopUpEnabled && (
                                    <Link
                                        href={route('seller.wallet.manual-top-up')}
                                        onClick={closeRecharge}
                                        className="flex w-full items-center gap-3 rounded-xl border border-sky-100 bg-white px-3.5 py-3 text-left transition hover:border-sky-200 hover:bg-sky-50"
                                    >
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500 text-white">
                                            <Upload className="h-5 w-5" />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-sm font-semibold text-gray-900">Manual</span>
                                            <span className="block text-xs text-gray-500">MoMo / bank + upload proof</span>
                                        </span>
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <form onSubmit={submitPaystack} className="mt-4 space-y-3">
                                <div>
                                    <Label htmlFor="recharge-amount">Amount (GH₵)</Label>
                                    <Input
                                        id="recharge-amount"
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
                                        Recharge
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
