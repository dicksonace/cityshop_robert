import { router, useForm } from '@inertiajs/react';
import { LoaderCircle, Smartphone, Upload, X } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import DirectPaymentDetails from '@/components/shop/direct-payment-details';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type FundingAccount } from '@/components/wallet/manual-top-up-form';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import { csrfHeaders } from '@/lib/csrf';
import { normalizeMomoNetworkId } from '@/lib/momo-networks';
import { paystackRechargeQuote, type PaystackFeeSettings } from '@/lib/paystack-fees';
import { cn } from '@/lib/utils';

interface RechargeModalProps {
    open: boolean;
    onClose: () => void;
    paystackConfigured: boolean;
    flutterwaveConfigured?: boolean;
    manualTopUpEnabled: boolean;
    manualFundingAccounts?: FundingAccount[];
    manualHref: string;
    paystackRoute: string;
    flutterwaveRoute?: string;
    chooseHint?: string;
    amountInputId?: string;
    paystackFee?: PaystackFeeSettings | null;
}

type RechargeStep = 'choose' | 'paystack' | 'flutterwave' | 'manual';

/**
 * Recharge flow: Auto Paystack / Flutterwave vs Manual. Manual shows the compact MoMo picker
 * and pay-to details inline (same as manual deposit), then continues to proof.
 */
