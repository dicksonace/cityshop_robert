import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice, Wallet } from '@/types/marketplace';

type Field = {
    id: number;
    group: string;
    type: string;
    name: string;
    label: string;
    placeholder: string | null;
    help_text: string | null;
    required: boolean;
    options: string[];
};

type Method = {
    id: number;
    name: string;
    type: string;
    account_name: string | null;
    account_number: string | null;
    bank_name: string | null;
    network: string | null;
    instructions: string | null;
    qr_url: string | null;
};

type Config = {
    enabled: boolean;
    instructions: string | null;
    rate: {
        ghs_per_rmb: number;
        rmb_per_ghs: number;
        min_ghs: number;
        max_ghs: number;
        fee_mode: 'flat' | 'percent';
        fee_value: number;
    } | null;
    payment_methods: Method[];
    fields: Field[];
};

type Kyc = { can_store_funds?: boolean; status_label?: string };

interface Props {
    config: Config;
    wallet: Wallet;
    hasPaymentPin?: boolean;
    kyc?: Kyc;
    initialGhs?: string | null;
}

function fieldKey(id: number) {
    return `fields.${id}`;
}

function isQrField(field: Field) {
    const blob = `${field.name} ${field.label}`.toLowerCase();
    return ['image', 'document', 'files'].includes(field.type) || blob.includes('qr');
}

