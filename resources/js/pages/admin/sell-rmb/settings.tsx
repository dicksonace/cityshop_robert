import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import LivePauseControl from '@/components/admin/live-pause-control';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

type Rate = {
    id: number;
    usd_per_rmb: number;
    ghs_per_usd: number;
    ghs_per_rmb?: number;
    fee_mode: string;
    fee_value: number;
    min_rmb: number;
    max_rmb: number;
    daily_max_rmb: number | null;
    monthly_max_rmb: number | null;
    max_per_day: number | null;
    approval_above_rmb: number | null;
    active: boolean;
    effective_from: string | null;
    effective_to: string | null;
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
    proof_required: boolean;
    active: boolean;
};

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
    active: boolean;
};

interface Props {
    settings: { enabled: boolean; instructions: string | null; receive_instructions: string | null };
    currentRate: Rate | null;
    rates: Rate[];
    methods: Method[];
    fields: Field[];
    fieldTypes: string[];
    open: boolean;
}

export default function SellRmbSettings({
    settings,
    currentRate,
    rates,
    methods,
    fields,
    fieldTypes,
    open,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [tab, setTab] = useState<'rate' | 'methods' | 'fields'>('rate');

    const settingsForm = useForm({
        enabled: settings.enabled,
        instructions: settings.instructions ?? '',
        receive_instructions: settings.receive_instructions ?? '',
    });

    const rateForm = useForm({
        ghs_per_rmb: String(
            currentRate?.ghs_per_rmb ??
                (currentRate ? currentRate.usd_per_rmb * currentRate.ghs_per_usd : 1.712),
        ),
        fee_mode: currentRate?.fee_mode ?? 'flat',
        fee_value: String(currentRate?.fee_value ?? '0'),
        min_rmb: String(currentRate?.min_rmb ?? '100'),
        max_rmb: String(currentRate?.max_rmb ?? '50000'),
        daily_max_rmb: String(currentRate?.daily_max_rmb ?? ''),
        monthly_max_rmb: String(currentRate?.monthly_max_rmb ?? ''),
        max_per_day: String(currentRate?.max_per_day ?? ''),
        approval_above_rmb: String(currentRate?.approval_above_rmb ?? ''),
    });

    const methodForm = useForm({
        name: 'CityShop Alipay',
        type: 'alipay',
        account_name: '',
        account_number: '',
        network: '',
        instructions: 'Send the exact RMB amount and upload your payment screenshot.',
        proof_required: true,
        active: true,
        qr: null as File | null,
    });

    const fieldForm = useForm({
        group: 'payment',
        type: 'text',
        label: '',
        name: '',
        placeholder: '',
        help_text: '',
        required: true,
        options: '',
        active: true,
    });

    const saveSettings: FormEventHandler = (e) => {
        e.preventDefault();
        settingsForm.post(route('admin.sell-rmb.settings.update'), { preserveScroll: true });
    };

    const publishRate: FormEventHandler = (e) => {
        e.preventDefault();
        rateForm.post(route('admin.sell-rmb.rates.store'), { preserveScroll: true });
    };

    return (
        <AdminLayout title="Sell RMB settings" active="sell-rmb-settings">
            <Head title="Sell RMB settings" />
            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">Sell RMB settings</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Like rmb-wallet: buyers send RMB to your Alipay QR (no RMB wallet). Requests go straight to
                            Processing for admin.
                            {open ? ' Service is live.' : ' Not live yet — enable, publish a rate, and add Alipay QR.'}
                        </p>
                    </div>
                    <Link
                        href={route('admin.sell-rmb.index')}
                        className="text-sm font-semibold text-orange-600 hover:underline"
                    >
                        View pending sell requests →
                    </Link>
                </div>

                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}

                <LivePauseControl
                    title="RMB → GHS (Sell RMB)"
                    description="Pause to stop new Sell RMB requests. Rates and receive methods stay saved."
                    enabled={settings.enabled}
                    open={open}
                    instructions={settings.instructions ?? ''}
                    updateUrl={route('admin.sell-rmb.settings.update')}
                    extra={{ receive_instructions: settings.receive_instructions ?? '' }}
                />

                <div className="flex gap-2">
                    {(['rate', 'methods', 'fields'] as const).map((id) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setTab(id)}
                            className={`rounded-full px-3 py-1.5 text-sm font-semibold ${
                                tab === id ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            {id === 'rate' ? 'Rate & limits' : id === 'methods' ? 'RMB receive methods' : 'Form fields'}
                        </button>
                    ))}
                </div>

                {tab === 'rate' && (
                    <>
                        <form onSubmit={saveSettings} className="space-y-4 rounded-2xl border border-gray-200 bg-white p-5">
                            <div>
                                <Label>Buyer instructions</Label>
                                <textarea
                                    className="mt-1 min-h-24 w-full rounded-md border px-3 py-2 text-sm"
                                    value={settingsForm.data.instructions}
                                    onChange={(e) => settingsForm.setData('instructions', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label>Receive instructions</Label>
                                <textarea
                                    className="mt-1 min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                    value={settingsForm.data.receive_instructions}
                                    onChange={(e) => settingsForm.setData('receive_instructions', e.target.value)}
                                />
                            </div>
                            <Button disabled={settingsForm.processing}>Save instructions</Button>
                        </form>

                        <form onSubmit={publishRate} className="space-y-4 rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 className="font-bold">Publish buying rate</h2>
                            <p className="text-xs text-gray-500">
                                Same as rmb-wallet: set how much GHS you pay for 1 RMB. Open requests keep their old rate.
                            </p>
                            <div className="flex flex-wrap items-end gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                                <div className="text-center">
                                    <p className="text-2xl font-black text-gray-900">1</p>
                                    <p className="text-xs font-bold uppercase tracking-wide text-gray-500">RMB</p>
                                </div>
                                <p className="pb-2 text-lg font-bold text-gray-400">=</p>
                                <div className="min-w-[9rem] flex-1">
                                    <Label>GHS per 1 RMB</Label>
                                    <Input
                                        className="mt-1 text-lg font-bold"
                                        value={rateForm.data.ghs_per_rmb}
                                        onChange={(e) => rateForm.setData('ghs_per_rmb', e.target.value)}
                                        placeholder="1.712"
                                    />
                                    <InputError message={rateForm.errors.ghs_per_rmb} />
                                </div>
                                <p className="pb-2 text-sm font-bold text-gray-600">GHS</p>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>Fee mode</Label>
                                    <select
                                        className="mt-1 h-10 w-full rounded-md border px-3 text-sm"
                                        value={rateForm.data.fee_mode}
                                        onChange={(e) => rateForm.setData('fee_mode', e.target.value)}
                                    >
                                        <option value="flat">Fixed (USD bridge)</option>
                                        <option value="percent">Percent</option>
                                    </select>
                                </div>
                                <div>
                                    <Label>Fee value</Label>
                                    <Input
                                        className="mt-1"
                                        value={rateForm.data.fee_value}
                                        onChange={(e) => rateForm.setData('fee_value', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>Min RMB</Label>
                                    <Input value={rateForm.data.min_rmb} onChange={(e) => rateForm.setData('min_rmb', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Max RMB</Label>
                                    <Input value={rateForm.data.max_rmb} onChange={(e) => rateForm.setData('max_rmb', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Daily max RMB / user</Label>
                                    <Input value={rateForm.data.daily_max_rmb} onChange={(e) => rateForm.setData('daily_max_rmb', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Monthly max RMB / user</Label>
                                    <Input
                                        value={rateForm.data.monthly_max_rmb}
                                        onChange={(e) => rateForm.setData('monthly_max_rmb', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label>Max requests / day</Label>
                                    <Input value={rateForm.data.max_per_day} onChange={(e) => rateForm.setData('max_per_day', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Manual approval above RMB</Label>
                                    <Input
                                        value={rateForm.data.approval_above_rmb}
                                        onChange={(e) => rateForm.setData('approval_above_rmb', e.target.value)}
                                    />
                                </div>
                            </div>
                            <Button disabled={rateForm.processing} className="bg-orange-500 hover:bg-orange-600">
                                Save RMB sell rate
                            </Button>
                        </form>

                        <div className="rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 className="font-bold">Rate history</h2>
                            <table className="mt-3 w-full text-left text-sm">
                                <thead>
                                    <tr className="text-gray-500">
                                        <th className="py-2">1 RMB = GHS</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rates.map((rate) => (
                                        <tr key={rate.id} className="border-t">
                                            <td className="py-2 font-semibold">
                                                GH₵{(rate.ghs_per_rmb ?? rate.usd_per_rmb * rate.ghs_per_usd).toFixed(4)}
                                            </td>
                                            <td>{rate.effective_from ? new Date(rate.effective_from).toLocaleString('en-GH') : '—'}</td>
                                            <td>{rate.effective_to ? new Date(rate.effective_to).toLocaleString('en-GH') : 'Current'}</td>
                                            <td>{rate.active ? 'Active' : 'Expired'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {tab === 'methods' && (
                    <>
                        <form
                            className="space-y-3 rounded-2xl border border-gray-200 bg-white p-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                methodForm.post(route('admin.sell-rmb.methods.store'), { forceFormData: true, preserveScroll: true });
                            }}
                        >
                            <h2 className="font-bold">Add RMB receive method</h2>
                            <Input placeholder="Name" value={methodForm.data.name} onChange={(e) => methodForm.setData('name', e.target.value)} />
                            <select
                                className="h-10 w-full rounded-md border px-3 text-sm"
                                value={methodForm.data.type}
                                onChange={(e) => methodForm.setData('type', e.target.value)}
                            >
                                <option value="alipay">Alipay</option>
                                <option value="wechat">WeChat</option>
                                <option value="bank">Bank</option>
                                <option value="other">Other</option>
                            </select>
                            <Input
                                placeholder="Account name"
                                value={methodForm.data.account_name}
                                onChange={(e) => methodForm.setData('account_name', e.target.value)}
                            />
                            <Input
                                placeholder="Account number / ID"
                                value={methodForm.data.account_number}
                                onChange={(e) => methodForm.setData('account_number', e.target.value)}
                            />
                            <Input
                                placeholder="Network"
                                value={methodForm.data.network}
                                onChange={(e) => methodForm.setData('network', e.target.value)}
                            />
                            <textarea
                                className="min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                placeholder="Instructions"
                                value={methodForm.data.instructions}
                                onChange={(e) => methodForm.setData('instructions', e.target.value)}
                            />
                            <input type="file" accept="image/*" onChange={(e) => methodForm.setData('qr', e.target.files?.[0] ?? null)} />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={methodForm.data.proof_required}
                                    onChange={(e) => methodForm.setData('proof_required', e.target.checked)}
                                />
                                Proof required
                            </label>
                            <Button>Add method</Button>
                        </form>

                        <div className="space-y-3">
                            {methods.map((method) => (
                                <div key={method.id} className="rounded-2xl border border-gray-200 bg-white p-4">
                                    <p className="font-bold">
                                        {method.name} {method.active ? '' : '(inactive)'}
                                    </p>
                                    <p className="text-sm text-gray-600">
                                        {method.type} · {method.account_name} · {method.account_number}
                                    </p>
                                    {!method.active ? null : (
                                        <form
                                            className="mt-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                router.post(route('admin.sell-rmb.methods.destroy', method.id), {}, { preserveScroll: true });
                                            }}
                                        >
                                            <Button variant="outline" size="sm">
                                                Deactivate
                                            </Button>
                                        </form>
                                    )}
                                </div>
                            ))}
                        </div>
                    </>
                )}

                {tab === 'fields' && (
                    <>
                        <form
                            className="space-y-3 rounded-2xl border border-gray-200 bg-white p-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                fieldForm.transform((data) => ({
                                    ...data,
                                    options: data.options
                                        ? data.options.split(',').map((s) => s.trim()).filter(Boolean)
                                        : [],
                                }));
                                fieldForm.post(route('admin.sell-rmb.fields.store'), { preserveScroll: true });
                            }}
                        >
                            <h2 className="font-bold">Add field</h2>
                            <select
                                className="h-10 w-full rounded-md border px-3 text-sm"
                                value={fieldForm.data.group}
                                onChange={(e) => fieldForm.setData('group', e.target.value)}
                            >
                                <option value="payment">RMB payment proof</option>
                                <option value="payout">Payout details</option>
                                <option value="recipient">Recipient</option>
                            </select>
                            <select
                                className="h-10 w-full rounded-md border px-3 text-sm"
                                value={fieldForm.data.type}
                                onChange={(e) => fieldForm.setData('type', e.target.value)}
                            >
                                {fieldTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                            <Input placeholder="Label" value={fieldForm.data.label} onChange={(e) => fieldForm.setData('label', e.target.value)} />
                            <Input
                                placeholder="Placeholder"
                                value={fieldForm.data.placeholder}
                                onChange={(e) => fieldForm.setData('placeholder', e.target.value)}
                            />
                            <Input
                                placeholder="Help text"
                                value={fieldForm.data.help_text}
                                onChange={(e) => fieldForm.setData('help_text', e.target.value)}
                            />
                            <Input
                                placeholder="Dropdown options, comma separated"
                                value={fieldForm.data.options}
                                onChange={(e) => fieldForm.setData('options', e.target.value)}
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={fieldForm.data.required}
                                    onChange={(e) => fieldForm.setData('required', e.target.checked)}
                                />
                                Required
                            </label>
                            <Button>Add field</Button>
                        </form>

                        <div className="space-y-2">
                            {fields.map((field) => (
                                <div
                                    key={field.id}
                                    className="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-semibold">
                                            {field.label} <span className="text-gray-400">({field.type})</span>
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {field.group} · {field.required ? 'required' : 'optional'} · {field.active ? 'active' : 'off'}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
