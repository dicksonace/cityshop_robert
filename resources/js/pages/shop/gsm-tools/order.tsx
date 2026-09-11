import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Field = {
    id: number;
    name: string;
    label: string;
    placeholder: string | null;
    type: string;
    required: boolean;
};

type Service = {
    id: number;
    name: string;
    description: string | null;
    price_ghs: number;
    fields: Field[];
};

interface Props {
    service: Service;
    wallet: { available_balance: number };
    hasPaymentPin: boolean;
}

export default function GsmToolOrder({ service, wallet, hasPaymentPin }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [pin, setPin] = useState('');
    const initialFields = useMemo(() => {
        const map: Record<string, string> = {};
        for (const field of service.fields) map[field.name] = '';
        return map;
    }, [service.fields]);

    const form = useForm({
        gsm_service_id: service.id,
        fields: initialFields,
        payment_pin: '',
    });

    const enough = wallet.available_balance >= service.price_ghs;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.setData('payment_pin', pin);
        form.transform((data) => ({ ...data, payment_pin: pin }));
        form.post(route('gsm-tools.orders.store'));
    };

    return (
        <ShopLayout>
            <Head title={service.name} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <button type="button" onClick={() => router.visit(route('gsm-tools.index'))} className="mb-3 text-sm text-orange-600">
                    ← GSM Tools
                </button>
                <h1 className="text-xl font-bold text-gray-900">{service.name}</h1>
                {service.description ? <p className="mt-2 text-sm text-gray-600">{service.description}</p> : null}

                <p className="mt-4 text-sm font-semibold text-gray-900">
                    Total {formatPrice(service.price_ghs)} — deducted from your wallet.
                </p>
                <p className="mt-1 text-sm text-gray-500">Balance {formatPrice(wallet.available_balance)}</p>

                {!enough ? (
                    <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        You don&apos;t have enough balance. Please top up your wallet.
                    </div>
                ) : null}

                {(flash?.success || flash?.error) && (
                    <div className={`mt-3 rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </div>
                )}

                <form onSubmit={submit} className="mt-5 space-y-4">
                    {service.fields.map((field) => (
                        <div key={field.id}>
                            <Label htmlFor={field.name}>
                                {field.label}
                                {field.required ? '*' : ''}
                            </Label>
                            {field.type === 'textarea' ? (
                                <textarea
                                    id={field.name}
                                    className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                    rows={3}
                                    placeholder={field.placeholder ?? undefined}
                                    value={form.data.fields[field.name] ?? ''}
                                    onChange={(e) =>
                                        form.setData('fields', { ...form.data.fields, [field.name]: e.target.value })
                                    }
                                />
                            ) : (
                                <Input
                                    id={field.name}
                                    className="mt-1"
                                    placeholder={field.placeholder ?? undefined}
                                    value={form.data.fields[field.name] ?? ''}
                                    onChange={(e) =>
                                        form.setData('fields', { ...form.data.fields, [field.name]: e.target.value })
                                    }
                                />
                            )}
                            <InputError message={(form.errors as Record<string, string>)[`fields.${field.name}`]} />
                        </div>
                    ))}

                    {hasPaymentPin ? (
                        <div>
                            <Label htmlFor="payment_pin">Payment PIN*</Label>
                            <Input
                                id="payment_pin"
                                className="mt-1"
                                inputMode="numeric"
                                maxLength={4}
                                value={pin}
                                onChange={(e) => setPin(e.target.value.replace(/\D/g, '').slice(0, 4))}
                            />
                            <InputError message={form.errors.payment_pin} />
                        </div>
                    ) : (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Set a payment PIN in your account before placing GSM orders.
                        </div>
                    )}

                    <InputError message={form.errors.balance || form.errors.gsm_service_id} />

                    <Button type="submit" disabled={form.processing || !enough || !hasPaymentPin} className="w-full bg-orange-500 hover:bg-orange-600">
                        Place Order
                    </Button>
                </form>
            </div>
        </ShopLayout>
    );
}