/** Buy RMB step 2: QR + optional recipient + pay GHS (rmb-wallet style). */
export default function ChinaTransferCreate({
    config,
    hasPaymentPin = false,
    kyc,
    initialGhs,
}: Props) {
    const { flash } = usePage<SharedData>().props;

    const form = useForm({
        funding_source: 'external' as const,
        ghs_amount: String(initialGhs ?? config.rate?.min_ghs ?? ''),
        payment_method_id: String(config.payment_methods[0]?.id ?? ''),
        payment_pin: '',
        fields: {} as Record<string, string>,
        files: {} as Record<string, File | null>,
    });

    const quote = useMemo(() => {
        if (!config.rate) return null;
        const amount = Number(form.data.ghs_amount);
        if (!Number.isFinite(amount) || amount <= 0) return null;
        const rmb = amount / config.rate.ghs_per_rmb;
        const fee =
            config.rate.fee_mode === 'percent' ? (amount * config.rate.fee_value) / 100 : config.rate.fee_value;
        return { rmb, fee, total: amount + fee, ghs: amount };
    }, [form.data.ghs_amount, config.rate]);

    const method = config.payment_methods.find((m) => String(m.id) === form.data.payment_method_id);

    const recipientFields = config.fields.filter((f) => {
        const g = f.group.toLowerCase();
        return !['payment', 'payment_proof', 'proof'].includes(g);
    });
    const paymentFields = config.fields.filter((f) => {
        const g = f.group.toLowerCase();
        return ['payment', 'payment_proof', 'proof'].includes(g);
    });
    const qrFields = recipientFields.filter(isQrField);
    const textFields = recipientFields.filter((f) => !isQrField(f));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const payload: Record<string, unknown> = {
            funding_source: 'external',
            payment_pin: form.data.payment_pin,
            ghs_amount: form.data.ghs_amount,
            payment_method_id: form.data.payment_method_id,
        };
        Object.entries(form.data.fields).forEach(([id, value]) => {
            payload[`fields[${id}]`] = value;
        });
        Object.entries(form.data.files).forEach(([id, file]) => {
            if (file) payload[`files[${id}]`] = file;
        });
        form.transform(() => payload);
        form.post(route('wallet.china-transfer.store'), { forceFormData: true });
    };

    const formError =
        flash.error ||
        form.errors.ghs_amount ||
        form.errors.payment_method_id ||
        form.errors.funding_source ||
        form.errors.payment_pin ||
        form.errors.kyc;

    return (
        <ShopLayout hideFlash>
            <Head title="Submit Transfer Request" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <Link href={route('wallet.china-transfer.index')} className="text-sm font-semibold text-indigo-600">
                    ← Buy RMB
                </Link>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Submit Transfer Request</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Upload the recipient Alipay QR, pay GHS — we send RMB at today’s rate.
                </p>

                {formError && (
                    <p className="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{formError}</p>
                )}
                {!kyc?.can_store_funds && (
                    <p className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        KYC required before transfer.{' '}
                        <Link href={route('account.index')} className="font-semibold underline">
                            Open account / KYC
                        </Link>
                    </p>
                )}
                {!hasPaymentPin && (
                    <p className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Set a 4-digit payment PIN in Profile before transferring.
                    </p>
                )}

                {quote && (
                    <div className="mt-4 rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm">
                        <div className="flex justify-between font-bold text-indigo-900">
                            <span>You send</span>
                            <span>{formatPrice(quote.ghs)}</span>
                        </div>
                        <div className="mt-1 flex justify-between font-bold text-indigo-900">
                            <span>They receive</span>
                            <span>¥{quote.rmb.toFixed(2)}</span>
                        </div>
                        <p className="mt-2 text-xs text-indigo-700">
                            Fee {formatPrice(quote.fee)} · Total {formatPrice(quote.total)}
                        </p>
                    </div>
                )}

                <form onSubmit={submit} className="mt-5 space-y-5">
                    {qrFields.map((field) => {
                        const error = form.errors[fieldKey(field.id)] || form.errors[`files.${field.id}`];
                        const file = form.data.files[field.id];
                        return (
                            <section key={field.id} className="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4">
                                <Label className="text-base font-bold">
                                    Upload Alipay QR Code{field.required ? ' *' : ''}
                                </Label>
                                <p className="mt-1 text-sm text-gray-500">
                                    {field.help_text ?? "Upload recipient's Alipay QR code"}
                                </p>
                                <div className="mt-3 flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-6">
                                    <p className="text-sm font-semibold text-gray-600">
                                        {file ? file.name : 'Choose recipient QR image'}
                                    </p>
                                    <label className="cursor-pointer rounded-xl bg-red-600 px-5 py-2.5 text-sm font-extrabold text-white hover:bg-red-700">
                                        Choose Image
                                        <input
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={(e) => {
                                                const next = e.target.files?.[0] ?? null;
                                                form.setData('files', { ...form.data.files, [field.id]: next });
                                            }}
                                        />
                                    </label>
                                </div>
                                <InputError message={error} />
                            </section>
                        );
                    })}

                    {textFields.map((field) => {
                        const error = form.errors[fieldKey(field.id)];
                        const optional = !field.required;
                        return (
                            <div key={field.id} className="space-y-1.5">
                                <Label>
                                    {field.label}
                                    {optional ? ' (Optional)' : ' *'}
                                </Label>
                                {field.type === 'textarea' ? (
                                    <textarea
                                        className="min-h-24 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                        placeholder={field.placeholder ?? ''}
                                        value={form.data.fields[field.id] ?? ''}
                                        onChange={(e) =>
                                            form.setData('fields', {
                                                ...form.data.fields,
                                                [field.id]: e.target.value,
                                            })
                                        }
                                    />
                                ) : (
                                    <Input
                                        placeholder={field.placeholder ?? ''}
                                        value={form.data.fields[field.id] ?? ''}
                                        onChange={(e) =>
                                            form.setData('fields', {
                                                ...form.data.fields,
                                                [field.id]: e.target.value,
                                            })
                                        }
                                    />
                                )}
                                {field.help_text && <p className="text-xs text-gray-500">{field.help_text}</p>}
                                <InputError message={error} />
                            </div>
                        );
                    })}

                    <section className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Pay GHS to CityShop</h2>
                        <div className="space-y-2">
                            {config.payment_methods.map((item) => (
                                <label
                                    key={item.id}
                                    className={`block cursor-pointer rounded-xl border px-3 py-3 ${
                                        form.data.payment_method_id === String(item.id)
                                            ? 'border-indigo-400 bg-indigo-50'
                                            : 'border-gray-200'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        className="mr-2"
                                        checked={form.data.payment_method_id === String(item.id)}
                                        onChange={() => form.setData('payment_method_id', String(item.id))}
                                    />
                                    <span className="font-semibold">{item.name}</span>
                                    {item.account_number && (
                                        <span className="mt-1 block text-sm text-gray-600">
                                            {item.account_name} · {item.account_number}
                                            {item.bank_name ? ` · ${item.bank_name}` : ''}
                                        </span>
                                    )}
                                </label>
                            ))}
                        </div>
                        {method?.instructions && <p className="text-sm text-gray-600">{method.instructions}</p>}
                        {method?.qr_url && (
                            <img src={method.qr_url} alt="Pay QR" className="mt-2 h-40 w-40 rounded-xl object-cover" />
                        )}
                        {paymentFields.map((field) => {
                            const error = form.errors[fieldKey(field.id)] || form.errors[`files.${field.id}`];
                            if (['image', 'document', 'files'].includes(field.type)) {
                                return (
                                    <div key={field.id} className="space-y-1.5">
                                        <Label>
                                            {field.label}
                                            {field.required ? ' *' : ''}
                                        </Label>
                                        <input
                                            type="file"
                                            accept={field.type === 'image' ? 'image/*' : undefined}
                                            onChange={(e) => {
                                                const file = e.target.files?.[0] ?? null;
                                                form.setData('files', { ...form.data.files, [field.id]: file });
                                            }}
                                        />
                                        <InputError message={error} />
                                    </div>
                                );
                            }
                            return (
                                <div key={field.id} className="space-y-1.5">
                                    <Label>
                                        {field.label}
                                        {field.required ? ' *' : ''}
                                    </Label>
                                    <Input
                                        value={form.data.fields[field.id] ?? ''}
                                        onChange={(e) =>
                                            form.setData('fields', {
                                                ...form.data.fields,
                                                [field.id]: e.target.value,
                                            })
                                        }
                                    />
                                    <InputError message={error} />
                                </div>
                            );
                        })}
                    </section>

                    <section className="space-y-2 rounded-2xl border border-gray-200 bg-white p-4">
                        <Label>Payment PIN *</Label>
                        <Input
                            type="password"
                            inputMode="numeric"
                            maxLength={4}
                            value={form.data.payment_pin}
                            onChange={(e) =>
                                form.setData('payment_pin', e.target.value.replace(/\D/g, '').slice(0, 4))
                            }
                            placeholder="••••"
                        />
                        <InputError message={form.errors.payment_pin} />
                    </section>

                    <Button disabled={form.processing} className="w-full bg-red-600 hover:bg-red-700">
                        {form.processing ? 'Submitting…' : 'Submit Transfer Request'}
                    </Button>
                </form>
            </div>
        </ShopLayout>
    );
}
