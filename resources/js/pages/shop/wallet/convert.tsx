import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice, Wallet } from '@/types/marketplace';

type SampleQuote = {
    direction: string;
    amount_ghs: number;
    amount_rmb: number;
    rate: number;
    rate_label: string;
    result_label: string;
} | null;

type Kyc = { can_store_funds?: boolean; status_label?: string };

interface Props {
    wallet: Wallet;
    sampleQuotes: {
        ghs_to_rmb: SampleQuote;
        rmb_to_ghs: SampleQuote;
    };
    hasPaymentPin?: boolean;
    kyc?: Kyc;
}

export default function WalletConvert({ wallet, sampleQuotes, hasPaymentPin = false, kyc }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [direction, setDirection] = useState<'ghs_to_rmb' | 'rmb_to_ghs'>('ghs_to_rmb');
    const [liveQuote, setLiveQuote] = useState<SampleQuote>(null);
    const [quoteError, setQuoteError] = useState<string | null>(null);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const form = useForm({
        direction: 'ghs_to_rmb' as 'ghs_to_rmb' | 'rmb_to_ghs',
        amount: '',
        payment_pin: '',
    });

    useEffect(() => {
        form.setData('direction', direction);
        setLiveQuote(null);
        setQuoteError(null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [direction]);

    const amount = Number(form.data.amount);
    const preview = useMemo(() => {
        if (!Number.isFinite(amount) || amount < 1) return null;
        const sample = direction === 'ghs_to_rmb' ? sampleQuotes.ghs_to_rmb : sampleQuotes.rmb_to_ghs;
        if (!sample || sample.rate <= 0) return null;
        if (direction === 'ghs_to_rmb') {
            const rmb = amount / sample.rate;
            return {
                result: `¥${rmb.toFixed(2)}`,
                rateLabel: sample.rate_label,
                ghsAfter: wallet.available_balance - amount,
                rmbAfter: Number(wallet.rmb_balance ?? 0) + rmb,
            };
        }
        const ghs = amount * sample.rate;
        return {
            result: formatPrice(ghs),
            rateLabel: sample.rate_label,
            ghsAfter: wallet.available_balance + ghs,
            rmbAfter: Number(wallet.rmb_balance ?? 0) - amount,
        };
    }, [amount, direction, sampleQuotes, wallet]);

    useEffect(() => {
        if (!Number.isFinite(amount) || amount < 1) {
            setLiveQuote(null);
            return;
        }
        const timer = window.setTimeout(async () => {
            try {
                const res = await fetch(route('wallet.convert.quote'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    },
                    body: JSON.stringify({ direction, amount }),
                });
                const json = await res.json();
                if (!res.ok) {
                    setQuoteError(json.message ?? 'Could not quote');
                    setLiveQuote(null);
                    return;
                }
                setQuoteError(null);
                setLiveQuote(json.data);
            } catch {
                setQuoteError('Could not quote conversion');
            }
        }, 250);
        return () => window.clearTimeout(timer);
    }, [amount, direction]);

    const openConfirm: FormEventHandler = (e) => {
        e.preventDefault();
        if (!kyc?.can_store_funds) {
            form.setError('amount', 'Approve your Ghana Card (KYC) before converting.');
            return;
        }
        if (!hasPaymentPin) {
            form.setError('payment_pin', 'Set a 4-digit payment PIN in Profile first.');
            return;
        }
        if (amount < 1) return;
        setConfirmOpen(true);
    };

    const submitConfirm: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('wallet.convert.store'), {
            onFinish: () => setConfirmOpen(false),
        });
    };

    const sourceLabel = direction === 'ghs_to_rmb' ? 'GHS amount' : 'RMB amount';
    const balanceHint =
        direction === 'ghs_to_rmb'
            ? `Available: ${formatPrice(wallet.available_balance)}`
            : `Available: ¥${Number(wallet.rmb_balance ?? 0).toFixed(2)}`;

    return (
        <ShopLayout hideFlash>
            <Head title="Convert" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <Link href={route('wallet.china-rmb.index')} className="text-sm font-semibold text-orange-600">
                    ← China / RMB
                </Link>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Convert</h1>
                <p className="mt-1 text-sm text-gray-500">Instant GHS ↔ RMB. PIN + KYC required.</p>

                {(flash.success || flash.error || form.errors.amount || form.errors.direction || form.errors.payment_pin || form.errors.kyc) && (
                    <p
                        className={`mt-3 rounded-xl px-4 py-3 text-sm ${
                            flash.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ??
                            flash.error ??
                            form.errors.amount ??
                            form.errors.direction ??
                            form.errors.payment_pin ??
                            form.errors.kyc}
                    </p>
                )}

                {!kyc?.can_store_funds && (
                    <p className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        KYC required before convert.{' '}
                        <Link href={route('kyc.index')} className="font-semibold underline">
                            Verify Ghana Card
                        </Link>
                    </p>
                )}

                <div className="mt-5 grid grid-cols-2 gap-3 rounded-2xl border border-gray-200 bg-white p-4">
                    <div>
                        <p className="text-xs font-semibold text-gray-500">GHS</p>
                        <p className="text-lg font-black text-gray-900">{formatPrice(wallet.available_balance)}</p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-gray-500">RMB</p>
                        <p className="text-lg font-black text-gray-900">¥{Number(wallet.rmb_balance ?? 0).toFixed(2)}</p>
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-2.5">
                    <button
                        type="button"
                        onClick={() => setDirection('ghs_to_rmb')}
                        className={`rounded-xl border-2 px-3 py-3 text-sm font-extrabold ${
                            direction === 'ghs_to_rmb' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200'
                        }`}
                    >
                        GHS → RMB
                    </button>
                    <button
                        type="button"
                        onClick={() => setDirection('rmb_to_ghs')}
                        className={`rounded-xl border-2 px-3 py-3 text-sm font-extrabold ${
                            direction === 'rmb_to_ghs' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200'
                        }`}
                    >
                        RMB → GHS
                    </button>
                </div>

                <form onSubmit={openConfirm} className="mt-5 space-y-4 rounded-2xl border border-gray-200 bg-white p-4">
                    <div className="space-y-1.5">
                        <Label>{sourceLabel}</Label>
                        <Input
                            inputMode="decimal"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            placeholder="Min 1.00"
                        />
                        <p className="text-xs text-gray-500">{balanceHint}</p>
                        <InputError message={form.errors.amount} />
                    </div>

                    {(liveQuote || preview) && (
                        <dl className="space-y-1.5 rounded-xl bg-gray-50 px-3 py-3 text-sm">
                            <div className="flex justify-between text-gray-600">
                                <dt>Rate</dt>
                                <dd>{liveQuote?.rate_label ?? preview?.rateLabel}</dd>
                            </div>
                            <div className="flex justify-between font-bold text-gray-900">
                                <dt>You receive</dt>
                                <dd>{liveQuote?.result_label ?? preview?.result}</dd>
                            </div>
                        </dl>
                    )}
                    {quoteError && <p className="text-sm text-red-700">{quoteError}</p>}

                    <Button disabled={form.processing || amount < 1} className="w-full bg-orange-500 hover:bg-orange-600">
                        Review & convert
                    </Button>
                </form>
            </div>

            {confirmOpen && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
                    <form
                        onSubmit={submitConfirm}
                        className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
                    >
                        <h2 className="text-lg font-bold">Confirm exchange</h2>
                        <p className="mt-2 text-sm text-gray-600">
                            {direction === 'ghs_to_rmb' ? 'GHS → RMB' : 'RMB → GHS'} ·{' '}
                            {liveQuote?.result_label ?? preview?.result}
                        </p>
                        {preview && (
                            <dl className="mt-3 space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <dt>GHS after</dt>
                                    <dd>{formatPrice(preview.ghsAfter)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>RMB after</dt>
                                    <dd>¥{preview.rmbAfter.toFixed(2)}</dd>
                                </div>
                            </dl>
                        )}
                        <div className="mt-4 space-y-1.5">
                            <Label>Payment PIN</Label>
                            <Input
                                type="password"
                                inputMode="numeric"
                                maxLength={4}
                                value={form.data.payment_pin}
                                onChange={(e) => form.setData('payment_pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                placeholder="••••"
                                autoFocus
                            />
                            <InputError message={form.errors.payment_pin} />
                        </div>
                        <div className="mt-4 flex gap-2">
                            <Button type="button" variant="outline" className="flex-1" onClick={() => setConfirmOpen(false)}>
                                Cancel
                            </Button>
                            <Button disabled={form.processing} className="flex-1 bg-orange-500 hover:bg-orange-600">
                                {form.processing ? 'Converting…' : 'Confirm'}
                            </Button>
                        </div>
                    </form>
                </div>
            )}
        </ShopLayout>
    );
}
