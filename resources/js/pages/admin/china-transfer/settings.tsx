import { Head, router, useForm, usePage } from '@inertiajs/react';
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
    ghs_per_rmb: number;
    rmb_per_ghs: number;
    fee_mode: string;
    fee_value: number;
    min_ghs: number;
    max_ghs: number;
    daily_max_ghs: number | null;
    monthly_max_ghs: number | null;
    max_per_day: number | null;
    approval_above_ghs: number | null;
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
    bank_name: string | null;
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
    settings: {
        enabled: boolean;
        instructions: string | null;
        max_converts_per_day?: number;
        max_rmb_out_per_day?: number | null;
        max_rmb_out_per_month?: number | null;
    };
    currentRate: Rate | null;
    rates: Rate[];
    methods: Method[];
    fields: Field[];
    fieldTypes: string[];
    open: boolean;
}

export default function ChinaTransferSettings({
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
        max_converts_per_day: String(settings.max_converts_per_day ?? 30),
        max_rmb_out_per_day: String(settings.max_rmb_out_per_day ?? ''),
        max_rmb_out_per_month: String(settings.max_rmb_out_per_month ?? ''),
    });

    const rateForm = useForm({
        // Admin enters like rmb-wallet: 1 GHS = X RMB (stored as ghs_per_rmb = 1/X).
        rmb_per_ghs: String(
            currentRate?.rmb_per_ghs ??
                (currentRate?.ghs_per_rmb && currentRate.ghs_per_rmb > 0
                    ? 1 / currentRate.ghs_per_rmb
                    : '0.559'),
        ),
        fee_mode: currentRate?.fee_mode ?? 'flat',
        fee_value: String(currentRate?.fee_value ?? '0'),
        min_ghs: String(currentRate?.min_ghs ?? '50'),
        max_ghs: String(currentRate?.max_ghs ?? '50000'),
        daily_max_ghs: String(currentRate?.daily_max_ghs ?? ''),
        monthly_max_ghs: String(currentRate?.monthly_max_ghs ?? ''),
        max_per_day: String(currentRate?.max_per_day ?? ''),
        approval_above_ghs: String(currentRate?.approval_above_ghs ?? '10000'),
    });

    const methodForm = useForm({
        name: 'MTN MoMo',
        type: 'momo',
        account_name: '',
        account_number: '',
        bank_name: '',
        network: 'mtn',
        instructions: 'Send the exact GHS amount and use your transfer reference.',
        proof_required: true,
        active: true,
        qr: null as File | null,
    });

    const fieldForm = useForm({
        group: 'recipient',
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
        settingsForm.post(route('admin.china-transfer.settings.update'), { preserveScroll: true });
    };

    const publishRate: FormEventHandler = (e) => {
        e.preventDefault();
        rateForm.post(route('admin.china-transfer.rates.store'), { preserveScroll: true });
    };

    const rmbPerGhs = Number(rateForm.data.rmb_per_ghs);
    const impliedGhsPerRmb = rmbPerGhs > 0 ? 1 / rmbPerGhs : 0;

    return (
        <AdminLayout title="China Transfer settings" active="china-transfer-settings">
            <Head title="China Transfer settings" />
            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">China Transfer settings</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Alipay only. Buyers send GHS to your accounts, then you send RMB and upload proof.
                        {open ? ' Service is live.' : ' Not live yet — enable, publish a rate, and add a payment method.'}
                    </p>
                </div>

                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}

                <LivePauseControl
                    title="GHS → RMB (Transfer to China)"
                    description="Pause to stop new Transfer to China requests. Rates and payment methods stay saved."
                    enabled={settings.enabled}
                    open={open}
                    instructions={settings.instructions ?? ''}
                    updateUrl={route('admin.china-transfer.settings.update')}
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
                            {id === 'rate' ? 'Rate & limits' : id === 'methods' ? 'GHS payment methods' : 'Form fields'}
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
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <Label>Max converts / day</Label>
                                    <Input
                                        className="mt-1"
                                        value={settingsForm.data.max_converts_per_day}
                                        onChange={(e) => settingsForm.setData('max_converts_per_day', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label>Max RMB out / day (wallet)</Label>
                                    <Input
                                        className="mt-1"
                                        value={settingsForm.data.max_rmb_out_per_day}
                                        onChange={(e) => settingsForm.setData('max_rmb_out_per_day', e.target.value)}
                                        placeholder="Unlimited"
                                    />
                                </div>
                                <div>
                                    <Label>Max RMB out / month</Label>
                                    <Input
                                        className="mt-1"
                                        value={settingsForm.data.max_rmb_out_per_month}
                                        onChange={(e) => settingsForm.setData('max_rmb_out_per_month', e.target.value)}
                                        placeholder="Unlimited"
                                    />
                                </div>
                            </div>
                            <Button disabled={settingsForm.processing}>Save instructions & limits</Button>
                        </form>

                        <form onSubmit={publishRate} className="space-y-4 rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 className="font-bold">Publish new rate</h2>
                            <p className="text-xs text-gray-500">
                                Same as rmb-wallet: set GHS → RMB for Buy RMB. Open transfers keep the old rate.
                            </p>
                            <div>
                                <Label>GHS to RMB Rate</Label>
                                <div className="mt-1 flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-semibold text-gray-700">1 GHS =</span>
                                    <Input
                                        className="max-w-[10rem] text-lg font-bold"
                                        value={rateForm.data.rmb_per_ghs}
                                        onChange={(e) => rateForm.setData('rmb_per_ghs', e.target.value)}
                                        placeholder="0.559"
                                    />
                                    <span className="text-sm font-semibold text-gray-700">RMB</span>
                                </div>
                                <p className="mt-1 text-xs text-gray-500">
                                    Accepts any decimal precision (e.g. 0.565, 0.5802). Implies 1 RMB ≈ GH₵
                                    {impliedGhsPerRmb > 0 ? impliedGhsPerRmb.toFixed(4) : '—'}
                                </p>
                                <InputError message={rateForm.errors.rmb_per_ghs ?? rateForm.errors.ghs_per_rmb} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>Fee mode</Label>
                                    <select
                                        className="mt-1 h-10 w-full rounded-md border px-3 text-sm"
                                        value={rateForm.data.fee_mode}
                                        onChange={(e) => rateForm.setData('fee_mode', e.target.value)}
                                    >
                                        <option value="flat">Fixed GHS</option>
                                        <option value="percent">Percent of GHS sent</option>
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
                                    <Label>Min GHS</Label>
                                    <Input value={rateForm.data.min_ghs} onChange={(e) => rateForm.setData('min_ghs', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Max GHS</Label>
                                    <Input value={rateForm.data.max_ghs} onChange={(e) => rateForm.setData('max_ghs', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Daily max GHS / user</Label>
                                    <Input value={rateForm.data.daily_max_ghs} onChange={(e) => rateForm.setData('daily_max_ghs', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Monthly max GHS / user</Label>
                                    <Input value={rateForm.data.monthly_max_ghs} onChange={(e) => rateForm.setData('monthly_max_ghs', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Max transfers / day</Label>
                                    <Input value={rateForm.data.max_per_day} onChange={(e) => rateForm.setData('max_per_day', e.target.value)} />
                                </div>
                                <div>
                                    <Label>Manual approval above GHS</Label>
                                    <Input
                                        value={rateForm.data.approval_above_ghs}
                                        onChange={(e) => rateForm.setData('approval_above_ghs', e.target.value)}
                                    />
                                </div>
                            </div>
                            <Button disabled={rateForm.processing} className="bg-orange-500 hover:bg-orange-600">
                                Publish rate
                            </Button>
                        </form>

                        <div className="rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 className="font-bold">Rate history</h2>
                            <table className="mt-3 w-full text-left text-sm">
                                <thead>
                                    <tr className="text-gray-500">
                                        <th className="py-2">1 GHS = RMB</th>
                                        <th>1 RMB = GHS</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rates.map((rate) => (
                                        <tr key={rate.id} className="border-t">
                                            <td className="py-2 font-semibold">¥{rate.rmb_per_ghs.toFixed(4)}</td>
                                            <td className="py-2">GH₵{rate.ghs_per_rmb.toFixed(4)}</td>
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
                                methodForm.post(route('admin.china-transfer.methods.store'), { forceFormData: true, preserveScroll: true });
                            }}
                        >
                            <h2 className="font-bold">Add GHS payment method</h2>
                            <Input placeholder="Name" value={methodForm.data.name} onChange={(e) => methodForm.setData('name', e.target.value)} />
                            <select
                                className="h-10 w-full rounded-md border px-3 text-sm"
                                value={methodForm.data.type}
                                onChange={(e) => methodForm.setData('type', e.target.value)}
                            >
                                <option value="momo">Mobile Money</option>
                                <option value="bank">Bank</option>
                                <option value="wallet">Wallet</option>
                                <option value="other">Other</option>
                            </select>
                            <Input placeholder="Account name" value={methodForm.data.account_name} onChange={(e) => methodForm.setData('account_name', e.target.value)} />
                            <Input placeholder="Number" value={methodForm.data.account_number} onChange={(e) => methodForm.setData('account_number', e.target.value)} />
                            <Input placeholder="Bank / network" value={methodForm.data.bank_name} onChange={(e) => methodForm.setData('bank_name', e.target.value)} />
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
                                        {method.account_name} · {method.account_number}
                                    </p>
                                    {!method.active ? null : (
                                        <form
                                            className="mt-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                router.post(route('admin.china-transfer.methods.destroy', method.id), {}, { preserveScroll: true });
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
                                fieldForm.post(route('admin.china-transfer.fields.store'), { preserveScroll: true });
                            }}
                        >
                            <h2 className="font-bold">Add field</h2>
                            <select
                                className="h-10 w-full rounded-md border px-3 text-sm"
                                value={fieldForm.data.group}
                                onChange={(e) => fieldForm.setData('group', e.target.value)}
                            >
                                <option value="recipient">Alipay / recipient</option>
                                <option value="payment">GHS payment proof</option>
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
                            <Input placeholder="Placeholder" value={fieldForm.data.placeholder} onChange={(e) => fieldForm.setData('placeholder', e.target.value)} />
                            <Input placeholder="Help text" value={fieldForm.data.help_text} onChange={(e) => fieldForm.setData('help_text', e.target.value)} />
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
                                <div key={field.id} className="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
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
