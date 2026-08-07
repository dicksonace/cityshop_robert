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
}

export default function WithdrawalFeeSettings({ settings }: Props) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({
        enabled: settings.enabled,
        amount: String(settings.amount ?? 10),
        applies_to: settings.applies_to ?? 'bank',
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
                    <h1 className="text-xl font-bold text-gray-900">Withdrawal fee</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Flat fee charged per withdrawal (default GH₵10 for bank payouts). You can change the amount or turn it off anytime.
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
                    <label className="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300 text-orange-600"
                        />
                        <span className="text-sm font-semibold text-gray-900">Enable withdrawal fee</span>
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
                        <p className="mt-1 text-xs text-gray-500">Charged once per withdrawal request. Example: GH₵10.</p>
                        <InputError message={form.errors.amount} />
                    </div>

                    <div>
                        <Label>Apply fee to</Label>
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
                        Save fee settings
                    </Button>
                </form>
            </div>
        </AdminLayout>
    );
}
