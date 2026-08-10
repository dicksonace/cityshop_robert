import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

type BankTier = { min: string; max: string; fee: string };

interface Props {
    settings: {
        enabled: boolean;
        amount: number;
        applies_to: 'bank' | 'momo' | 'all' | 'none';
        bank_tiers?: { min: number; max: number | null; fee: number }[];
    };
    autoPaystack: {
        enabled: boolean;
        fee_percent: number;
    };
}

function tiersFromSettings(settings: Props['settings']): BankTier[] {
    const rows = settings.bank_tiers?.length
        ? settings.bank_tiers
        : [
              { min: 10, max: 1000, fee: 10 },
              { min: 1001, max: 25000, fee: 20 },
          ];
    return rows.map((t) => ({
        min: String(t.min ?? 0),
        max: t.max == null ? '' : String(t.max),
        fee: String(t.fee ?? 0),
    }));
}

export default function WithdrawalFeeSettings({ settings, autoPaystack }: Props) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({
        enabled: settings.enabled,
        amount: String(settings.amount ?? 10),
        applies_to: settings.applies_to ?? 'bank',
        bank_tiers: tiersFromSettings(settings),
        auto_paystack_enabled: autoPaystack?.enabled ?? false,
        auto_paystack_fee_percent: String(autoPaystack?.fee_percent ?? 2),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            bank_tiers: data.bank_tiers.map((t) => ({
                min: Number(t.min) || 0,
                max: t.max.trim() === '' ? null : Number(t.max),
                fee: Number(t.fee) || 0,
            })),
        }));
        form.post(route('admin.withdrawal-fees.settings.update'), { preserveScroll: true });
    };

    const updateTier = (index: number, key: keyof BankTier, value: string) => {
        const next = form.data.bank_tiers.map((row, i) => (i === index ? { ...row, [key]: value } : row));
        form.setData('bank_tiers', next);
    };

    return (
        <AdminLayout title="Withdrawal fees" active="withdrawal-fees">
            <Head title="Withdrawal fees" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">Withdrawal settings</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Control Paystack auto-payouts and bank fee bands when auto payout is off.
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

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="space-y-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                        <label className="flex items-start gap-3">
                            <input
                                type="checkbox"
                                checked={form.data.auto_paystack_enabled}
                                onChange={(e) => form.setData('auto_paystack_enabled', e.target.checked)}
                                className="mt-1 h-4 w-4 rounded border-gray-300 text-emerald-600"
                            />
                            <span>
                                <span className="block text-sm font-semibold text-gray-900">
                                    Enable Paystack auto withdrawal
                                </span>
                                <span className="mt-0.5 block text-xs text-gray-600">
                                    Buyer and seller withdrawals go out via Paystack without admin approval. Turn off to
                                    keep the manual review queue.
                                </span>
                            </span>
                        </label>
                        <InputError message={form.errors.auto_paystack_enabled} />

                        <div>
                            <Label>Auto withdrawal fee (%)</Label>
                            <Input
                                type="number"
                                min="0"
                                max="25"
                                step="0.01"
                                value={form.data.auto_paystack_fee_percent}
                                onChange={(e) => form.setData('auto_paystack_fee_percent', e.target.value)}
                                className="mt-1"
                                required
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Charged on the withdrawal amount when auto Paystack is on (default 2%).
                            </p>
                            <InputError message={form.errors.auto_paystack_fee_percent} />
                        </div>
                    </div>

                    <div className="border-t border-gray-100 pt-4">
                        <p className="mb-3 text-sm font-semibold text-gray-900">Fees when auto payout is off</p>
                    </div>

                    <label className="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300 text-orange-600"
                        />
                        <span className="text-sm font-semibold text-gray-900">Enable withdrawal fees</span>
                    </label>
                    <InputError message={form.errors.enabled} />

                    <div>
                        <Label>Apply fees to</Label>
                        <select
                            value={form.data.applies_to}
                            onChange={(e) => form.setData('applies_to', e.target.value as Props['settings']['applies_to'])}
                            className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                        >
                            <option value="bank">Bank withdrawals only</option>
                            <option value="momo">Mobile Money only</option>
                            <option value="all">All withdrawals</option>
                            <option value="none">None (disable by channel)</option>
                        </select>
                        <InputError message={form.errors.applies_to} />
                    </div>

                    <div className="space-y-3 rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                        <div>
                            <p className="text-sm font-semibold text-gray-900">Bank fee bands</p>
                            <p className="mt-0.5 text-xs text-gray-600">
                                Example: GH₵10–1,000 → fee GH₵10 · GH₵10,000–25,000 → fee GH₵20. Amounts between bands
                                keep the lower band fee.
                            </p>
                        </div>

                        {form.data.bank_tiers.map((tier, index) => (
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
                                        disabled={form.data.bank_tiers.length <= 1}
                                        onClick={() =>
                                            form.setData(
                                                'bank_tiers',
                                                form.data.bank_tiers.filter((_, i) => i !== index),
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
                                form.setData('bank_tiers', [
                                    ...form.data.bank_tiers,
                                    { min: '', max: '', fee: '' },
                                ])
                            }
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add band
                        </Button>
                        <InputError message={form.errors.bank_tiers} />
                    </div>

                    <div>
                        <Label>Fallback / MoMo fee (GH₵)</Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            className="mt-1"
                            required
                        />
                        <p className="mt-1 text-xs text-gray-500">
                            Used for MoMo (when MoMo fees apply) or if a bank amount has no matching band.
                        </p>
                        <InputError message={form.errors.amount} />
                    </div>

                    <Button type="submit" disabled={form.processing} className="bg-orange-500 hover:bg-orange-600">
                        {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                        Save withdrawal settings
                    </Button>
                </form>
            </div>
        </AdminLayout>
    );
}
