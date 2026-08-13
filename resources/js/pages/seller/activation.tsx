import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { SharedData } from '@/types';
import { formatPrice, Wallet } from '@/types/marketplace';

interface ActivationPayload {
    fee_amount: number;
    prompted_at?: string | null;
    paid_until?: string | null;
    paid_at?: string | null;
    is_active: boolean;
    needs_payment: boolean;
}

interface Props {
    activation: ActivationPayload;
    wallet: Wallet;
    hasPaymentPin: boolean;
}

function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function SellerActivation({ activation, wallet, hasPaymentPin }: Props) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({ payment_pin: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('seller.activation.pay'));
    };

    return (
        <SellerLayout title="Store activation" active="dashboard">
            <Head title="Store activation" />

            <div className="mx-auto max-w-lg space-y-4">
                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>
                )}
                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>
                )}

                <div className="rounded-2xl border border-orange-100 bg-white p-5 shadow-sm">
                    <h1 className="text-xl font-black text-gray-900">Seller service fee</h1>
                    <p className="mt-2 text-sm text-gray-600">
                        Pay once for 1 year. Until you pay, buyers cannot see your products and you cannot post new listings.
                        You can still withdraw and recharge your wallet.
                    </p>

                    <div className="mt-4 rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900">
                        <div className="flex justify-between gap-3">
                            <span>Service fee</span>
                            <span className="font-bold">{formatPrice(activation.fee_amount)}</span>
                        </div>
                        <div className="mt-1.5 flex justify-between gap-3">
                            <span>Wallet balance</span>
                            <span className="font-semibold">{formatPrice(wallet.available_balance)}</span>
                        </div>
                        {activation.paid_until && (
                            <div className="mt-1.5 flex justify-between gap-3">
                                <span>{activation.is_active ? 'Active until' : 'Expired'}</span>
                                <span>{formatDate(activation.paid_until)}</span>
                            </div>
                        )}
                    </div>

                    {activation.is_active ? (
                        <p className="mt-4 text-sm font-medium text-emerald-700">Your store is active. No payment is due.</p>
                    ) : !hasPaymentPin ? (
                        <p className="mt-4 text-sm text-amber-800">
                            Set a payment PIN first, then come back to pay.{' '}
                            <Link href={route('payment-pin.edit')} className="font-semibold text-orange-600 underline">
                                Open account
                            </Link>
                        </p>
                    ) : (
                        <form onSubmit={submit} className="mt-4 space-y-3">
                            <div>
                                <Label htmlFor="payment_pin">Payment PIN</Label>
                                <Input
                                    id="payment_pin"
                                    type="password"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={form.data.payment_pin}
                                    onChange={(e) => form.setData('payment_pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                    className="mt-1"
                                    autoComplete="off"
                                />
                                <InputError message={form.errors.payment_pin ?? form.errors.amount} />
                            </div>
                            <Button type="submit" disabled={form.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                                Pay {formatPrice(activation.fee_amount)} from wallet
                            </Button>
                        </form>
                    )}

                    <div className="mt-4 flex flex-wrap gap-3 text-sm">
                        <Link href={route('seller.wallet')} className="font-semibold text-orange-600 hover:underline">
                            Recharge wallet
                        </Link>
                        <Link href={route('seller.wallet')} className="font-semibold text-gray-600 hover:underline">
                            Withdraw
                        </Link>
                    </div>
                </div>
            </div>
        </SellerLayout>
    );
}
