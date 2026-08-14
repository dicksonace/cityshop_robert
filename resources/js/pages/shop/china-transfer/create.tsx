import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Field = {
    id: number;
    group: 'recipient' | 'payment';
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

interface Props {
    config: Config;
}

function fieldKey(id: number) {
    return `fields.${id}`;
}

export default function ChinaTransferCreate({ config }: Props) {
    const { flash } = usePage<SharedData>().props;
    const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
    const initialGhs = params.get('ghs_amount') || String(config.rate?.min_ghs ?? '');

    const form = useForm({
        ghs_amount: initialGhs,
        payment_method_id: String(config.payment_methods[0]?.id ?? ''),
        fields: {} as Record<string, string>,
        files: {} as Record<string, File | null>,
    });

    const quote = useMemo(() => {
        const amount = Number(form.data.ghs_amount);
        if (!config.rate || !Number.isFinite(amount) || amount <= 0) return null;
        const rmb = amount / config.rate.ghs_per_rmb;
        const fee = config.rate.fee_mode === 'percent' ? (amount * config.rate.fee_value) / 100 : config.rate.fee_value;
        return { rmb, fee, total: amount + fee };
    }, [form.data.ghs_amount, config.rate]);

    const method = config.payment_methods.find((m) => String(m.id) === form.data.payment_method_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const payload: Record<string, unknown> = {
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

    const setField = (id: number, value: string) => {
        form.setData('fields', { ...form.data.fields, [id]: value });
    };

    const renderField = (field: Field) => {
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
                        multiple={field.type === 'files'}
                        onChange={(e) => {
                            const file = e.target.files?.[0] ?? null;
                            form.setData('files', { ...form.data.files, [field.id]: file });
                        }}
                    />
                    {field.help_text && <p className="text-xs text-gray-500">{field.help_text}</p>}
                    <InputError message={error} />
                </div>
            );
        }

        if (field.type === 'textarea') {
            return (
                <div key={field.id} className="space-y-1.5">
                    <Label>
                        {field.label}
                        {field.required ? ' *' : ''}
                    </Label>
                    <textarea
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        placeholder={field.placeholder ?? ''}
                        value={form.data.fields[field.id] ?? ''}
                        onChange={(e) => setField(field.id, e.target.value)}
                    />
                    {field.help_text && <p className="text-xs text-gray-500">{field.help_text}</p>}
                    <InputError message={error} />
                </div>
            );
        }

        if (field.type === 'dropdown' || field.type === 'radio') {
            return (
                <div key={field.id} className="space-y-1.5">
                    <Label>
                        {field.label}
                        {field.required ? ' *' : ''}
                    </Label>
                    <select
                        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        value={form.data.fields[field.id] ?? ''}
                        onChange={(e) => setField(field.id, e.target.value)}
                    >
                        <option value="">Select</option>
                        {field.options.map((opt) => (
                            <option key={opt} value={opt}>
                                {opt}
                            </option>
                        ))}
                    </select>
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
                    type={field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : field.type === 'date' ? 'date' : 'text'}
                    placeholder={field.placeholder ?? ''}
                    value={form.data.fields[field.id] ?? ''}
                    onChange={(e) => setField(field.id, e.target.value)}
                />
                {field.help_text && <p className="text-xs text-gray-500">{field.help_text}</p>}
                <InputError message={error} />
            </div>
        );
    };

    const recipientFields = config.fields.filter((f) => f.group === 'recipient');
    const paymentFields = config.fields.filter((f) => f.group === 'payment');

    return (
        <ShopLayout hideFlash>
            <Head title="New China Transfer" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <Link href={route('wallet.china-transfer.index')} className="text-sm font-semibold text-orange-600">
                    ← Rates
                </Link>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Send via Alipay</h1>
                {(flash.error || form.errors.ghs_amount) && (
                    <p className="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
                        {flash.error || form.errors.ghs_amount}
                    </p>
                )}

                <form onSubmit={submit} className="mt-5 space-y-6">
                    <section className="rounded-2xl border border-gray-200 bg-white p-4">
                        <Label>Amount to send (GHS)</Label>
                        <Input
                            className="mt-2"
                            inputMode="decimal"
                            value={form.data.ghs_amount}
                            onChange={(e) => form.setData('ghs_amount', e.target.value)}
                        />
                        {quote && (
                            <dl className="mt-4 space-y-1.5 text-sm">
                                <div className="flex justify-between text-gray-600">
                                    <dt>Exchange rate</dt>
                                    <dd>1 RMB = GH₵{config.rate?.ghs_per_rmb.toFixed(4)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>RMB value</dt>
                                    <dd className="font-semibold">¥{quote.rmb.toFixed(2)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Transfer fee</dt>
                                    <dd>{formatPrice(quote.fee)}</dd>
                                </div>
                                <div className="flex justify-between border-t pt-2 font-bold">
                                    <dt>Total payment</dt>
                                    <dd>{formatPrice(quote.total)}</dd>
                                </div>
                            </dl>
                        )}
                    </section>

                    <section className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Alipay recipient</h2>
                        {recipientFields.map(renderField)}
                    </section>

                    <section className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Pay GHS to CityShop</h2>
                        <div className="space-y-2">
                            {config.payment_methods.map((item) => (
                                <label
                                    key={item.id}
                                    className={`block cursor-pointer rounded-xl border px-3 py-3 ${
                                        form.data.payment_method_id === String(item.id)
                                            ? 'border-orange-400 bg-orange-50'
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
                        {method?.qr_url && <img src={method.qr_url} alt="Pay QR" className="mt-2 h-40 w-40 rounded-xl object-cover" />}
                        {paymentFields.map(renderField)}
                    </section>

                    <Button disabled={form.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                        {form.processing ? 'Submitting…' : 'Submit transfer'}
                    </Button>
                </form>
            </div>
        </ShopLayout>
    );
}
