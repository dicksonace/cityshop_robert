import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Wallet as WalletIcon } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import {
    formatPrice,
    formatWalletTransactionType,
    Paginated,
    Wallet,
    WalletTransaction,
} from '@/types/marketplace';

interface BuyerWalletProps {
    wallet: Wallet;
    transactions: Paginated<WalletTransaction>;
    paystackConfigured: boolean;
    paystackPublicKey: string;
}

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

export default function BuyerWallet({ wallet, transactions, paystackConfigured }: BuyerWalletProps) {
    const addFundsForm = useForm({ amount: '', method: 'momo' });
    const withdrawForm = useForm({
        amount: '',
        momo_number: '',
        account_name: '',
        network: 'mtn',
    });

    const submitAddFunds: FormEventHandler = (e) => {
        e.preventDefault();
        addFundsForm.post(route('wallet.add-funds'), { onSuccess: () => addFundsForm.reset() });
    };

    const submitWithdraw: FormEventHandler = (e) => {
        e.preventDefault();
        withdrawForm.post(route('wallet.withdraw'), { onSuccess: () => withdrawForm.reset() });
    };

    return (
        <ShopLayout>
            <Head title="My Wallet" />
            <div className="mx-auto max-w-5xl px-4 py-8">
                <div className="mb-8 flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-white">
                        <WalletIcon className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">My Wallet</h1>
                        <p className="text-sm text-gray-500">Add funds, pay for orders, withdraw, and view refunds.</p>
                    </div>
                </div>

                <div className="mb-8 rounded-2xl bg-gradient-to-r from-slate-900 via-blue-900 to-orange-900 p-6 text-white shadow-lg">
                    <p className="text-sm text-white/70">Available balance</p>
                    <p className="mt-1 text-4xl font-bold">{formatPrice(wallet.available_balance)}</p>
                    <p className="mt-2 text-xs text-white/60">Use your balance at checkout or withdraw to MoMo. Refunds are credited here.</p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <form onSubmit={submitAddFunds} className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Add Funds</h3>
                        <p className="mt-1 text-sm text-gray-500">Top up via Paystack (MoMo or card), pay for orders, or withdraw to MoMo.</p>
                        <div className="mt-4 space-y-3">
                            <div>
                                <Label>Amount (GH₵)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="5"
                                    value={addFundsForm.data.amount}
                                    onChange={(e) => addFundsForm.setData('amount', e.target.value)}
                                    required
                                    className="mt-1"
                                />
                                <InputError message={addFundsForm.errors.amount} />
                            </div>
                            <div>
                                <Label>Payment method</Label>
                                <select
                                    value={addFundsForm.data.method}
                                    onChange={(e) => addFundsForm.setData('method', e.target.value)}
                                    className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                >
                                    <option value="momo">Mobile Money</option>
                                    <option value="card">Card</option>
                                </select>
                            </div>
                            <Button type="submit" disabled={addFundsForm.processing || !paystackConfigured} className="w-full bg-green-600 hover:bg-green-700">
                                {addFundsForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                {paystackConfigured ? 'Pay to Add Funds' : 'Top-up unavailable'}
                            </Button>
                            {!paystackConfigured && (
                                <p className="text-xs text-amber-600">Online top-up requires Paystack to be configured.</p>
                            )}
                        </div>
                    </form>

                    <form onSubmit={submitWithdraw} className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Withdraw Funds</h3>
                        <p className="mt-1 text-sm text-gray-500">Transfer to your MoMo account.</p>
                        <div className="mt-4 space-y-3">
                            <div>
                                <Label>Amount (GH₵)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={withdrawForm.data.amount}
                                    onChange={(e) => withdrawForm.setData('amount', e.target.value)}
                                    required
                                    className="mt-1"
                                />
                                <InputError message={withdrawForm.errors.amount} />
                            </div>
                            <div>
                                <Label>MoMo Number</Label>
                                <Input
                                    value={withdrawForm.data.momo_number}
                                    onChange={(e) => withdrawForm.setData('momo_number', e.target.value)}
                                    required
                                    className="mt-1"
                                />
                                <InputError message={withdrawForm.errors.momo_number} />
                            </div>
                            <div>
                                <Label>Account Name</Label>
                                <Input
                                    value={withdrawForm.data.account_name}
                                    onChange={(e) => withdrawForm.setData('account_name', e.target.value)}
                                    required
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <Label>Network</Label>
                                <select
                                    value={withdrawForm.data.network}
                                    onChange={(e) => withdrawForm.setData('network', e.target.value)}
                                    className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                >
                                    <option value="mtn">MTN</option>
                                    <option value="telecel">Telecel</option>
                                    <option value="airteltigo">AirtelTigo</option>
                                </select>
                            </div>
                            <Button type="submit" disabled={withdrawForm.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                                {withdrawForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Request Withdrawal
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="mt-8 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Transaction History</h3>
                    {transactions.data.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">No transactions yet.</p>
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
                                                    {tx.reference && <span className="text-xs text-gray-400">{tx.reference}</span>}
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
            </div>
        </ShopLayout>
    );
}
