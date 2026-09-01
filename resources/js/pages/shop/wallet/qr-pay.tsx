import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, ScanLine } from 'lucide-react';
import { FormEvent, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { type KycPayload } from '@/components/wallet/kyc-verification-form';
import { SharedData } from '@/types';

type ResolvedRecipient = {
    user: { id: number; name: string; mobile?: string | null };
    amount?: number | null;
    reason?: string | null;
};

type Props = {
    hasPaymentPin: boolean;
    kyc: KycPayload;
};

export default function BuyerQrPay({ hasPaymentPin, kyc }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [resolved, setResolved] = useState<ResolvedRecipient | null>(null);
    const [resolveError, setResolveError] = useState<string | null>(null);
    const [resolving, setResolving] = useState(false);

    const resolveForm = useForm({ payload: '' });

    const payForm = useForm({
        payload: '',
        amount: '',
        note: '',
        payment_pin: '',
    });

    const resolvePayload = async (e: FormEvent) => {
        e.preventDefault();
        setResolveError(null);
        setResolving(true);

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        try {
            const res = await fetch(route('wallet.qr.resolve'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ payload: resolveForm.data.payload.trim() }),
            });
            const data = await res.json();
            if (!res.ok) {
                const msg = data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not read QR code';
                setResolveError(String(msg));
                return;
            }
            setResolved(data.data);
            payForm.setData({
                payload: resolveForm.data.payload.trim(),
                amount: data.data.amount != null ? String(data.data.amount) : '',
                note: data.data.reason ?? '',
                payment_pin: '',
            });
        } catch {
            setResolveError('Could not read QR code. Try again.');
        } finally {
            setResolving(false);
        }
    };

    const submitPay = (e: FormEvent) => {
        e.preventDefault();
        payForm.post(route('wallet.qr.pay.store'), { preserveScroll: true });
    };

    return (
        <ShopLayout>
            <Head title="Pay with QR" />
            <div className="mx-auto max-w-lg px-4 py-6 sm:py-8">
                <Link href={route('wallet.index')} className="text-sm font-semibold text-orange-600 hover:underline">
                    &larr; Back to wallet
                </Link>

                <div className="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <div className="flex items-center gap-3">
                        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                            <ScanLine className="h-5 w-5" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">Pay with QR</h1>
                            <p className="text-sm text-gray-500">Paste a CityShop QR code or payment link</p>
                        </div>
                    </div>

                    {flash?.success && (
                        <p className="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</p>
                    )}
                    {flash?.error && (
                        <p className="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800">{flash.error}</p>
                    )}

                    {!kyc.can_store_funds && (
                        <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Ghana Card approval required.{' '}
                            <Link href={route('kyc.index')} className="font-semibold underline">
                                Verify Ghana Card
                            </Link>
                        </p>
                    )}

                    {!hasPaymentPin && (
                        <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Set a payment PIN first.{' '}
                            <Link href={route('shop.payment-pin.edit')} className="font-semibold underline">
                                Set PIN
                            </Link>
                        </p>
                    )}

                    {!resolved ? (
                        <form onSubmit={resolvePayload} className="mt-5 space-y-3">
                            <div>
                                <Label htmlFor="payload">QR code or link</Label>
                                <Input
                                    id="payload"
                                    value={resolveForm.data.payload}
                                    onChange={(e) => resolveForm.setData('payload', e.target.value)}
                                    placeholder="Paste CS1… code or cityshop://pay link"
                                    className="mt-1"
                                    required
                                />
                                <InputError message={resolveForm.errors.payload} />
                            </div>
                            {resolveError && <p className="text-sm text-red-600">{resolveError}</p>}
                            <Button type="submit" disabled={resolving} className="w-full bg-orange-500 hover:bg-orange-600">
                                {resolving && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Continue
                            </Button>
                        </form>
                    ) : (
                        <form onSubmit={submitPay} className="mt-5 space-y-4">
                            <div className="rounded-xl bg-gray-50 p-4">
                                <p className="text-xs font-semibold uppercase text-gray-500">Pay to</p>
                                <p className="text-lg font-bold text-gray-900">{resolved.user.name}</p>
                                {resolved.user.mobile && (
                                    <p className="text-sm text-gray-600">{resolved.user.mobile}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="amount">Amount (GHS)</Label>
                                <Input
                                    id="amount"
                                    inputMode="decimal"
                                    value={payForm.data.amount}
                                    onChange={(e) => payForm.setData('amount', e.target.value)}
                                    readOnly={resolved.amount != null}
                                    className="mt-1"
                                    required
                                />
                                <InputError message={payForm.errors.amount} />
                            </div>

                            <div>
                                <Label htmlFor="note">Note (optional)</Label>
                                <Input
                                    id="note"
                                    value={payForm.data.note}
                                    onChange={(e) => payForm.setData('note', e.target.value)}
                                    className="mt-1"
                                />
                                <InputError message={payForm.errors.note} />
                            </div>

                            <div>
                                <Label htmlFor="payment_pin">Payment PIN</Label>
                                <Input
                                    id="payment_pin"
                                    type="password"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={payForm.data.payment_pin}
                                    onChange={(e) =>
                                        payForm.setData('payment_pin', e.target.value.replace(/\D/g, '').slice(0, 4))
                                    }
                                    className="mt-1"
                                    required
                                />
                                <InputError message={payForm.errors.payment_pin} />
                            </div>

                            <Button
                                type="submit"
                                disabled={payForm.processing || !kyc.can_store_funds || !hasPaymentPin}
                                className="w-full bg-orange-500 hover:bg-orange-600"
                            >
                                {payForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Send payment
                            </Button>

                            <button
                                type="button"
                                onClick={() => setResolved(null)}
                                className="w-full text-sm font-semibold text-gray-500 hover:text-gray-700"
                            >
                                Scan a different code
                            </button>
                        </form>
                    )}

                    <Link
                        href={route('wallet.qr.receive')}
                        className="mt-4 block text-center text-sm font-semibold text-orange-600 hover:underline"
                    >
                        Show my QR to receive &rarr;
                    </Link>
                </div>
            </div>
        </ShopLayout>
    );
}
