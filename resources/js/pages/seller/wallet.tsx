import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import {
    formatPrice,
    formatWalletTransactionType,
    Paginated,
    Wallet,
    WalletTransaction,
} from '@/types/marketplace';

interface PayoutMethod {
    id: number;
    network: string;
    account_number: string;
    account_name: string;
    is_default: boolean;
}

interface Withdrawal {
    id: number;
    amount: number;
    network: string;
    momo_number: string;
    account_name: string;
    status: string;
    payout_channel?: string | null;
    rejection_reason?: string | null;
    failure_reason?: string | null;
    created_at: string;
    processed_at?: string;
}

interface WalletProps {
    wallet: Wallet;
    transactions: Paginated<WalletTransaction>;
    withdrawals: Paginated<Withdrawal>;
    payoutMethods: PayoutMethod[];
    hasPendingWithdrawal: boolean;
}

const networkLabels: Record<string, string> = {
    mtn: 'MTN Mobile Money',
    telecel: 'Telecel Cash',
    airteltigo: 'AirtelTigo Money',
};

function formatDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GH', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function SellerWallet({ wallet, transactions, withdrawals, payoutMethods, hasPendingWithdrawal }: WalletProps) {
    const [withdrawStep, setWithdrawStep] = useState<'method' | 'amount' | 'review'>('method');
    const [showAddMethod, setShowAddMethod] = useState(payoutMethods.length === 0);

    const methodForm = useForm({
        network: 'mtn',
        account_number: '',
        account_name: '',
        is_default: true,
    });

    const withdrawForm = useForm({
        payout_method_id: payoutMethods.find((m) => m.is_default)?.id?.toString() ?? payoutMethods[0]?.id?.toString() ?? '',
        amount: '',
    });

    const selectedMethod = payoutMethods.find((m) => m.id === Number(withdrawForm.data.payout_method_id));

    const saveMethod: FormEventHandler = (e) => {
        e.preventDefault();
        methodForm.post(route('seller.wallet.payout-methods.store'), {
            onSuccess: () => {
                methodForm.reset();
                setShowAddMethod(false);
            },
        });
    };

    const submitWithdraw: FormEventHandler = (e) => {
        e.preventDefault();
        if (withdrawStep === 'method') {
            setWithdrawStep('amount');
            return;
        }
        if (withdrawStep === 'amount') {
            setWithdrawStep('review');
            return;
        }
        withdrawForm.post(route('seller.wallet.withdraw'), {
            onSuccess: () => {
                withdrawForm.reset();
                setWithdrawStep('method');
            },
        });
    };

    const statusColor: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-blue-100 text-blue-800',
        approved: 'bg-blue-100 text-blue-800',
        paid: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
    };

    const statusLabel: Record<string, string> = {
        pending: 'Awaiting admin',
        processing: 'Payout in progress',
        paid: 'Paid out',
        rejected: 'Rejected',
    };

    return (
        <SellerLayout title="Finance" active="wallet">
            <Head title="Finance" />

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    { label: 'Available', value: wallet.available_balance, desc: 'Withdrawable' },
                    { label: 'Pending', value: wallet.pending_balance, desc: 'Clearing' },
                    { label: 'Lifetime earnings', value: wallet.total_earnings, desc: 'All time' },
                    { label: 'Withdrawn', value: wallet.withdrawn_amount, desc: 'Paid out' },
                ].map((card) => (
                    <div key={card.label} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p className="text-sm text-gray-500">{card.label}</p>
                        <p className="mt-1 text-2xl font-bold text-gray-900">{formatPrice(card.value)}</p>
                        <p className="text-xs text-gray-400">{card.desc}</p>
                    </div>
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Payout methods</h3>
                        <Button type="button" variant="outline" size="sm" onClick={() => setShowAddMethod(!showAddMethod)}>
                            <Plus className="mr-1 h-4 w-4" /> Add
                        </Button>
                    </div>

                    {showAddMethod && (
                        <form onSubmit={saveMethod} className="mt-4 space-y-3 rounded-xl border border-dashed border-gray-200 p-4">
                            <div>
                                <Label>Network</Label>
                                <select
                                    value={methodForm.data.network}
                                    onChange={(e) => methodForm.setData('network', e.target.value)}
                                    className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                >
                                    <option value="mtn">MTN Mobile Money</option>
                                    <option value="telecel">Telecel Cash</option>
                                    <option value="airteltigo">AirtelTigo Money</option>
                                </select>
                            </div>
                            <div>
                                <Label>Mobile number</Label>
                                <Input value={methodForm.data.account_number} onChange={(e) => methodForm.setData('account_number', e.target.value)} required className="mt-1" placeholder="0XX XXX XXXX" />
                                <InputError message={methodForm.errors.account_number} />
                            </div>
                            <div>
                                <Label>Account name</Label>
                                <Input value={methodForm.data.account_name} onChange={(e) => methodForm.setData('account_name', e.target.value)} required className="mt-1" />
                                <InputError message={methodForm.errors.account_name} />
                            </div>
                            <Button type="submit" disabled={methodForm.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                                Save payout method
                            </Button>
                        </form>
                    )}

                    <ul className="mt-4 space-y-2">
                        {payoutMethods.map((method) => (
                            <li key={method.id} className="flex items-center justify-between rounded-xl border border-gray-100 p-3">
                                <div>
                                    <p className="font-medium text-gray-900">{networkLabels[method.network] ?? method.network}</p>
                                    <p className="text-sm text-gray-500">{method.account_number} · {method.account_name}</p>
                                    {method.is_default && <span className="text-xs text-orange-500">Default</span>}
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-red-500"
                                    onClick={() => router.delete(route('seller.wallet.payout-methods.destroy', method.id))}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </li>
                        ))}
                        {payoutMethods.length === 0 && !showAddMethod && (
                            <p className="text-sm text-gray-500">Add a payout method to withdraw funds.</p>
                        )}
                    </ul>
                </div>

                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Request withdrawal</h3>
                    {hasPendingWithdrawal ? (
                        <p className="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">You have a pending withdrawal. Please wait for it to be processed before submitting another.</p>
                    ) : payoutMethods.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500">Save a payout method first.</p>
                    ) : (
                        <form onSubmit={submitWithdraw} className="mt-4 space-y-4">
                            {withdrawStep === 'method' && (
                                <div className="space-y-2">
                                    <Label>Select payout method</Label>
                                    {payoutMethods.map((method) => (
                                        <label
                                            key={method.id}
                                            className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 ${withdrawForm.data.payout_method_id === String(method.id) ? 'border-orange-500 bg-orange-50' : 'border-gray-200'}`}
                                        >
                                            <input
                                                type="radio"
                                                name="payout_method_id"
                                                value={method.id}
                                                checked={withdrawForm.data.payout_method_id === String(method.id)}
                                                onChange={() => withdrawForm.setData('payout_method_id', String(method.id))}
                                            />
                                            <div>
                                                <p className="font-medium">{networkLabels[method.network]}</p>
                                                <p className="text-sm text-gray-500">{method.account_number}</p>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            )}

                            {withdrawStep === 'amount' && (
                                <div>
                                    <Label>Amount (GH₵)</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="10"
                                        max={wallet.available_balance}
                                        value={withdrawForm.data.amount}
                                        onChange={(e) => withdrawForm.setData('amount', e.target.value)}
                                        required
                                        className="mt-1"
                                    />
                                    <InputError message={withdrawForm.errors.amount} />
                                    <button type="button" className="mt-2 text-sm text-orange-500 hover:underline" onClick={() => withdrawForm.setData('amount', String(wallet.available_balance))}>
                                        Withdraw all ({formatPrice(wallet.available_balance)})
                                    </button>
                                    <p className="mt-2 text-xs text-gray-500">Minimum withdrawal: GH₵10</p>
                                </div>
                            )}

                            {withdrawStep === 'review' && selectedMethod && (
                                <div className="rounded-xl bg-gray-50 p-4 text-sm space-y-2">
                                    <p><span className="text-gray-500">Network:</span> {networkLabels[selectedMethod.network]}</p>
                                    <p><span className="text-gray-500">Number:</span> {selectedMethod.account_number}</p>
                                    <p><span className="text-gray-500">Name:</span> {selectedMethod.account_name}</p>
                                    <p className="text-lg font-bold text-orange-500">{formatPrice(parseFloat(withdrawForm.data.amount) || 0)}</p>
                                    <p className="text-xs text-gray-500">Admin will send via Paystack or manual MoMo after review.</p>
                                </div>
                            )}

                            <div className="flex gap-2">
                                {withdrawStep !== 'method' && (
                                    <Button type="button" variant="outline" onClick={() => setWithdrawStep(withdrawStep === 'review' ? 'amount' : 'method')}>
                                        Back
                                    </Button>
                                )}
                                <Button type="submit" disabled={withdrawForm.processing} className="flex-1 bg-orange-500 hover:bg-orange-600">
                                    {withdrawForm.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    {withdrawStep === 'review' ? (
                                        <><Check className="mr-2 h-4 w-4" /> Submit request</>
                                    ) : (
                                        'Continue'
                                    )}
                                </Button>
                            </div>
                        </form>
                    )}
                </div>
            </div>

            <div className="mt-8 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Withdrawal history</h3>
                {withdrawals.data.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">No withdrawal requests yet.</p>
                ) : (
                    <div className="mt-4 divide-y">
                        {withdrawals.data.map((w) => (
                            <div key={w.id} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusColor[w.status] ?? 'bg-gray-100'}`}>
                                            {statusLabel[w.status] ?? w.status}
                                        </span>
                                        {w.payout_channel && (
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs capitalize text-gray-600">
                                                {w.payout_channel}
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1 text-gray-500">{networkLabels[w.network]} · {w.momo_number}</p>
                                    <p className="text-xs text-gray-400">{formatDate(w.created_at)}</p>
                                    {w.rejection_reason && <p className="mt-1 text-xs text-red-600">{w.rejection_reason}</p>}
                                </div>
                                <p className="font-bold text-gray-900">{formatPrice(w.amount)}</p>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="mt-8 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Transactions</h3>
                {transactions.data.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">No transactions yet.</p>
                ) : (
                    <div className="mt-4 divide-y">
                        {transactions.data.map((tx) => (
                            <div key={tx.id} className="flex justify-between py-3 text-sm">
                                <div>
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{formatWalletTransactionType(tx.type)}</span>
                                    <p className="mt-1 text-gray-600">{tx.description}</p>
                                </div>
                                <p className={`font-semibold ${tx.amount > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {tx.amount > 0 ? '+' : ''}{formatPrice(tx.amount)}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </SellerLayout>
    );
}
