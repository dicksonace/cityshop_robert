import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import DocumentUploadField from '@/components/forms/document-upload-field';
import DirectPaymentDetails from '@/components/shop/direct-payment-details';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import { formatPrice } from '@/types/marketplace';
import { SharedData } from '@/types';

export type FundingAccount = {
    type: 'momo' | 'bank';
    label: string;
    account_name: string;
    account_number: string;
    network?: string | null;
    bank_name?: string | null;
};

export type TopUpHistoryItem = {
    id: number;
    amount: number;
    payment_reference: string;
    status: string;
    admin_notes: string | null;
    proof_url: string | null;
    created_at: string | null;
    reviewed_at: string | null;
};

interface Props {
    settings: {
        enabled: boolean;
        instructions: string;
        accounts: FundingAccount[];
    };
    requests: TopUpHistoryItem[];
    walletRoute: string;
    submitRoute: string;
    /** Seller layout has no flash banner — show inline. Shop layout already shows flash at top. */
    showFlash?: boolean;
}

function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function ManualTopUpForm({ settings, requests, walletRoute, submitRoute, showFlash = false }: Props) {
    const { flash } = usePage<SharedData>().props;

    const form = useForm({
        amount: '',
        payment_reference: '',
        sender_name: '',
        sender_number: '',
        network: 'mtn',
        user_note: '',
        proof: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(submitRoute, { forceFormData: true });
    };

    const statusColor: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-800',
    };

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <Link href={walletRoute} className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
                <ArrowLeft className="h-4 w-4" />
                Back to wallet
            </Link>

            <div>
                <h1 className="text-2xl font-bold text-gray-900">Manual top-up</h1>
                <p className="mt-1 text-sm text-gray-500">
                    For larger amounts: send payment to CityShop, then upload proof and your transaction reference.
                </p>
            </div>

            {showFlash && flash.success && (
                <div className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}
            {showFlash && flash.error && (
                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{flash.error}</div>
            )}

            {settings.instructions && (
                <div className="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    {settings.instructions}
                </div>
            )}

            <div className="space-y-3">
                <h2 className="text-sm font-semibold text-gray-900">Send payment to</h2>
                <p className="text-xs text-gray-500">Choose the network that matches your phone — tap Copy, then send.</p>
                {settings.accounts.map((account, index) => (
                    <DirectPaymentDetails
                        key={`${account.network ?? account.type}-${account.account_number}-${index}`}
                        accountNumber={account.account_number}
                        accountName={account.account_name}
                        network={account.type === 'momo' ? account.network : null}
                        isBank={account.type === 'bank'}
                        bankName={account.bank_name}
                    />
                ))}
            </div>

            <form onSubmit={submit} className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-gray-900">After you pay — submit proof</h2>
                <p className="mt-1 text-sm text-gray-500">We credit your wallet once an admin verifies the transfer.</p>

                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label>Amount sent (GH₵)</Label>
                        <Input
                            type="number"
                            step="0.01"
                            min="10"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            required
                            className="mt-1"
                        />
                        <InputError message={form.errors.amount} />
                    </div>
                    <div>
                        <Label>Payment reference / ID</Label>
                        <Input
                            value={form.data.payment_reference}
                            onChange={(e) => form.setData('payment_reference', e.target.value)}
                            required
                            className="mt-1"
                            placeholder="From MoMo or bank SMS"
                        />
                        <InputError message={form.errors.payment_reference} />
                    </div>
                    <div>
                        <Label>Name on account you sent from</Label>
                        <Input
                            value={form.data.sender_name}
                            onChange={(e) => form.setData('sender_name', e.target.value)}
                            required
                            className="mt-1"
                        />
                        <InputError message={form.errors.sender_name} />
                    </div>
                    <div>
                        <Label>Your MoMo / account number (optional)</Label>
                        <Input
                            value={form.data.sender_number}
                            onChange={(e) => form.setData('sender_number', e.target.value)}
                            className="mt-1"
                        />
                        <InputError message={form.errors.sender_number} />
                    </div>
                    <div className="sm:col-span-2">
                        <MomoNetworkPicker
                            value={form.data.network === 'bank' ? 'mtn' : form.data.network}
                            onChange={(network) => form.setData('network', network)}
                            label="Network you paid from"
                            hint="Select the MoMo network on the phone you used to send the money."
                        />
                        <InputError message={form.errors.network} />
                    </div>
                    <div className="sm:col-span-2">
                        <DocumentUploadField
                            id="manual-top-up-proof"
                            label="Screenshot / receipt"
                            hint="Upload a screenshot of your MoMo or bank payment confirmation"
                            required
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            maxSizeMb={5}
                            value={form.data.proof}
                            onChange={(file) => form.setData('proof', file)}
                            error={form.errors.proof}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <Label>Note (optional)</Label>
                        <Input
                            value={form.data.user_note}
                            onChange={(e) => form.setData('user_note', e.target.value)}
                            className="mt-1"
                            placeholder="Anything else we should know"
                        />
                    </div>
                </div>

                <Button type="submit" disabled={form.processing} className="mt-4 w-full bg-green-600 py-6 text-base font-semibold hover:bg-green-700">
                    {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Submit for verification
                </Button>
            </form>

            {requests.length > 0 && (
                <div>
                    <h2 className="mb-3 text-sm font-semibold text-gray-900">Your recent requests</h2>
                    <div className="space-y-2">
                        {requests.map((item) => (
                            <div key={item.id} className="rounded-xl border border-gray-100 bg-white px-4 py-3 text-sm shadow-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="font-semibold text-gray-900">{formatPrice(item.amount)}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor[item.status] ?? 'bg-gray-100'}`}>
                                        {item.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-gray-500">
                                    Ref: {item.payment_reference} · {formatDate(item.created_at)}
                                </p>
                                {item.admin_notes && <p className="mt-1 text-gray-700">Admin: {item.admin_notes}</p>}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
