import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, RefreshCw, Wallet as WalletIcon } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ManualTopUpPrompt from '@/components/wallet/manual-top-up-prompt';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import WithdrawalHighlight from '@/components/wallet/withdrawal-highlight';
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
    manualTopUpEnabled?: boolean;
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

export default function BuyerWallet({ wallet, transactions, paystackConfigured, manualTopUpEnabled }: BuyerWalletProps) {
    const [refreshing, setRefreshing] = useState(false);
    const addFundsForm = useForm({ amount: '', method: 'momo' });
    const withdrawForm = useForm({
        amount: '',
        momo_number: '',
        account_name: '',
        network: 'mtn',
    });

    const refreshBalance = () => {
        setRefreshing(true);
        router.reload({
            only: ['wallet', 'transactions'],
            onFinish: () => setRefreshing(false),
        });
    };

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
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-sm text-white/70">Available balance</p>
                            <p className="mt-1 text-4xl font-bold">{formatPrice(wallet.available_balance)}</p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={refreshBalance}
                            disabled={refreshing}
                            className="shrink-0 border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                        >
                            <RefreshCw className={`mr-1.5 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    <p className="mt-2 text-xs text-white/60">Use your balance at checkout or withdraw to MoMo. Refunds are credited here.</p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <form onSubmit={submitAddFunds} className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h3 className="font-semibold text-gray-900">Add Funds</h3>
                        <p className="mt-1 text-sm text-gray-500">Top up via Paystack (MoMo or card).</p>
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
                            {manualTopUpEnabled && (
                                <ManualTopUpPrompt href={route('wallet.manual-top-up')} />
                            )}
                        </div>
                    </form>

                    <WithdrawalHighlight
                        title="Withdraw to MoMo"
                        subtitle="Pick your mobile money network first, then enter your MoMo details."
                    >
                        <form onSubmit={submitWithdraw} className="space-y-4">
                            <MomoNetworkPicker
                                value={withdrawForm.data.network}
                                onChange={(network) => withdrawForm.setData('network', network)}
                                hint="Step 1 — choose MTN MoMo, Telecel, or AirtelTigo."
                            />
                            <div>
                                <Label>MoMo number</Label>
                                <Input
                                    value={withdrawForm.data.momo_number}
                                    onChange={(e) => withdrawForm.setData('momo_number', e.target.value)}
                                    required
                                    className="mt-1"
                                    placeholder="0XX XXX XXXX"
                                />
                                <InputError message={withdrawForm.errors.momo_number} />
                            </div>
                            <div>
                                <Label>Account name</Label>
                                <Input
                                    value={withdrawForm.data.account_name}
                                    onChange={(e) => withdrawForm.setData('account_name', e.target.value)}
                                    required
                                    className="mt-1"
                                    placeholder="Name on MoMo account"
                                />
                                <InputError message={withdrawForm.errors.account_name} />
                            </div>
                            <div>
                                <Label>Amount (GH₵)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    max={wallet.available_balance}
                                    value={withdrawForm.data.amount}
                                    onChange={(e) => withdrawForm.setData('amount', e.target.value)}
                                    required
                                    className="mt-1 text-lg"
                                />
                                <InputError message={withdrawForm.errors.amount} />
                                <button
                                    type="button"
                                    className="mt-2 text-sm font-medium text-orange-600 hover:underline"
                                    onClick={() => withdrawForm.setData('amount', String(wallet.available_balance))}
                                >
                                    Withdraw all ({formatPrice(wallet.available_balance)})
                                </button>
                            </div>
                            <Button type="submit" disabled={withdrawForm.processing} className="w-full bg-orange-500 py-6 text-base hover:bg-orange-600">
                                {withdrawForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Request withdrawal to MoMo
                            </Button>
                        </form>
                    </WithdrawalHighlight>
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
