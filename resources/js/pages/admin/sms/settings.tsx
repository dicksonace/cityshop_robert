import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

interface Provider {
    id: 'formula_dc' | 'txtconnect';
    label: string;
    configured: boolean;
    sender: string;
}

interface Props {
    settings: {
        driver: 'formula_dc' | 'txtconnect';
        failover: boolean;
        alert_mobile_1?: string;
        alert_mobile_2?: string;
        alert_mobile_3?: string;
        alert_mobile_4?: string;
    };
    providers: Provider[];
}

export default function SmsSettings({ settings, providers }: Props) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({
        driver: settings.driver,
        failover: settings.failover,
        alert_mobile_1: settings.alert_mobile_1 ?? '',
        alert_mobile_2: settings.alert_mobile_2 ?? '',
        alert_mobile_3: settings.alert_mobile_3 ?? '',
        alert_mobile_4: settings.alert_mobile_4 ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.sms.settings.update'), { preserveScroll: true });
    };

    return (
        <AdminLayout title="SMS" active="sms">
            <Head title="SMS platforms" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">SMS platforms</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Switch which provider sends CityShop SMS. If failover is ON and TxtConnect fails
                        (for example CityShop sender ID still pending), Formula DC will still deliver the text.
                        Turn failover OFF to confirm TxtConnect alone.
                    </p>
                </div>

                {flash?.success && (
                    <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{flash.success}</p>
                )}

                <form onSubmit={submit} className="space-y-5 rounded-2xl border border-gray-200 bg-white p-5">
                    <div className="space-y-3">
                        <Label>Active platform</Label>
                        {providers.map((provider) => (
                            <label
                                key={provider.id}
                                className={`flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 ${
                                    form.data.driver === provider.id ? 'border-orange-400 bg-orange-50' : 'border-gray-200'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="driver"
                                    className="mt-1"
                                    checked={form.data.driver === provider.id}
                                    onChange={() => form.setData('driver', provider.id)}
                                />
                                <span>
                                    <span className="block font-semibold text-gray-900">{provider.label}</span>
                                    <span className="mt-0.5 block text-xs text-gray-500">
                                        Sender ID: {provider.sender}
                                        {provider.configured ? ' · key saved' : ' · API key missing'}
                                        {provider.id === 'txtconnect' ? ' · CityShop sender is pending on TxtConnect' : ''}
                                    </span>
                                </span>
                            </label>
                        ))}
                        <InputError message={form.errors.driver} />
                    </div>

                    <label className="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3">
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={form.data.failover}
                            onChange={(e) => form.setData('failover', e.target.checked)}
                        />
                        <span>
                            <span className="block font-semibold text-gray-900">If this platform fails, try the other</span>
                            <span className="mt-0.5 block text-xs text-gray-500">
                                With this ON, choosing TxtConnect can still send via Formula DC when TxtConnect rejects the message.
                            </span>
                        </span>
                    </label>

                    <div className="space-y-3 rounded-xl border border-sky-100 bg-sky-50/50 p-4">
                        <div>
                            <p className="font-semibold text-gray-900">Admin alert numbers</p>
                            <p className="mt-0.5 text-xs text-gray-500">
                                These numbers get SMS when a buyer submits a withdrawal, a pending manual deposit, or a Transfer to China.
                            </p>
                        </div>
                        {(
                            [
                                ['alert_mobile_1', 'Alert number 1'],
                                ['alert_mobile_2', 'Alert number 2'],
                                ['alert_mobile_3', 'Alert number 3'],
                                ['alert_mobile_4', 'Alert number 4'],
                            ] as const
                        ).map(([key, label]) => (
                            <div key={key}>
                                <Label>{label}</Label>
                                <input
                                    type="tel"
                                    value={form.data[key]}
                                    onChange={(e) => form.setData(key, e.target.value)}
                                    placeholder="0XX XXX XXXX"
                                    className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                                />
                                <InputError message={form.errors[key]} />
                            </div>
                        ))}
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save SMS platform'}
                    </Button>
                </form>
            </div>
        </AdminLayout>
    );
}
