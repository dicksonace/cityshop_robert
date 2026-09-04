import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

type FeeTier = { min: string; max: string; fee: string };

interface Props {
    settings: {
        enabled: boolean;
        mode: 'percent' | 'flat' | 'tiers';
        percent: number;
        flat: number;
        tiers?: { min: number; max: number | null; fee: number }[];
    };
    paymentsLocked: boolean;
    flutterwaveLocked?: boolean;
}

function tiersFromSettings(settings: Props['settings']): FeeTier[] {
    const rows = settings.tiers?.length
        ? settings.tiers
        : [
              { min: 1, max: 99.99, fee: 1 },
              { min: 100, max: 999.99, fee: 2 },
              { min: 1000, max: null, fee: 5 },
          ];

    return rows.map((t) => ({
        min: String(t.min ?? 0),
        max: t.max == null ? '' : String(t.max),
        fee: String(t.fee ?? 0),
    }));
}

export default function PaystackFeeSettings({ settings, paymentsLocked = false, flutterwaveLocked = false }: Props) {
    const { flash } = usePage<SharedData>().props;
    const lockForm = useForm({ locked: paymentsLocked });
    const flwLockForm = useForm({ locked: flutterwaveLocked });
    const form = useForm({
        enabled: settings.enabled,
        mode: settings.mode ?? 'percent',
        percent: String(settings.percent ?? 1.95),
        flat: String(settings.flat ?? 0),
        tiers: tiersFromSettings(settings),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            tiers: data.tiers.map((t) => ({
                min: Number(t.min) || 0,
                max: t.max.trim() === '' ? null : Number(t.max),
                fee: Number(t.fee) || 0,
            })),
        }));
        form.post(route('admin.paystack-fees.settings.update'), { preserveScroll: true });
    };

    const updateTier = (index: number, key: keyof FeeTier, value: string) => {
        form.setData(
            'tiers',
            form.data.tiers.map((row, i) => (i === index ? { ...row, [key]: value } : row)),
        );
    };

    return (
        <AdminLayout title="Paystack fees" active="paystack-fees">
            <Head title="Paystack fees" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">Paystack fees</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Added on wallet top-up and checkout when buyers pay via Paystack or Flutterwave. Use one fee, or flat fees by amount range.
                    </p>
                </div>

                {(flash?.success || flash?.error) && (
                    <div
                        className={`rounded-xl border px-4 py-3 text-sm ${
                            flash.success
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-red-200 bg-red-50 text-red-800'
                        }`}
                    >
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div
                    className={`space-y-4 rounded-2xl border p-6 shadow-sm ${
                        lockForm.data.locked
                            ? 'border-amber-200 bg-amber-50 text-amber-950'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-950'
                    }`}
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-base font-bold">Paystack payments</h2>
                            <p className="mt-1 text-sm opacity-80">
                                Disable when you want buyers and sellers to use manual MoMo / bank top-up and
                                checkout only. Existing Paystack payments already in progress can still finish.
                            </p>
                        </div>
                        <span className="rounded-full bg-white/80 px-3 py-1 text-xs font-extrabold uppercase tracking-wide ring-1 ring-black/5">
                            {lockForm.data.locked ? 'Disabled' : 'Enabled'}
                        </span>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={lockForm.processing || !lockForm.data.locked}
                            onClick={() => {
                                lockForm.setData('locked', false);
                                lockForm.post(route('admin.paystack-fees.lock.update'), { preserveScroll: true });
                            }}
                            className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition ${
                                !lockForm.data.locked
                                    ? 'bg-emerald-600 text-white shadow-sm'
                                    : 'bg-white text-emerald-800 ring-1 ring-emerald-200 hover:bg-emerald-50'
                            } disabled:cursor-not-allowed disabled:opacity-70`}
                        >
                            {lockForm.processing && lockForm.data.locked ? (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            ) : null}
                            Enable
                        </button>
                        <button
                            type="button"
                            disabled={lockForm.processing || lockForm.data.locked}
                            onClick={() => {
                                lockForm.setData('locked', true);
                                lockForm.post(route('admin.paystack-fees.lock.update'), { preserveScroll: true });
                            }}
                            className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition ${
                                lockForm.data.locked
                                    ? 'bg-amber-600 text-white shadow-sm'
                                    : 'bg-white text-amber-900 ring-1 ring-amber-200 hover:bg-amber-50'
                            } disabled:cursor-not-allowed disabled:opacity-70`}
                        >
                            {lockForm.processing && !lockForm.data.locked ? (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            ) : null}
                            Disable
                        </button>
                    </div>
                    <InputError message={lockForm.errors.locked} />
                    <p className="text-xs opacity-75">
                        Keep manual wallet funding accounts on under Wallet funding so users still have a way to
                        pay when Paystack is disabled.
                    </p>
                </div>

                <div
                    className={`space-y-4 rounded-2xl border p-6 shadow-sm ${
                        flwLockForm.data.locked
                            ? 'border-amber-200 bg-amber-50 text-amber-950'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-950'
                    }`}
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-base font-bold">Flutterwave payments</h2>
                            <p className="mt-1 text-sm opacity-80">
                                Second collection gateway for checkout and wallet top-up. Withdrawals stay on
                                Paystack. Uses the same collection fees as Paystack above.
                            </p>
                        </div>
                        <span className="rounded-full bg-white/80 px-3 py-1 text-xs font-extrabold uppercase tracking-wide ring-1 ring-black/5">
                            {flwLockForm.data.locked ? 'Disabled' : 'Enabled'}
                        </span>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={flwLockForm.processing || !flwLockForm.data.locked}
                            onClick={() => {
                                flwLockForm.setData('locked', false);
                                flwLockForm.post(route('admin.flutterwave.lock.update'), { preserveScroll: true });
                            }}
                            className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition ${
                                !flwLockForm.data.locked
                                    ? 'bg-emerald-600 text-white shadow-sm'
                                    : 'bg-white text-emerald-800 ring-1 ring-emerald-200 hover:bg-emerald-50'
                            } disabled:cursor-not-allowed disabled:opacity-70`}
                        >
                            {flwLockForm.processing && flwLockForm.data.locked ? (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            ) : null}
                            Enable
                        </button>
                        <button
                            type="button"
                            disabled={flwLockForm.processing || flwLockForm.data.locked}
                            onClick={() => {
                                flwLockForm.setData('locked', true);
                                flwLockForm.post(route('admin.flutterwave.lock.update'), { preserveScroll: true });
                            }}
                            className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition ${
                                flwLockForm.data.locked
                                    ? 'bg-amber-600 text-white shadow-sm'
                                    : 'bg-white text-amber-900 ring-1 ring-amber-200 hover:bg-amber-50'
                            } disabled:cursor-not-allowed disabled:opacity-70`}
                        >
                            {flwLockForm.processing && !flwLockForm.data.locked ? (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            ) : null}
                            Disable
                        </button>
                    </div>
                    <InputError message={flwLockForm.errors.locked} />
                </div>

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <label className="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300 text-orange-600"
                        />
                        <span className="text-sm font-semibold text-gray-900">Enable Paystack collection fees</span>
                    </label>
                    <InputError message={form.errors.enabled} />

                    <div>
                        <Label>Fee type</Label>
                        <select
                            value={form.data.mode}
                            onChange={(e) => form.setData('mode', e.target.value as Props['settings']['mode'])}
                            className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                        >
                            <option value="percent">One percent fee (covers Paystack cut)</option>
                            <option value="flat">One flat fee (GH₵)</option>
                            <option value="tiers">Flat fee by amount range</option>
                        </select>
                        <InputError message={form.errors.mode} />
                    </div>

                    {form.data.mode === 'percent' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Percent (%)</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    max="25"
                                    step="0.01"
                                    value={form.data.percent}
                                    onChange={(e) => form.setData('percent', e.target.value)}
                                    className="mt-1"
                                    required
                                />
                                <p className="mt-1 text-xs text-gray-500">Ghana Paystack local rate is usually 1.95%.</p>
                                <InputError message={form.errors.percent} />
                            </div>
                            <div>
                                <Label>Extra flat (GH₵)</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.flat}
                                    onChange={(e) => form.setData('flat', e.target.value)}
                                    className="mt-1"
                                />
                                <p className="mt-1 text-xs text-gray-500">Optional extra cedis on top of the percent.</p>
                                <InputError message={form.errors.flat} />
                            </div>
                        </div>
                    )}

                    {form.data.mode === 'flat' && (
                        <div>
                            <Label>Flat fee (GH₵)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.flat}
                                onChange={(e) => form.setData('flat', e.target.value)}
                                className="mt-1"
                                required
                            />
                            <p className="mt-1 text-xs text-gray-500">Same fee on every Paystack payment, e.g. GH₵1.</p>
                            <InputError message={form.errors.flat} />
                        </div>
                    )}

                    {form.data.mode === 'tiers' && (
                        <div className="space-y-3 rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                            <div>
                                <p className="text-sm font-semibold text-gray-900">Amount ranges</p>
                                <p className="mt-0.5 text-xs text-gray-600">
                                    Example: GH₵1–99.99 → GH₵1 · GH₵100–999.99 → GH₵2 · GH₵1,000+ → GH₵5.
                                </p>
                            </div>

                            {form.data.tiers.map((tier, index) => (
                                <div key={index} className="grid gap-2 rounded-lg border border-orange-100 bg-white p-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
                                    <div>
                                        <Label className="text-xs">From (GH₵)</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={tier.min}
                                            onChange={(e) => updateTier(index, 'min', e.target.value)}
                                            className="mt-1"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-xs">To (GH₵)</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={tier.max}
                                            onChange={(e) => updateTier(index, 'max', e.target.value)}
                                            className="mt-1"
                                            placeholder="No max"
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-xs">Fee (GH₵)</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={tier.fee}
                                            onChange={(e) => updateTier(index, 'fee', e.target.value)}
                                            className="mt-1"
                                            required
                                        />
                                    </div>
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full text-red-600"
                                            disabled={form.data.tiers.length <= 1}
                                            onClick={() =>
                                                form.setData(
                                                    'tiers',
                                                    form.data.tiers.filter((_, i) => i !== index),
                                                )
                                            }
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}

                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('tiers', [...form.data.tiers, { min: '', max: '', fee: '' }])
                                }
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add band
                            </Button>
                            <InputError message={form.errors.tiers} />
                        </div>
                    )}

                    <Button type="submit" disabled={form.processing} className="bg-orange-500 hover:bg-orange-600">
                        {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                        Save Paystack fees
                    </Button>
                </form>
            </div>
        </AdminLayout>
    );
}
