import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Search, Store, User as UserIcon, Wallet, X } from 'lucide-react';
import { FormEvent, FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface WalletUser {
    id: number;
    name: string;
    email: string;
    mobile?: string | null;
    role: 'seller' | 'buyer';
    store_name?: string | null;
    available_balance: number;
}

interface RecentFunding {
    id: number;
    amount: number;
    description: string;
    created_at: string | null;
    user: { id: number; name: string; role: string } | null;
}

interface WalletFundingProps {
    users: Paginated<WalletUser>;
    role: 'all' | 'seller' | 'buyer';
    search?: string | null;
    recentFundings: RecentFunding[];
}

const roleTabs = [
    { key: 'all', label: 'All' },
    { key: 'seller', label: 'Sellers' },
    { key: 'buyer', label: 'Buyers' },
] as const;

export default function AdminWalletFunding({ users, role, search, recentFundings }: WalletFundingProps) {
    const { flash } = usePage<SharedData>().props;
    const [query, setQuery] = useState(search ?? '');
    const [target, setTarget] = useState<WalletUser | null>(null);

    const form = useForm({
        user_id: 0,
        amount: '',
        note: '',
    });

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('admin.wallet-funding.index'),
            { role: role !== 'all' ? role : undefined, search: query || undefined },
            { preserveState: true, replace: true },
        );
    };

    const openFund = (user: WalletUser) => {
        setTarget(user);
        form.setData({ user_id: user.id, amount: '', note: '' });
        form.clearErrors();
    };

    const closeFund = () => {
        setTarget(null);
        form.reset();
    };

    const submitFund: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.wallet-funding.store'), {
            preserveScroll: true,
            onSuccess: () => closeFund(),
        });
    };

    return (
        <AdminLayout title="Add Funds to Wallet" active="wallet-funding">
            <Head title="Add Funds" />

            <div className="mb-4">
                <h1 className="text-lg font-bold text-gray-900">Add funds to wallets</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Manually credit a seller or buyer wallet. The amount is added to their available balance and logged
                    as a transaction.
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

            <div className="mb-4 flex flex-wrap items-center gap-2">
                {roleTabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={route('admin.wallet-funding.index', {
                            role: tab.key !== 'all' ? tab.key : undefined,
                            search: search || undefined,
                        })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium ${
                            role === tab.key
                                ? 'bg-blue-500 text-white'
                                : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>

            <form onSubmit={submitSearch} className="mb-6">
                <div className="relative max-w-md">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by name, email, or phone..."
                        className="pl-9"
                    />
                </div>
            </form>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                        <table className="min-w-[640px] w-full text-sm">
                            <thead className="border-b bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">User</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">Role</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-500">Balance</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-10 text-center text-gray-500">
                                            No users found.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <p className="font-medium text-gray-900">{user.name}</p>
                                                <p className="text-gray-500">{user.email}</p>
                                                {user.store_name && (
                                                    <p className="text-xs text-gray-400">{user.store_name}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        user.role === 'seller'
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-orange-50 text-orange-700'
                                                    }`}
                                                >
                                                    {user.role === 'seller' ? (
                                                        <Store className="h-3 w-3" />
                                                    ) : (
                                                        <UserIcon className="h-3 w-3" />
                                                    )}
                                                    {user.role}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 font-medium text-green-600">
                                                {formatPrice(user.available_balance)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() => openFund(user)}
                                                    className="bg-blue-500 hover:bg-blue-600"
                                                >
                                                    <Wallet className="mr-1 h-4 w-4" />
                                                    Add funds
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {users.last_page > 1 && (
                        <div className="mt-4 flex flex-wrap justify-center gap-2">
                            {users.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`rounded-lg px-3 py-1.5 text-sm ${
                                            link.active
                                                ? 'bg-blue-500 text-white'
                                                : 'bg-white text-gray-600 ring-1 ring-gray-200'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : null,
                            )}
                        </div>
                    )}
                </div>

                <div className="rounded-xl bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-gray-900">Recent admin credits</h2>
                    <p className="mt-1 text-xs text-gray-500">Last 15 manual wallet top-ups.</p>
                    <div className="mt-4 space-y-3">
                        {recentFundings.length === 0 ? (
                            <p className="text-sm text-gray-400">No manual credits yet.</p>
                        ) : (
                            recentFundings.map((tx) => (
                                <div key={tx.id} className="border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-medium text-gray-900">{tx.user?.name ?? 'User'}</p>
                                        <p className="text-sm font-semibold text-green-600">{formatPrice(tx.amount)}</p>
                                    </div>
                                    <p className="text-xs text-gray-500">{tx.description}</p>
                                    {tx.created_at && (
                                        <p className="mt-0.5 text-[11px] text-gray-400">
                                            {new Date(tx.created_at).toLocaleString('en-GH')}
                                        </p>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>

            {target && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                        <div className="flex items-start justify-between">
                            <div>
                                <h3 className="text-lg font-bold text-gray-900">Add funds</h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    {target.name} · <span className="capitalize">{target.role}</span>
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeFund}
                                className="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm">
                            <span className="text-gray-500">Current balance: </span>
                            <span className="font-semibold text-green-600">{formatPrice(target.available_balance)}</span>
                        </div>

                        <form className="mt-4 space-y-4" onSubmit={submitFund}>
                            <div>
                                <Label htmlFor="amount">Amount (GH₵) *</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.5"
                                    value={form.data.amount}
                                    onChange={(e) => form.setData('amount', e.target.value)}
                                    className="mt-1"
                                    placeholder="0.00"
                                    autoFocus
                                />
                                <InputError message={form.errors.amount} />
                            </div>
                            <div>
                                <Label htmlFor="note">Note (optional)</Label>
                                <Input
                                    id="note"
                                    value={form.data.note}
                                    onChange={(e) => form.setData('note', e.target.value)}
                                    className="mt-1"
                                    placeholder="Reason for this credit"
                                />
                                <InputError message={form.errors.note} />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" onClick={closeFund}>
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-blue-500 hover:bg-blue-600"
                                >
                                    {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    Add funds
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
