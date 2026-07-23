import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import AdminLayout from '@/layouts/admin-layout';
import { momoNetworkMeta } from '@/lib/momo-networks';
import { SharedData } from '@/types';

type Account = {
    type: 'momo' | 'bank';
    label: string;
    account_name: string;
    account_number: string;
    network?: string | null;
    bank_name?: string | null;
};

interface Props {
    settings: {
        enabled: boolean;
        instructions: string;
        accounts: Account[];
    };
}

const emptyAccount = (): Account => ({
    type: 'momo',
    label: 'MTN Mobile Money',
    account_name: 'City Unlock Ventures / Robert Asare',
    account_number: '',
    network: 'mtn',
    bank_name: '',
});

/** Starter receive accounts from CityShop ops — used when none are configured yet. */
const defaultCityShopAccounts = (): Account[] => [
    {
        type: 'momo',
        label: 'MTN Mobile Money',
        account_name: 'City Unlock Ventures / Robert Asare',
        account_number: '0539790093',
        network: 'mtn',
        bank_name: '',
    },
    {
        type: 'momo',
        label: 'Telecel Cash',
        account_name: 'City Unlock Ventures / Robert Asare',
        account_number: '513014',
        network: 'telecel',
        bank_name: '',
    },
    {
        type: 'momo',
        label: 'AirtelTigo Cash',
        account_name: 'City Unlock Ventures / Robert Asare',
        account_number: '0273706541',
        network: 'airteltigo',
        bank_name: '',
    },
];

function normalizeAccount(account: Account): Account {
    const type = account.type === 'bank' ? 'bank' : 'momo';
    const meta = account.network ? momoNetworkMeta(account.network) : undefined;

    return {
        type,
        label: account.label || (type === 'momo' ? (meta?.label ?? 'Mobile Money') : 'Bank transfer'),
        account_name: account.account_name || '',
        account_number: account.account_number || '',
        network: type === 'momo' ? (meta?.id ?? 'mtn') : null,
        bank_name: type === 'bank' ? (account.bank_name || '') : '',
    };
}

export default function ManualFundingSettings({ settings }: Props) {
    const { flash } = usePage<SharedData>().props;

    const form = useForm({
        enabled: settings.enabled,
        instructions: settings.instructions,
        accounts: (settings.accounts.length > 0 ? settings.accounts : defaultCityShopAccounts()).map(normalizeAccount),
    });

    const accounts = form.data.accounts;

    const setAccounts = (next: Account[]) => {
        form.setData('accounts', next.map(normalizeAccount));
    };

    const updateAccount = (index: number, patch: Partial<Account>) => {
        setAccounts(accounts.map((account, i) => (i === index ? { ...account, ...patch } : account)));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.manual-funding.settings.update'), {
            preserveScroll: true,
        });
    };

    const fieldError = (key: string): string | undefined => {
        const errors = form.errors as Record<string, string | undefined>;
        return errors[key];
    };

    return (
        <AdminLayout title="Manual Payment Accounts" active="manual-funding-settings">
            <Head title="Manual Payment Accounts" />

            <div className="mb-4">
                <h1 className="text-lg font-bold text-gray-900">Manual payment receive accounts</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Set the MoMo or bank details users see when they top up by manual transfer (large amounts). After they
                    send money and upload proof, you approve it under Manual Top-ups.
                </p>
            </div>

            {flash.success && (
                <div className="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            {flash.error && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {flash.error}
                </div>
            )}

            {Object.keys(form.errors).length > 0 && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Could not save — check the highlighted fields below.
                </div>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <label className="flex items-center gap-2 text-sm font-medium text-gray-900">
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                            className="rounded border-gray-300"
                        />
                        Enable manual top-up for buyers and sellers
                    </label>
                    <div className="mt-4">
                        <Label>Instructions shown to users</Label>
                        <textarea
                            value={form.data.instructions}
                            onChange={(e) => form.setData('instructions', e.target.value)}
                            rows={3}
                            className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Send payment to one of the accounts below, then submit your proof and MoMo/bank reference."
                        />
                        <InputError message={form.errors.instructions} />
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-900">Receive accounts</h2>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setAccounts([...accounts, emptyAccount()])}
                            disabled={accounts.length >= 10}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add account
                        </Button>
                    </div>

                    {accounts.map((account, index) => (
                        <div key={index} className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div className="mb-3 flex items-center justify-between">
                                <p className="text-sm font-medium text-gray-800">Account {index + 1}</p>
                                {accounts.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => setAccounts(accounts.filter((_, i) => i !== index))}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label>Type</Label>
                                    <select
                                        value={account.type}
                                        onChange={(e) =>
                                            updateAccount(index, {
                                                type: e.target.value as 'momo' | 'bank',
                                                label:
                                                    e.target.value === 'momo'
                                                        ? account.label || 'CITY SHOP MOMO'
                                                        : account.label || 'Bank transfer',
                                                network: e.target.value === 'momo' ? account.network || 'mtn' : null,
                                            })
                                        }
                                        className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        <option value="momo">Mobile Money</option>
                                        <option value="bank">Bank</option>
                                    </select>
                                    <InputError message={fieldError(`accounts.${index}.type`)} />
                                </div>
                                <div>
                                    <Label>Label</Label>
                                    <Input
                                        value={account.label}
                                        onChange={(e) => updateAccount(index, { label: e.target.value })}
                                        className="mt-1"
                                        required
                                    />
                                    <InputError message={fieldError(`accounts.${index}.label`)} />
                                </div>

                                {account.type === 'momo' && (
                                    <div className="sm:col-span-2">
                                        <MomoNetworkPicker
                                            value={account.network || 'mtn'}
                                            onChange={(network) => updateAccount(index, { network })}
                                            label="Mobile money network"
                                            hint="Pick the network for this receive number — MTN Mobile Money is recommended."
                                        />
                                        <InputError message={fieldError(`accounts.${index}.network`)} />
                                    </div>
                                )}

                                {account.type === 'bank' && (
                                    <div>
                                        <Label>Bank name</Label>
                                        <Input
                                            value={account.bank_name ?? ''}
                                            onChange={(e) => updateAccount(index, { bank_name: e.target.value })}
                                            className="mt-1"
                                        />
                                        <InputError message={fieldError(`accounts.${index}.bank_name`)} />
                                    </div>
                                )}
                                <div>
                                    <Label>Account name</Label>
                                    <Input
                                        value={account.account_name}
                                        onChange={(e) => updateAccount(index, { account_name: e.target.value })}
                                        className="mt-1"
                                        required
                                    />
                                    <InputError message={fieldError(`accounts.${index}.account_name`)} />
                                </div>
                                <div>
                                    <Label>Account / MoMo number</Label>
                                    <Input
                                        value={account.account_number}
                                        onChange={(e) => updateAccount(index, { account_number: e.target.value })}
                                        className="mt-1"
                                        required
                                    />
                                    <InputError message={fieldError(`accounts.${index}.account_number`)} />
                                </div>
                            </div>
                        </div>
                    ))}
                    <InputError message={form.errors.accounts as string | undefined} />
                </div>

                <Button type="submit" disabled={form.processing} className="bg-blue-600 hover:bg-blue-700">
                    {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Save settings
                </Button>
            </form>

            <p className="mt-6 text-sm text-gray-500">
                After saving, review submissions at{' '}
                <Link href={route('admin.manual-top-ups.index')} className="text-blue-600 hover:underline">
                    Manual Top-ups
                </Link>
                .
            </p>
        </AdminLayout>
    );
}
