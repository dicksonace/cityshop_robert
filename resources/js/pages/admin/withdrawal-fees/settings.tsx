import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

interface Props {
    settings: {
        enabled: boolean;
        amount: number;
        applies_to: 'bank' | 'momo' | 'all' | 'none';
    };
    autoPaystack: {
        enabled: boolean;
        fee_percent: number;
    };
}

export default function WithdrawalFeeSettings({ settings, autoPaystack }: Props) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({
        enabled: settings.enabled,
        amount: String(settings.amount ?? 10),
        applies_to: settings.applies_to ?? 'bank',
        auto_paystack_enabled: autoPaystack?.enabled ?? false,
        auto_paystack_fee_percent: String(autoPaystack?.fee_percent ?? 2),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.withdrawal-fees.settings.update'), { preserveScroll: true });
    };

    return (
        <AdminLayout title="Withdrawal fees" active="withdrawal-fees">
            <Head title="Withdrawal fees" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">Withdrawal settings</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Control Paystack auto-payouts and fallback flat fees when auto payout is off.
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
                        <p className="mb-3 text-sm font-semibold text-gray-900">Flat fee (when auto payout is off)</p>
                    </div>

                    <label className="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300 text-orange-600"
                        />
                        <span className="text-sm font-semibold text-gray-900">Enable flat withdrawal fee</span>
                    </label>
                    <InputError message={form.errors.enabled} />

                    <div>
                        <Label>Fee amount (GH₵) — cap per transaction</Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            className="mt-1"
                            required
                        />
                        <p className="mt-1 text-xs text-gray-500">Used only when Paystack auto withdrawal is disabled.</p>
                        <InputError message={form.errors.amount} />
                    </div>

                    <div>
                        <Label>Apply flat fee to</Label>
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

                    <Button type="submit" disabled={form.processing} className="bg-orange-500 hover:bg-orange-600">
                        {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                        Save withdrawal settings
                    </Button>
                </form>
            </div>
        </AdminLayout>
    );
}
