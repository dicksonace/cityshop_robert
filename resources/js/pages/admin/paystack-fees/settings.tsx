import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

type FeeTier = { min: string; max: string; fee: string };

interface FlutterwaveKeys {
    source: 'none' | 'env' | 'admin' | 'mixed';
    configured: boolean;
    available: boolean;
    is_test: boolean;
    admin_public_set: boolean;
    admin_secret_set: boolean;
    admin_hash_set: boolean;
    env_public_set: boolean;
    env_secret_set: boolean;
    public_key_masked: string;
    secret_key_masked: string;
    webhook_hash_set: boolean;
}

interface Props {
    settings: {
        enabled: boolean;
        mode: 'percent' | 'flat' | 'tiers';
        percent: number;
        flat: number;
        tiers?: { min: number; max: number | null; fee: number }[];
    };
    paymentsLocked: boolean;
    paystackPayments?: {
        locked: boolean;
        checkout_enabled: boolean;
        recharge_enabled: boolean;
        withdrawal_enabled: boolean;
    };
    flutterwaveLocked?: boolean;
    flutterwaveKeys?: FlutterwaveKeys;
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

export default function PaystackFeeSettings({
    settings,
    paymentsLocked = false,
    paystackPayments,
    flutterwaveLocked = false,
    flutterwaveKeys,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const payments = paystackPayments ?? {
        locked: paymentsLocked,
        checkout_enabled: !paymentsLocked,
        recharge_enabled: !paymentsLocked,
        withdrawal_enabled: true,
    };
    const [savingFlag, setSavingFlag] = useState<string | null>(null);
    const flwLockForm = useForm({ locked: flutterwaveLocked });
    const keysForm = useForm({
        public_key: '',
        secret_key: '',
        webhook_hash: '',
        verify: true,
    });
    const verifyForm = useForm({ secret_key: '' });
    const clearKeysForm = useForm({});

    const keysSourceLabel =
        flutterwaveKeys?.source === 'admin'
            ? 'Using keys saved on this page'
            : flutterwaveKeys?.source === 'mixed'
              ? 'Using a mix of this page and server .env'
              : flutterwaveKeys?.source === 'env'
                ? 'Using server .env keys (set keys here to override)'
                : 'No Flutterwave keys yet';
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

    const savePaystackFlag = (
        key: 'checkout_enabled' | 'recharge_enabled' | 'withdrawal_enabled',
        value: boolean,
    ) => {
        setSavingFlag(key);
        router.post(
            route('admin.paystack-fees.lock.update'),
            {
                checkout_enabled: key === 'checkout_enabled' ? value : payments.checkout_enabled,
                recharge_enabled: key === 'recharge_enabled' ? value : payments.recharge_enabled,
                withdrawal_enabled: key === 'withdrawal_enabled' ? value : payments.withdrawal_enabled,
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingFlag(null),
            },
        );
    };

    const updateTier = (index: number, key: keyof FeeTier, value: string) => {
        form.setData(
            'tiers',
            form.data.tiers.map((row, i) => (i === index ? { ...row, [key]: value } : row)),
        );
    };

    return (
        <AdminLayout title="Paystack / Flutterwave" active="paystack-fees">
            <Head title="Paystack fees" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">Paystack / Flutterwave</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Turn Paystack checkout, wallet recharge, and withdrawals on or off separately.
                        New Paystack references start with cityshop-. Flutterwave keys stay below.
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

                <div className="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div>
                        <h2 className="text-base font-bold text-gray-900">Paystack on / off</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Turn checkout, wallet recharge, and withdrawals off independently. Payments already
                            started can still finish. References sent to Paystack look like cityshop-8F3A…
                        </p>
                    </div>

                    {(
                        [
                            {
                                key: 'checkout_enabled' as const,
                                title: 'Checkout',
                                help: 'Show Paystack on web and app order payment.',
                                on: payments.checkout_enabled,
                            },
                            {
                                key: 'recharge_enabled' as const,
                                title: 'Wallet recharge',
                                help: 'Show Paystack on buyer and seller top-up, like RMB wallet deposit.',
                                on: payments.recharge_enabled,
                            },
                            {
                                key: 'withdrawal_enabled' as const,
                                title: 'Withdrawals',
                                help: 'Allow Paystack payouts (including auto withdraw). Manual mark-paid still works.',
                                on: payments.withdrawal_enabled,
                            },
                        ] as const
                    ).map((row) => (
                        <div
                            key={row.key}
                            className={`rounded-xl border px-4 py-3 ${
                                row.on
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                                    : 'border-amber-200 bg-amber-50 text-amber-950'
                            }`}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-bold">{row.title}</p>
                                    <p className="mt-0.5 text-xs opacity-80">{row.help}</p>
                                </div>
                                <span className="rounded-full bg-white/80 px-3 py-1 text-xs font-extrabold uppercase tracking-wide ring-1 ring-black/5">
                                    {row.on ? 'On' : 'Off'}
                                </span>
                            </div>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    disabled={savingFlag !== null || row.on}
                                    onClick={() => savePaystackFlag(row.key, true)}
                                    className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition ${
                                        row.on
                                            ? 'bg-emerald-600 text-white shadow-sm'
                                            : 'bg-white text-emerald-800 ring-1 ring-emerald-200 hover:bg-emerald-50'
                                    } disabled:cursor-not-allowed disabled:opacity-70`}
                                >
                                    {savingFlag === row.key && !row.on ? (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    ) : null}
                                    Enable
                                </button>
                                <button
                                    type="button"
                                    disabled={savingFlag !== null || !row.on}
                                    onClick={() => savePaystackFlag(row.key, false)}
                                    className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition ${
                                        !row.on
                                            ? 'bg-amber-600 text-white shadow-sm'
                                            : 'bg-white text-amber-900 ring-1 ring-amber-200 hover:bg-amber-50'
                                    } disabled:cursor-not-allowed disabled:opacity-70`}
                                >
                                    {savingFlag === row.key && row.on ? (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    ) : null}
                                    Disable
                                </button>
                            </div>
                        </div>
                    ))}
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
                                Primary alternative for checkout and wallet top-up when Paystack is off.
                                Paste live API keys in the box below. Paystack withdrawals use the switch
                                above. Uses the same collection fees as above.
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
                    <p className="text-xs opacity-75">
                        Enable is not enough. Flutterwave must accept a live public + secret key below,
                        or deposits start then bounce back with “Invalid authorization key”.
                    </p>
                </div>

                <div className="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div>
                        <h2 className="text-base font-bold text-gray-900">Flutterwave API keys</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Copy from{' '}
                            <a
                                href="https://app.flutterwave.com/dashboard/settings/apis"
                                target="_blank"
                                rel="noreferrer"
                                className="font-semibold text-orange-600 underline"
                            >
                                Flutterwave dashboard
                            </a>
                            . Leave a field blank to keep the current value. Webhook URL:{' '}
                            <span className="font-mono text-xs">https://cityunlock.net/webhooks/flutterwave</span>
                        </p>
                    </div>

                    <div
                        className={`rounded-xl border px-4 py-3 text-sm ${
                            flutterwaveKeys?.configured
                                ? flutterwaveKeys.is_test
                                    ? 'border-amber-200 bg-amber-50 text-amber-950'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-950'
                                : 'border-red-200 bg-red-50 text-red-900'
                        }`}
                    >
                        <p className="font-bold">
                            {flutterwaveKeys?.configured
                                ? flutterwaveKeys.available
                                    ? 'Keys are set and Flutterwave is enabled'
                                    : 'Keys are set, but Flutterwave is disabled above'
                                : 'No usable Flutterwave keys — deposits will fail'}
                        </p>
                        <p className="mt-1 text-xs opacity-80">{keysSourceLabel}</p>
                        {flutterwaveKeys?.public_key_masked ? (
                            <p className="mt-2 font-mono text-xs">Public: {flutterwaveKeys.public_key_masked}</p>
                        ) : null}
                        {flutterwaveKeys?.secret_key_masked ? (
                            <p className="font-mono text-xs">Secret: {flutterwaveKeys.secret_key_masked}</p>
                        ) : null}
                        {flutterwaveKeys?.is_test ? (
                            <p className="mt-2 text-xs font-semibold">
                                These look like TEST keys. Live app deposits need FLWPUBK- / FLWSECK- (not
                                _TEST).
                            </p>
                        ) : null}
                    </div>

                    <div>
                        <Label>Public key</Label>
                        <Input
                            value={keysForm.data.public_key}
                            onChange={(e) => keysForm.setData('public_key', e.target.value)}
                            className="mt-1 font-mono text-sm"
                            placeholder={flutterwaveKeys?.public_key_masked || 'FLWPUBK-…-X'}
                            autoComplete="off"
                        />
                        <InputError message={keysForm.errors.public_key} />
                    </div>
                    <div>
                        <Label>Secret key</Label>
                        <Input
                            type="password"
                            value={keysForm.data.secret_key}
                            onChange={(e) => keysForm.setData('secret_key', e.target.value)}
                            className="mt-1 font-mono text-sm"
                            placeholder={flutterwaveKeys?.secret_key_masked || 'FLWSECK-…-X'}
                            autoComplete="new-password"
                        />
                        <InputError message={keysForm.errors.secret_key} />
                    </div>
                    <div>
                        <Label>Webhook secret hash (optional)</Label>
                        <Input
                            value={keysForm.data.webhook_hash}
                            onChange={(e) => keysForm.setData('webhook_hash', e.target.value)}
                            className="mt-1 font-mono text-sm"
                            placeholder={flutterwaveKeys?.webhook_hash_set ? 'Saved — leave blank to keep' : 'Same hash as Flutterwave webhook settings'}
                            autoComplete="off"
                        />
                        <InputError message={keysForm.errors.webhook_hash} />
                    </div>

                    <label className="flex items-center gap-3 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={keysForm.data.verify}
                            onChange={(e) => keysForm.setData('verify', e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300 text-orange-600"
                        />
                        Check the key with Flutterwave when saving
                    </label>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            disabled={keysForm.processing}
                            className="bg-orange-500 hover:bg-orange-600"
                            onClick={() => keysForm.post(route('admin.flutterwave.keys.update'), { preserveScroll: true })}
                        >
                            {keysForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Save Flutterwave keys
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={verifyForm.processing}
                            onClick={() => {
                                verifyForm.setData('secret_key', keysForm.data.secret_key);
                                verifyForm.post(route('admin.flutterwave.keys.verify'), { preserveScroll: true });
                            }}
                        >
                            {verifyForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Verify keys
                        </Button>
                        {(flutterwaveKeys?.admin_public_set || flutterwaveKeys?.admin_secret_set) && (
                            <Button
                                type="button"
                                variant="outline"
                                className="text-red-600"
                                disabled={clearKeysForm.processing}
                                onClick={() =>
                                    clearKeysForm.post(route('admin.flutterwave.keys.clear'), { preserveScroll: true })
                                }
                            >
                                {clearKeysForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Clear saved keys
                            </Button>
                        )}
                    </div>
                    <InputError message={verifyForm.errors.secret_key} />
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
