import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PanelLayout from '@/layouts/panel-layout';
import {
    formatPrice,
    formatWalletTransactionType,
    Paginated,
    Wallet,
    WalletTransaction,
} from '@/types/marketplace';

interface WalletProps {
    wallet: Wallet;
    transactions: Paginated<WalletTransaction>;
}

const nav = [
    { label: 'Dashboard', href: route('seller.dashboard') },
    { label: 'Products', href: route('seller.products.index') },
    { label: 'Orders', href: route('seller.orders.index') },
    { label: 'Messages', href: route('chat.index') },
    { label: 'Wallet', href: route('seller.wallet'), active: true },
];

function formatDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function SellerWallet({ wallet, transactions }: WalletProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        momo_number: '',
        account_name: '',
        network: 'mtn',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('seller.wallet.withdraw'), { onSuccess: () => reset() });
    };

    return (
        <PanelLayout title="Wallet" nav={nav}>
            <Head title="Wallet" />
            <div className="grid gap-6 lg:grid-cols-2">
                <div className="grid gap-4 sm:grid-cols-2">
                    {[
                        { label: 'Available', value: wallet.available_balance, color: 'text-green-600' },
                        { label: 'Pending', value: wallet.pending_balance, color: 'text-yellow-600' },
                        { label: 'Total Earnings', value: wallet.total_earnings, color: 'text-blue-600' },
                        { label: 'Withdrawn', value: wallet.withdrawn_amount, color: 'text-gray-600' },
                    ].map((card) => (
                        <div key={card.label} className="rounded-xl bg-white p-5 shadow-sm">
                            <p className="text-sm text-gray-500">{card.label}</p>
                            <p className={`mt-1 text-2xl font-bold ${card.color}`}>{formatPrice(card.value)}</p>
                        </div>
                    ))}
                </div>

                <form onSubmit={submit} className="rounded-xl bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Request Withdrawal</h3>
                    <div className="mt-4 space-y-3">
                        <div>
                            <Label>Amount (GH₵)</Label>
                            <Input type="number" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} required className="mt-1" />
                            <InputError message={errors.amount} />
                        </div>
                        <div>
                            <Label>MoMo Number</Label>
                            <Input value={data.momo_number} onChange={(e) => setData('momo_number', e.target.value)} required className="mt-1" />
                            <InputError message={errors.momo_number} />
                        </div>
                        <div>
                            <Label>Account Name</Label>
                            <Input value={data.account_name} onChange={(e) => setData('account_name', e.target.value)} required className="mt-1" />
                        </div>
                        <div>
                            <Label>Network</Label>
                            <select value={data.network} onChange={(e) => setData('network', e.target.value)} className="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                <option value="mtn">MTN</option>
                                <option value="telecel">Telecel</option>
                                <option value="airteltigo">AirtelTigo</option>
                            </select>
                        </div>
                        <Button type="submit" disabled={processing} className="w-full bg-orange-500 hover:bg-orange-600">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Request Payout
                        </Button>
                    </div>
                </form>
            </div>

            <div className="mt-8 rounded-xl bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Transaction History</h3>
                {transactions.data.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">No transactions yet. Sales and withdrawals will appear here.</p>
                ) : (
                    <>
                        <div className="mt-4 divide-y">
                            {transactions.data.map((tx) => {
                                const isCredit = tx.amount > 0;

                                return (
                                    <div key={tx.id} className="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                                    {formatWalletTransactionType(tx.type)}
                                                </span>
                                                {tx.reference && (
                                                    <span className="text-xs text-gray-400">{tx.reference}</span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-gray-600">{tx.description}</p>
                                            <p className="text-xs text-gray-400">{formatDate(tx.created_at)}</p>
                                        </div>
                                        <p className={`shrink-0 text-sm font-semibold sm:text-base ${isCredit ? 'text-green-600' : 'text-red-600'}`}>
                                            {isCredit ? '+' : ''}{formatPrice(tx.amount)}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>

                        {transactions.last_page > 1 && (
                            <div className="mt-6 flex flex-wrap gap-2">
                                {transactions.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url ?? '#'}
                                        preserveScroll
                                        className={`rounded-md px-3 py-1.5 text-sm ${
                                            link.active
                                                ? 'bg-orange-500 text-white'
                                                : link.url
                                                  ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                                  : 'cursor-not-allowed bg-gray-50 text-gray-400'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </PanelLayout>
    );
}