export default function RechargeModal({
    open,
    onClose,
    paystackConfigured,
    flutterwaveConfigured = false,
    manualTopUpEnabled,
    manualFundingAccounts = [],
    manualHref,
    paystackRoute,
    flutterwaveRoute,
    chooseHint = 'Choose how you want to add funds.',
    amountInputId = 'recharge-amount',
    paystackFee,
}: RechargeModalProps) {
    const [step, setStep] = useState<RechargeStep>('choose');
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState('');
    const [selectedNetwork, setSelectedNetwork] = useState<string | null>(null);
    const form = useForm({
        amount: '',
        method: 'momo' as 'momo' | 'card',
    });

    const onlineConfigured = paystackConfigured || flutterwaveConfigured;
    const multiOnline = paystackConfigured && flutterwaveConfigured;

    const momoAccountsByNetwork = useMemo(() => {
        const map: Record<string, FundingAccount> = {};
        for (const account of manualFundingAccounts) {
            if (account.type !== 'momo') continue;
            const id = normalizeMomoNetworkId(account.network);
            if (id && !map[id]) map[id] = account;
        }
        return map;
    }, [manualFundingAccounts]);

    const selectedAccount = selectedNetwork ? momoAccountsByNetwork[selectedNetwork] ?? null : null;

    useEffect(() => {
        if (!open) return;

        form.reset();
        form.clearErrors();
        setSubmitting(false);
        setSubmitError('');

        if (onlineConfigured && !manualTopUpEnabled) {
            if (multiOnline) {
                setStep('choose');
            } else if (paystackConfigured) {
                setStep('paystack');
            } else {
                setStep('flutterwave');
            }
            return;
        }
        if (!onlineConfigured && manualTopUpEnabled) {
            setStep('manual');
            return;
        }
        setStep('choose');
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only re-init when opened
    }, [open, paystackConfigured, flutterwaveConfigured, manualTopUpEnabled]);

    useEffect(() => {
        if (!open || step !== 'manual') return;
        if (selectedNetwork && momoAccountsByNetwork[selectedNetwork]) return;
        const defaultId = ['mtn', 'telecel', 'airteltigo'].find((id) => momoAccountsByNetwork[id]);
        if (defaultId) setSelectedNetwork(defaultId);
    }, [open, step, momoAccountsByNetwork, selectedNetwork]);

    if (!open) return null;

    const close = () => {
        form.reset();
        form.clearErrors();
        setSubmitting(false);
        setSubmitError('');
        setSelectedNetwork(null);
        setStep('choose');
        onClose();
    };

    const submitOnline = async (routeName: string) => {
        setSubmitError('');
        const amount = Number(form.data.amount);
        if (!Number.isFinite(amount) || amount < 5) {
            form.setError('amount', 'Enter at least GH₵5.');
            return;
        }

        setSubmitting(true);
        try {
            const res = await fetch(routeName, {
                method: 'POST',
                headers: {
                    ...csrfHeaders(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    amount,
                    method: form.data.method,
                }),
            });
            const data = (await res.json().catch(() => ({}))) as {
                message?: string;
                authorization_url?: string;
            };
            if (!res.ok) {
                throw new Error(data.message || 'Could not start payment. Please try again.');
            }
            if (!data.authorization_url) {
                throw new Error('Could not start payment. Please try again.');
            }
            window.location.href = data.authorization_url;
        } catch (err) {
            setSubmitting(false);
            setSubmitError(err instanceof Error ? err.message : 'Could not start payment. Please try again.');
        }
    };

    const submitPaystack: FormEventHandler = async (e) => {
        e.preventDefault();
        await submitOnline(paystackRoute);
    };

    const submitFlutterwave: FormEventHandler = async (e) => {
        e.preventDefault();
        if (!flutterwaveRoute) {
            setSubmitError('Flutterwave is not available.');
            return;
        }
        await submitOnline(flutterwaveRoute);
    };

    const showChooser =
        step === 'choose' &&
        ((onlineConfigured && manualTopUpEnabled) || multiOnline || (paystackConfigured && flutterwaveConfigured && manualTopUpEnabled));
    const showManual = manualTopUpEnabled && step === 'manual';
    const showPaystack = step === 'paystack';
    const showFlutterwave = step === 'flutterwave';
    const quote = paystackRechargeQuote(Number(form.data.amount) || 0, paystackFee);

    const title = showChooser
        ? 'Recharge'
        : showManual
          ? 'Manual deposit'
          : showFlutterwave
            ? 'Flutterwave recharge'
            : 'Paystack recharge';

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-3 pt-14 sm:items-start sm:pt-20">
            <div className="max-h-[calc(100vh-4rem)] w-full max-w-sm overflow-y-auto rounded-2xl bg-white p-4 shadow-xl sm:p-5">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h3 className="text-base font-bold text-gray-900">{title}</h3>
                        {showChooser && (
                            <p className="mt-0.5 text-xs leading-snug text-gray-500">{chooseHint}</p>
                        )}
                        {showManual && (
                            <p className="mt-0.5 text-xs leading-snug text-gray-500">
                                Copy the CityShop number, then submit proof.
                            </p>
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
                        {paystackConfigured ? (
                            <button
                                type="button"
                                onClick={() => setStep('paystack')}
                                className="flex w-full items-center gap-3 rounded-xl border border-orange-200 bg-orange-50/60 px-3.5 py-3 text-left transition hover:border-orange-300 hover:bg-orange-50"
                            >
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-500 text-white">
                                    <Smartphone className="h-5 w-5" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold text-gray-900">Paystack</span>
                                    <span className="block text-xs text-gray-500">Instant MoMo or card</span>
                                </span>
                            </button>
                        ) : null}

                        {flutterwaveConfigured && flutterwaveRoute ? (
                            <button
                                type="button"
                                onClick={() => setStep('flutterwave')}
                                className="flex w-full items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50/60 px-3.5 py-3 text-left transition hover:border-indigo-300 hover:bg-indigo-50"
                            >
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                                    <Smartphone className="h-5 w-5" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold text-gray-900">Flutterwave</span>
                                    <span className="block text-xs text-gray-500">Instant MoMo or card</span>
                                </span>
                            </button>
                        ) : null}

                        {manualTopUpEnabled ? (
                            <button
                                type="button"
                                onClick={() => setStep('manual')}
                                className="flex w-full items-center gap-3 rounded-xl border border-sky-100 bg-white px-3.5 py-3 text-left transition hover:border-sky-200 hover:bg-sky-50"
                            >
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500 text-white">
                                    <Upload className="h-5 w-5" />
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold text-gray-900">Manual</span>
                                    <span className="block text-xs text-gray-500">MoMo / bank + upload proof</span>
                                </span>
                            </button>
                        ) : null}
                    </div>
                ) : showManual ? (
                    <div className="mt-4 space-y-4">
                        <MomoNetworkPicker
                            value={selectedNetwork ?? ''}
                            onChange={setSelectedNetwork}
                            enabledNetworks={Object.keys(momoAccountsByNetwork)}
                            variant="selected"
                        />

                        {selectedAccount && selectedNetwork ? (
                            <DirectPaymentDetails
                                accountNumber={selectedAccount.account_number}
                                accountName={selectedAccount.account_name}
                                network={selectedNetwork}
                            />
                        ) : null}

                        <div className="flex gap-2 pt-0.5">
                            {onlineConfigured ? (
                                <Button type="button" variant="outline" size="sm" className="flex-1" onClick={() => setStep('choose')}>
                                    Back
                                </Button>
                            ) : (
                                <Button type="button" variant="outline" size="sm" className="flex-1" onClick={close}>
                                    Cancel
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                disabled={!selectedNetwork}
                                className="flex-1 bg-green-600 hover:bg-green-700"
                                onClick={() => {
                                    close();
                                    router.visit(manualHref);
                                }}
                            >
                                Continue — submit proof
                            </Button>
                        </div>
                    </div>
                ) : (
                    <form
                        onSubmit={showFlutterwave ? submitFlutterwave : submitPaystack}
                        className="mt-4 space-y-3"
                    >
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
                        {submitError && (
                            <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                                {submitError}
                            </p>
                        )}
                        {quote.credit >= 5 && (
                            <div className="rounded-xl bg-orange-50 px-3 py-2.5 text-xs text-orange-900">
                                <div className="flex justify-between gap-3">
                                    <span>Wallet credit</span>
                                    <span className="font-semibold">GH₵{quote.credit.toFixed(2)}</span>
                                </div>
                                <div className="mt-1.5 flex justify-between gap-3 border-t border-orange-200 pt-1.5 text-sm font-bold">
                                    <span>You pay</span>
                                    <span>GH₵{quote.charge.toFixed(2)}</span>
                                </div>
                            </div>
                        )}
                        <div className="flex gap-2 pt-0.5">
                            {(manualTopUpEnabled || multiOnline) && onlineConfigured ? (
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
                                disabled={submitting}
                                className="flex-1 bg-orange-500 hover:bg-orange-600"
                            >
                                {submitting && <LoaderCircle className="mr-2 h-3.5 w-3.5 animate-spin" />}
                                Recharge
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
