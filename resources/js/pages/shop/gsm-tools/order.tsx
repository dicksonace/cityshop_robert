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
    overview: string | null;
    features: string[];
    what_to_send: string | null;
    eta_label: string;
    allow_quantity: boolean;
    min_qty: number;
    max_qty: number;
    price_ghs: number;
    image_url: string | null;
    fields: Field[];
};

function isEmailField(field: Field): boolean {
    return field.type === 'email' || field.name.toLowerCase() === 'email' || field.label.toLowerCase() === 'email';
}

interface Props {
    service: Service;
    wallet: { available_balance: number };
    hasPaymentPin: boolean;
    contactEmail: string;
}

export default function GsmToolOrder({ service, wallet, hasPaymentPin, contactEmail }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [pin, setPin] = useState('');
    const [qty, setQty] = useState(service.min_qty || 1);
    const min = Math.max(1, service.min_qty || 1);
    const max = Math.max(min, service.max_qty || 1000);
    const quantity = service.allow_quantity ? Math.min(max, Math.max(min, qty)) : 1;
    const total = service.price_ghs * quantity;

    const initialFields = useMemo(() => {
        const map: Record<string, string | File | null> = {};
        for (const field of service.fields) map[field.name] = field.type === 'image' ? null : '';
        return map;
    }, [service.fields]);

    const form = useForm({
        gsm_service_id: service.id,
        quantity: quantity,
        email: contactEmail || '',
        fields: initialFields,
        payment_pin: '',
    });

    const hasEmailField = service.fields.some(isEmailField);
    const enough = wallet.available_balance >= total;

    const setField = (name: string, value: string | File | null, emailSync = false) => {
        form.setData({
            ...form.data,
            fields: { ...form.data.fields, [name]: value },
            email: emailSync && typeof value === 'string' ? value : form.data.email,
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const fields: Record<string, string | File> = {};
            for (const [key, value] of Object.entries(data.fields)) {
                if (value instanceof File || (typeof value === 'string' && value !== '')) {
                    fields[key] = value;
                }
            }
            return { ...data, fields, quantity, payment_pin: pin };
        });
        form.post(route('gsm-tools.orders.store'), { forceFormData: true });
    };

    return (
        <ShopLayout>
            <Head title={service.name} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <button type="button" onClick={() => router.visit(route('gsm-tools.index'))} className="mb-3 text-sm text-orange-600">
                    ← Back to services
                </button>
                <div className="flex items-start gap-3">
                    {service.image_url ? (
                        <img src={service.image_url} alt="" className="h-16 w-16 shrink-0 rounded-2xl bg-slate-950 object-contain" />
                    ) : null}
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">{service.name}</h1>
                        <p className="mt-1 text-sm font-semibold text-orange-600">
                            {formatPrice(service.price_ghs)} · Delivery {service.eta_label || 'INSTANT'}
                        </p>
                    </div>
                </div>

                {service.overview || service.description ? (
                    <section className="mt-4">
                        <h2 className="text-sm font-bold text-gray-900">Overview</h2>
                        <p className="mt-1 whitespace-pre-line text-sm text-gray-600">{service.overview || service.description}</p>
                    </section>
                ) : null}

                {service.features?.length ? (
                    <section className="mt-4">
                        <h2 className="text-sm font-bold text-gray-900">Key Features</h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                            {service.features.map((feature) => (
                                <li key={feature}>{feature}</li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                {service.what_to_send ? (
                    <section className="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2">
                        <h2 className="text-sm font-bold text-gray-900">What You Need To Send</h2>
                        <p className="mt-1 whitespace-pre-line text-sm text-gray-700">{service.what_to_send}</p>
                    </section>
                ) : null}

                <p className="mt-4 text-sm font-semibold text-gray-900">
                    Total {formatPrice(total)} — deducted from your wallet.
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
                    {service.allow_quantity ? (
                        <div>
                            <Label>Quantity *</Label>
                            <div className="mt-1 flex items-center gap-2">
                                <button
                                    type="button"
                                    className="h-10 w-10 rounded-xl border border-gray-200 text-lg"
                                    onClick={() => setQty(Math.max(min, quantity - 1))}
                                >
                                    −
                                </button>
                                <Input className="text-center" readOnly value={quantity} />
                                <button
                                    type="button"
                                    className="h-10 w-10 rounded-xl border border-gray-200 text-lg"
                                    onClick={() => setQty(Math.min(max, quantity + 1))}
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    ) : null}

                    {service.fields.map((field) => (
                        <div key={field.id}>
                            <Label htmlFor={field.name}>
                                {field.label}
                                {field.required ? '*' : ''}
                            </Label>
                            {field.type === 'image' ? (
                                <input
                                    id={field.name}
                                    type="file"
                                    accept="image/*"
                                    className="mt-1 block w-full text-sm text-gray-700"
                                    onChange={(e) => setField(field.name, e.target.files?.[0] ?? null)}
                                />
                            ) : field.type === 'textarea' ? (
                                <textarea
                                    id={field.name}
                                    className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                    rows={3}
                                    placeholder={field.placeholder ?? undefined}
                                    value={typeof form.data.fields[field.name] === 'string' ? (form.data.fields[field.name] as string) : ''}
                                    onChange={(e) => setField(field.name, e.target.value)}
                                />
                            ) : (
                                <Input
                                    id={field.name}
                                    className="mt-1"
                                    type={field.type === 'password' ? 'password' : field.type === 'email' ? 'email' : 'text'}
                                    inputMode={
                                        field.type === 'number'
                                            ? 'decimal'
                                            : field.type === 'phone'
                                              ? 'tel'
                                              : field.type === 'email'
                                                ? 'email'
                                                : undefined
                                    }
                                    placeholder={field.placeholder ?? undefined}
                                    value={typeof form.data.fields[field.name] === 'string' ? (form.data.fields[field.name] as string) : ''}
                                    onChange={(e) => setField(field.name, e.target.value, isEmailField(field))}
                                />
                            )}
                            <InputError message={(form.errors as Record<string, string>)[`fields.${field.name}`]} />
                        </div>
                    ))}

                    {!hasEmailField ? (
                    <div>
                        <Label htmlFor="email">Email*</Label>
                        <Input
                            id="email"
                            className="mt-1"
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    ) : null}

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
