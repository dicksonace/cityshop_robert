import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, ShieldBan, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { SharedData } from '@/types';

interface PaymentMethod {
    id: number;
    type: string;
    label: string | null;
    account_name: string;
    account_number: string | null;
    network: string | null;
    bank_name: string | null;
    instructions: string | null;
    is_active: boolean;
    is_default: boolean;
    is_disabled: boolean;
    disabled_reason: string | null;
    disabled_at: string | null;
}

interface PaymentMethodsProps {
    profile: {
        accept_marketplace_payments: boolean;
        accept_direct_payments: boolean;
        payment_methods_locked: boolean;
        payment_methods_lock_reason: string | null;
    };
    methods: PaymentMethod[];
    types: { value: string; label: string }[];
}

export default function PaymentMethodsIndex({ profile, methods, types }: PaymentMethodsProps) {
    const { flash } = usePage<SharedData>().props;
    const locked = profile.payment_methods_locked;

    const settingsForm = useForm({
        accept_marketplace_payments: profile.accept_marketplace_payments,
        accept_direct_payments: profile.accept_direct_payments,
    });

    const methodForm = useForm({
        type: 'mobile_money',
        label: '',
        account_name: '',
        account_number: '',
        network: 'MTN',
        bank_name: '',
        instructions: '',
        is_default: false,
    });

    const saveSettings: FormEventHandler = (e) => {
        e.preventDefault();
        settingsForm.post(route('seller.payment-methods.settings'));
    };

    const addMethod: FormEventHandler = (e) => {
        e.preventDefault();
        if (locked) return;
        methodForm.post(route('seller.payment-methods.store'), { onSuccess: () => methodForm.reset() });
    };

    return (
        <SellerLayout title="Payment Methods" active="payment-methods">
            <Head title="Payment Methods" />

            {flash.success && <div className="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{flash.success}</div>}
            {flash.error && <div className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{flash.error}</div>}

            {locked && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    <p className="flex items-center gap-2 font-semibold">
                        <ShieldBan className="h-4 w-4" />
                        Payment setup locked by CityShop admin
                    </p>
                    <p className="mt-1">
                        {profile.payment_methods_lock_reason
                            ?? 'A payment method was flagged as suspicious. You cannot add new payment methods until admin unlocks your account.'}
                    </p>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold text-gray-900">Payment mode</h2>
                    <p className="mt-1 text-sm text-gray-500">Choose how buyers can pay you at checkout.</p>
                    <form onSubmit={saveSettings} className="mt-4 space-y-3">
                        <label className="flex items-start gap-3 rounded-lg border p-3">
                            <input
                                type="checkbox"
                                checked={settingsForm.data.accept_marketplace_payments}
                                onChange={(e) => settingsForm.setData('accept_marketplace_payments', e.target.checked)}
                            />
                            <div>
                                <p className="font-medium">Marketplace payments (default)</p>
                                <p className="text-sm text-gray-500">Buyer pays CityShop via Paystack. You withdraw from your wallet.</p>
                            </div>
                        </label>
                        <label className="flex items-start gap-3 rounded-lg border p-3">
                            <input
                                type="checkbox"
                                checked={settingsForm.data.accept_direct_payments}
                                onChange={(e) => settingsForm.setData('accept_direct_payments', e.target.checked)}
                            />
                            <div>
                                <p className="font-medium">My payment methods</p>
                                <p className="text-sm text-gray-500">Buyer pays you directly. You confirm payment in your orders.</p>
                            </div>
                        </label>
                        <Button type="submit" disabled={settingsForm.processing} className="bg-orange-500 hover:bg-orange-600">
                            Save settings
                        </Button>
                    </form>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <h2 className="font-semibold text-gray-900">Add payment method</h2>
                    {locked ? (
                        <p className="mt-4 text-sm text-gray-500">
                            Adding new payment methods is blocked while your account is under review.
                        </p>
                    ) : (
                        <form onSubmit={addMethod} className="mt-4 space-y-3">
                            <div>
                                <Label>Type</Label>
                                <select value={methodForm.data.type} onChange={(e) => methodForm.setData('type', e.target.value)} className="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                    {types.map((t) => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>Account name</Label>
                                <Input value={methodForm.data.account_name} onChange={(e) => methodForm.setData('account_name', e.target.value)} required className="mt-1" />
                                <InputError message={methodForm.errors.account_name} />
                            </div>
                            {methodForm.data.type === 'mobile_money' && (
                                <>
                                    <div>
                                        <Label>Network</Label>
                                        <Input value={methodForm.data.network} onChange={(e) => methodForm.setData('network', e.target.value)} className="mt-1" placeholder="MTN, Telecel, AirtelTigo" />
                                    </div>
                                    <div>
                                        <Label>MoMo number</Label>
                                        <Input value={methodForm.data.account_number} onChange={(e) => methodForm.setData('account_number', e.target.value)} className="mt-1" />
                                    </div>
                                </>
                            )}
                            {methodForm.data.type === 'bank' && (
                                <>
                                    <div>
                                        <Label>Bank name</Label>
                                        <Input value={methodForm.data.bank_name} onChange={(e) => methodForm.setData('bank_name', e.target.value)} className="mt-1" />
                                    </div>
                                    <div>
                                        <Label>Account number</Label>
                                        <Input value={methodForm.data.account_number} onChange={(e) => methodForm.setData('account_number', e.target.value)} className="mt-1" />
                                    </div>
                                </>
                            )}
                            <div>
                                <Label>Instructions for buyer (optional)</Label>
                                <Input value={methodForm.data.instructions} onChange={(e) => methodForm.setData('instructions', e.target.value)} className="mt-1" />
                            </div>
                            <Button type="submit" disabled={methodForm.processing} className="bg-orange-500 hover:bg-orange-600">
                                {methodForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Add method
                            </Button>
                        </form>
                    )}
                </div>
            </div>

            <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <h2 className="font-semibold text-gray-900">Your payment methods</h2>
                {methods.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">No methods added yet.</p>
                ) : (
                    <ul className="mt-4 divide-y">
                        {methods.map((m) => (
                            <li key={m.id} className="flex items-center justify-between gap-3 py-3 text-sm">
                                <div className="min-w-0">
                                    <p className="font-medium capitalize">{m.type.replace('_', ' ')}</p>
                                    <p className="text-gray-500">{m.account_name} · {m.account_number ?? m.label}</p>
                                    {m.is_disabled ? (
                                        <p className="mt-1 text-xs font-medium text-red-600">
                                            Disabled by admin
                                            {m.disabled_reason ? ` — ${m.disabled_reason}` : ''}
                                        </p>
                                    ) : m.is_default ? (
                                        <p className="mt-1 text-xs text-blue-600">Default</p>
                                    ) : null}
                                </div>
                                {!m.is_disabled && (
                                    <button
                                        type="button"
                                        onClick={() => router.delete(route('seller.payment-methods.destroy', m.id))}
                                        className="shrink-0 text-red-500"
                                        aria-label="Remove payment method"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </SellerLayout>
    );
}
