import { router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export interface JunipayStatus {
    source: 'none' | 'env' | 'admin';
    configured: boolean;
    collections_enabled: boolean;
    payouts_enabled: boolean;
    auto_payout: boolean;
    auto_payout_bank: boolean;
    auto_payout_other_momo: boolean;
    sandbox: boolean;
    collections_available: boolean;
    payouts_available: boolean;
    client_id: string;
    admin_private_key_set: boolean;
    callback_url: string;
}

type Flag = 'collections_enabled' | 'payouts_enabled' | 'auto_payout' | 'auto_payout_bank' | 'auto_payout_other_momo' | 'sandbox';

const flags: { key: Flag; label: string; hint: string }[] = [
    { key: 'collections_enabled', label: 'Collections', hint: 'Buyers can pay by Mobile Money prompt (checkout, wallet and GSM recharge, scan to pay).' },
    { key: 'payouts_enabled', label: 'Payouts', hint: 'Show “Send via Junipay” on withdrawals.' },
    { key: 'auto_payout', label: 'Pay withdrawals automatically', hint: 'New withdrawals are sent through Junipay straight away, instead of Paystack.' },
    { key: 'auto_payout_bank', label: 'Pay bank withdrawals automatically', hint: 'Turn off to pay bank withdrawals manually. MoMo withdrawals are not affected.' },
    { key: 'auto_payout_other_momo', label: 'Pay Vodafone and AirtelTigo automatically', hint: 'Turn off to pay Vodafone (Telecel) and AirtelTigo manually. MTN stays automatic.' },
    { key: 'sandbox', label: 'Sandbox mode', hint: 'Use sandbox.junipayments.com for testing. Turn off for real money.' },
];

export default function JunipaySettings({ status }: { status?: JunipayStatus }) {
    const keysForm = useForm({ client_id: '', private_key: '' });
    const verifyForm = useForm({});
    const clearForm = useForm({});

    const toggle = (key: Flag, value: boolean) => {
        router.post(route('admin.junipay.settings.update'), { [key]: value }, { preserveScroll: true });
    };

    return (
        <div className="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div>
                <h2 className="text-base font-bold text-gray-900">Junipay (Mobile Money collections and payouts)</h2>
                <p className="mt-1 text-sm text-gray-500">
                    Needs Junipay Direct API approval. Use the Client ID from Junipay → Getting Started, and the RSA private key that
                    matches the public key you gave Junipay. Callback URL:{' '}
                    <span className="font-mono text-xs">{status?.callback_url ?? 'https://cityunlock.net/webhooks/junipay'}</span>
                </p>
            </div>

            <div
                className={`rounded-xl border px-4 py-3 text-sm ${
                    status?.configured
                        ? status.sandbox
                            ? 'border-amber-200 bg-amber-50 text-amber-950'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-950'
                        : 'border-red-200 bg-red-50 text-red-900'
                }`}
            >
                <p className="font-bold">
                    {status?.configured
                        ? `Keys are set${status.sandbox ? ' (sandbox)' : ''}`
                        : 'No Junipay keys yet — Junipay is hidden from buyers and admins'}
                </p>
                {status?.client_id ? <p className="mt-2 font-mono text-xs">Client ID: {status.client_id}</p> : null}
                {status?.admin_private_key_set ? <p className="font-mono text-xs">Private key: saved (encrypted)</p> : null}
            </div>

            <div className="space-y-2">
                {flags.map((flag) => (
                    <label key={flag.key} className="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={Boolean(status?.[flag.key])}
                            onChange={(e) => toggle(flag.key, e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-gray-300 text-orange-600"
                        />
                        <span>
                            <span className="block text-sm font-semibold text-gray-900">{flag.label}</span>
                            <span className="block text-xs text-gray-500">{flag.hint}</span>
                        </span>
                    </label>
                ))}
            </div>

            <div>
                <Label>Client ID</Label>
                <Input
                    value={keysForm.data.client_id}
                    onChange={(e) => keysForm.setData('client_id', e.target.value)}
                    className="mt-1 font-mono text-sm"
                    placeholder={status?.client_id || 'Fu812152'}
                    autoComplete="off"
                />
                <InputError message={keysForm.errors.client_id} />
            </div>
            <div>
                <Label>RSA private key</Label>
                <textarea
                    value={keysForm.data.private_key}
                    onChange={(e) => keysForm.setData('private_key', e.target.value)}
                    rows={5}
                    className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs"
                    placeholder={status?.admin_private_key_set ? 'Saved — paste a new key to replace it' : '-----BEGIN PRIVATE KEY-----'}
                    autoComplete="off"
                    spellCheck={false}
                />
                <InputError message={keysForm.errors.private_key} />
            </div>

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    disabled={keysForm.processing}
                    className="bg-orange-500 hover:bg-orange-600"
                    onClick={() =>
                        keysForm.post(route('admin.junipay.keys.update'), {
                            preserveScroll: true,
                            onSuccess: () => keysForm.reset(),
                        })
                    }
                >
                    {keysForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Save and test keys
                </Button>
                <Button
                    type="button"
                    className="bg-emerald-600 hover:bg-emerald-700"
                    disabled={verifyForm.processing || !status?.configured}
                    onClick={() => verifyForm.post(route('admin.junipay.keys.verify'), { preserveScroll: true })}
                >
                    {verifyForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Test Junipay keys
                </Button>
                {(status?.source === 'admin' || status?.admin_private_key_set) && (
                    <Button
                        type="button"
                        variant="outline"
                        className="text-red-600"
                        disabled={clearForm.processing}
                        onClick={() => clearForm.post(route('admin.junipay.keys.clear'), { preserveScroll: true })}
                    >
                        Clear saved keys
                    </Button>
                )}
            </div>
        </div>
    );
}
