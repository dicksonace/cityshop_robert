import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

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
    network: string | null;
    instructions: string | null;
    qr_url: string | null;
};

type Config = {
    enabled: boolean;
    instructions: string | null;
    receive_instructions: string | null;
    rate: {
        usd_per_rmb: number;
        ghs_per_usd: number;
        ghs_per_rmb?: number;
        min_rmb: number;
        max_rmb: number;
        fee_mode: 'flat' | 'percent';
        fee_value: number;
    } | null;
    receive_methods: Method[];
    fields: Field[];
};

interface Props {
    config: Config;
}

function fieldKey(id: number) {
    return `fields.${id}`;
}

function formatGhs(n: number) {
    return `GH₵${n.toFixed(2)}`;
}

export default function SellRmbCreate({ config }: Props) {
    const { flash } = usePage<SharedData>().props;
    const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
    const initialRmb = params.get('rmb_amount') || String(config.rate?.min_rmb ?? '');

    const form = useForm({
        rmb_amount: initialRmb,
        payout_currency: 'ghs' as const,
        receive_method_id: String(config.receive_methods[0]?.id ?? ''),
        fields: {} as Record<string, string>,
        files: {} as Record<string, File | null>,
    });

    const quote = useMemo(() => {
        const amount = Number(form.data.rmb_amount);
        if (!config.rate || !Number.isFinite(amount) || amount <= 0) return null;
        const ghsPerRmb =
            config.rate.ghs_per_rmb ?? config.rate.usd_per_rmb * config.rate.ghs_per_usd;
        const usdGross = amount * config.rate.usd_per_rmb;
        const fee =
            config.rate.fee_mode === 'percent'
                ? (usdGross * config.rate.fee_value) / 100
                : config.rate.fee_value;
        const ghsGross = amount * ghsPerRmb;
        const feeGhs = usdGross > 0 ? ghsGross * (fee / usdGross) : 0;
        const ghsPayout = ghsGross - feeGhs;
        return { ghsPerRmb, feeGhs, ghsPayout };
    }, [form.data.rmb_amount, config.rate]);

    const method = config.receive_methods.find((m) => String(m.id) === form.data.receive_method_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const payload: Record<string, unknown> = {
            rmb_amount: form.data.rmb_amount,
            payout_currency: 'ghs',
            receive_method_id: form.data.receive_method_id,
        };
        Object.entries(form.data.fields).forEach(([id, value]) => {
            payload[`fields[${id}]`] = value;
        });
        Object.entries(form.data.files).forEach(([id, file]) => {
            if (file) payload[`files[${id}]`] = file;
        });
        form.transform(() => payload);
        form.post(route('wallet.sell-rmb.store'), { forceFormData: true });
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
                    type={
                        field.type === 'number'
                            ? 'number'
                            : field.type === 'email'
                              ? 'email'
                              : field.type === 'date'
                                ? 'date'
                                : 'text'
                    }
                    placeholder={field.placeholder ?? ''}
                    value={form.data.fields[field.id] ?? ''}
                    onChange={(e) => setField(field.id, e.target.value)}
                />
                {field.help_text && <p className="text-xs text-gray-500">{field.help_text}</p>}
                <InputError message={error} />
            </div>
        );
    };

    const paymentFields = config.fields.filter((f) => f.group === 'payment');
    const payoutFields = config.fields.filter(
        (f) => f.group === 'payout' || f.group === 'recipient',
    );

    return (
        <ShopLayout hideFlash>
            <Head title="New Sell RMB" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <Link href={route('wallet.sell-rmb.index')} className="text-sm font-semibold text-emerald-700">
                    ← Rates
                </Link>
                <h1 className="mt-3 text-2xl font-black text-gray-900">Sell RMB details</h1>
                {(flash.error || form.errors.rmb_amount) && (
                    <p className="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
                        {flash.error || form.errors.rmb_amount}
                    </p>
                )}

                <form onSubmit={submit} className="mt-5 space-y-6">
                    <section className="rounded-2xl border border-gray-200 bg-white p-4">
                        <Label>RMB amount</Label>
                        <Input
                            className="mt-2"
                            inputMode="decimal"
                            value={form.data.rmb_amount}
                            onChange={(e) => form.setData('rmb_amount', e.target.value)}
                        />
                        {quote && (
                            <dl className="mt-4 space-y-1.5 text-sm">
                                <div className="flex justify-between text-gray-600">
                                    <dt>Buying rate</dt>
                                    <dd>1 RMB = GH₵{quote.ghsPerRmb.toFixed(4)}</dd>
                                </div>
                                <div className="flex justify-between font-semibold text-gray-900">
                                    <dt>You receive (GHS)</dt>
                                    <dd>{formatGhs(quote.ghsPayout)}</dd>
                                </div>
                            </dl>
                        )}
                        <p className="mt-4 text-sm text-gray-600">
                            Send RMB to our Alipay QR (no RMB wallet). After you submit with proof, the
                            request goes to Processing for admin payout.
                        </p>
                        <InputError message={form.errors.payout_currency} />
                    </section>

                    <section className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Send RMB to CityShop</h2>
                        {config.receive_instructions && (
                            <p className="text-sm text-gray-600">{config.receive_instructions}</p>
                        )}
                        <div className="space-y-2">
                            {config.receive_methods.map((item) => (
                                <label
                                    key={item.id}
                                    className={`block cursor-pointer rounded-xl border px-3 py-3 ${
                                        form.data.receive_method_id === String(item.id)
                                            ? 'border-emerald-400 bg-emerald-50'
                                            : 'border-gray-200'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        className="mr-2"
                                        checked={form.data.receive_method_id === String(item.id)}
                                        onChange={() =>
                                            form.setData('receive_method_id', String(item.id))
                                        }
                                    />
                                    <span className="font-semibold">{item.name}</span>
                                    {(item.account_name || item.account_number) && (
                                        <span className="mt-1 block text-sm text-gray-600">
                                            {[item.type, item.account_name, item.account_number, item.network]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.receive_method_id} />
                        {method?.instructions && (
                            <p className="text-sm text-gray-600">{method.instructions}</p>
                        )}
                        {method?.qr_url && (
                            <img
                                src={method.qr_url}
                                alt="Receive QR"
                                className="mt-2 h-40 w-40 rounded-xl object-cover"
                            />
                        )}
                        {paymentFields.map(renderField)}
                    </section>

                    <section className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold text-gray-900">Your payout details</h2>
                        {payoutFields.map(renderField)}
                    </section>

                    <Button
                        disabled={form.processing}
                        className="w-full bg-emerald-600 hover:bg-emerald-700"
                    >
                        {form.processing ? 'Submitting…' : 'Submit Sell RMB'}
                    </Button>
                </form>
            </div>
        </ShopLayout>
    );
}
