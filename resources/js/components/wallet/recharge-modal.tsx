import { Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Smartphone, Upload, X } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { paystackRechargeQuote, type PaystackFeeSettings } from '@/lib/paystack-fees';
import { cn } from '@/lib/utils';

interface RechargeModalProps {
    open: boolean;
    onClose: () => void;
    paystackConfigured: boolean;
    manualTopUpEnabled: boolean;
    manualHref: string;
    paystackRoute: string;
    chooseHint?: string;
    amountInputId?: string;
    paystackFee?: PaystackFeeSettings | null;
}

/**
 * Same Recharge flow as mobile: choose Auto Paystack vs Manual,
 * then Paystack amount form. Skips the chooser when only one path exists.
 */
export default function RechargeModal({
    open,
    onClose,
    paystackConfigured,
    manualTopUpEnabled,
    manualHref,
    paystackRoute,
    chooseHint = 'Choose how you want to add funds.',
    amountInputId = 'recharge-amount',
    paystackFee,
}: RechargeModalProps) {
    const [step, setStep] = useState<'choose' | 'paystack'>('choose');
    const form = useForm({
        amount: '',
        method: 'momo' as 'momo' | 'card',
    });

    useEffect(() => {
        if (!open) return;

        form.reset();
        form.clearErrors();

        if (paystackConfigured && !manualTopUpEnabled) {
            setStep('paystack');
            return;
        }
        if (!paystackConfigured && manualTopUpEnabled) {
            onClose();
            router.visit(manualHref);
            return;
        }
        setStep('choose');
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only re-init when opened
    }, [open]);

    if (!open) return null;

    // Manual-only redirects away; don't flash an empty modal.
    if (!paystackConfigured && manualTopUpEnabled) return null;

    const close = () => {
        form.reset();
        form.clearErrors();
        setStep('choose');
        onClose();
    };

    const submitPaystack: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(paystackRoute, {
            onSuccess: () => close(),
        });
    };

    const showChooser = paystackConfigured && manualTopUpEnabled && step === 'choose';
    const quote = paystackRechargeQuote(Number(form.data.amount) || 0, paystackFee);

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-3 pt-14 sm:items-start sm:pt-20">
            <div className="w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl sm:p-5">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h3 className="text-base font-bold text-gray-900">
                            {showChooser ? 'Recharge' : 'Paystack recharge'}
                        </h3>
                        {showChooser && (
                            <p className="mt-0.5 text-xs leading-snug text-gray-500">{chooseHint}</p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={close}
                        className="shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Close"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                {showChooser ? (
                    <div className="mt-4 space-y-2.5">
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

                        <Link
                            href={manualHref}
                            onClick={close}
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
                    </div>
                ) : (
                    <form onSubmit={submitPaystack} className="mt-4 space-y-3">
                        <div>
                            <Label htmlFor={amountInputId}>Amount (GH₵)</Label>
                            <Input
                                id={amountInputId}
                                type="number"
                                min="5"
                                step="0.01"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                className="mt-1"
                                placeholder="e.g. 100"
                                autoFocus
                            />
                            <InputError message={form.errors.amount} />
                        </div>
                        <div>
                            <Label>Pay with</Label>
                            <div className="mt-1.5 flex gap-2">
                                {(['momo', 'card'] as const).map((method) => (
                                    <button
                                        key={method}
                                        type="button"
                                        onClick={() => form.setData('method', method)}
                                        className={cn(
                                            'flex-1 rounded-lg px-3 py-2 text-sm font-medium ring-1',
                                            form.data.method === method
                                                ? 'bg-orange-500 text-white ring-orange-500'
                                                : 'bg-white text-gray-700 ring-gray-200',
                                        )}
                                    >
                                        {method === 'momo' ? 'Mobile Money' : 'Card'}
                                    </button>
                                ))}
                            </div>
                            <InputError message={form.errors.method} />
                        </div>
                        {quote.credit >= 5 && (
                            <div className="rounded-xl bg-orange-50 px-3 py-2.5 text-xs text-orange-900">
                                <div className="flex justify-between gap-3">
                                    <span>Wallet credit</span>
                                    <span className="font-semibold">GH₵{quote.credit.toFixed(2)}</span>
                                </div>
                                <div className="mt-1 flex justify-between gap-3">
                                    <span>
                                        Paystack fee
                                        {quote.mode === 'percent' && quote.percent > 0 ? ` (${quote.percent}%)` : ''}
                                    </span>
                                    <span className="font-semibold">GH₵{quote.fee.toFixed(2)}</span>
                                </div>
                                <div className="mt-1.5 flex justify-between gap-3 border-t border-orange-200 pt-1.5 text-sm font-bold">
                                    <span>You pay</span>
                                    <span>GH₵{quote.charge.toFixed(2)}</span>
                                </div>
                            </div>
                        )}
                        <div className="flex gap-2 pt-0.5">
                            {paystackConfigured && manualTopUpEnabled ? (
                                <Button type="button" variant="outline" size="sm" className="flex-1" onClick={() => setStep('choose')}>
                                    Back
                                </Button>
                            ) : (
                                <Button type="button" variant="outline" size="sm" className="flex-1" onClick={close}>
                                    Cancel
                                </Button>
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                disabled={form.processing}
                                className="flex-1 bg-orange-500 hover:bg-orange-600"
                            >
                                {form.processing && <LoaderCircle className="mr-2 h-3.5 w-3.5 animate-spin" />}
                                Recharge
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
