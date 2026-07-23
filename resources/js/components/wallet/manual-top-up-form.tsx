import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import DocumentUploadField from '@/components/forms/document-upload-field';
import DirectPaymentDetails from '@/components/shop/direct-payment-details';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MomoNetworkLogo from '@/components/wallet/momo-network-logo';
import { MOMO_NETWORKS, momoNetworkLabel, normalizeMomoNetworkId } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';
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
    const [infoOpen, setInfoOpen] = useState(false);
    const [selectedNetwork, setSelectedNetwork] = useState<string | null>(null);

    const momoAccountsByNetwork = useMemo(() => {
        const map: Record<string, FundingAccount> = {};
        for (const account of settings.accounts) {
            if (account.type !== 'momo') continue;
            const id = normalizeMomoNetworkId(account.network);
            if (id && !map[id]) {
                map[id] = account;
            }
        }
        return map;
    }, [settings.accounts]);

    const bankAccounts = useMemo(
        () => settings.accounts.filter((account) => account.type === 'bank'),
        [settings.accounts],
    );

    const selectedAccount = selectedNetwork ? momoAccountsByNetwork[selectedNetwork] ?? null : null;

    const form = useForm({
        amount: '',
        payment_reference: '',
        network: '',
        user_note: '',
        proof: null as File | null,
    });

    const openNetworkInfo = (networkId: string) => {
        if (!momoAccountsByNetwork[networkId]) return;
        setSelectedNetwork(networkId);
        form.setData('network', networkId);
        setInfoOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!form.data.network) {
            form.setError('network', 'Choose MTN, Telecel, or AirtelTigo first.');
            return;
        }
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
                <h1 className="text-2xl font-bold text-gray-900">Manual deposit</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Choose MTN, Telecel, or AirtelTigo — we show the CityShop number to pay. Then submit proof.
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

            <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-gray-900">1. Choose payment method</h2>
                <p className="mt-1 text-sm text-gray-500">Tap a network to see the number / till and account name — then Copy and send.</p>

                <div className="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    {MOMO_NETWORKS.map((network) => {
                        const account = momoAccountsByNetwork[network.id];
                        const selected = selectedNetwork === network.id;
                        const disabled = !account;

                        return (
                            <button
                                key={network.id}
                                type="button"
                                disabled={disabled}
                                onClick={() => openNetworkInfo(network.id)}
                                className={cn(
                                    'flex min-h-[4.75rem] items-center gap-3 rounded-xl border-2 px-3 py-3 text-left transition',
                                    disabled && 'cursor-not-allowed opacity-40',
                                    !disabled && (selected ? network.selectedClass : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'),
                                )}
                            >
                                <MomoNetworkLogo network={network.id} size="sm" />
                                <span className="min-w-0">
                                    <span className={cn('block text-[10px] font-bold uppercase tracking-wide', selected ? network.accent : 'text-gray-400')}>
                                        {network.id === 'mtn' ? 'Recommended' : 'MoMo'}
                                    </span>
                                    <span className="mt-0.5 block text-sm font-semibold text-gray-900">{network.label}</span>
                                    {disabled ? (
                                        <span className="block text-xs text-gray-400">Not configured</span>
                                    ) : (
                                        <span className="block text-xs text-gray-500">Tap to view &amp; copy</span>
                                    )}
                                </span>
                            </button>
                        );
                    })}
                </div>
                <InputError message={form.errors.network} className="mt-2" />

                {selectedAccount && selectedNetwork && (
                    <div className="mt-4">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <p className="text-sm font-semibold text-gray-900">
                                Paying via {momoNetworkLabel(selectedNetwork)}
                            </p>
                            <button
                                type="button"
                                onClick={() => setInfoOpen(true)}
                                className="text-sm font-semibold text-sky-700 hover:underline"
                            >
                                View details again
                            </button>
                        </div>
                        <DirectPaymentDetails
                            accountNumber={selectedAccount.account_number}
                            accountName={selectedAccount.account_name}
                            network={selectedNetwork}
                            hint="Send the exact amount, then fill the proof form below."
                        />
                    </div>
                )}
            </div>

            {bankAccounts.length > 0 && (
                <div className="space-y-3">
                    <h2 className="text-sm font-semibold text-gray-900">Or pay by bank</h2>
                    {bankAccounts.map((account, index) => (
                        <DirectPaymentDetails
                            key={`bank-${account.account_number}-${index}`}
                            accountNumber={account.account_number}
                            accountName={account.account_name}
                            isBank
                            bankName={account.bank_name}
                        />
                    ))}
                </div>
            )}

            <form onSubmit={submit} className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-gray-900">2. After you pay — submit proof</h2>
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
                        <Label>Payment reference / ID <span className="font-normal text-gray-400">(optional)</span></Label>
                        <Input
                            value={form.data.payment_reference}
                            onChange={(e) => form.setData('payment_reference', e.target.value)}
                            className="mt-1"
                            placeholder="From MoMo or bank SMS"
                        />
                        <InputError message={form.errors.payment_reference} />
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

                <Button
                    type="submit"
                    disabled={form.processing || !form.data.network}
                    className="mt-4 w-full bg-green-600 py-6 text-base font-semibold hover:bg-green-700"
                >
                    {form.processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    I've paid — submit for verification
                </Button>
                {!form.data.network && (
                    <p className="mt-2 text-center text-xs text-amber-700">Choose a payment method above first.</p>
                )}
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
                                    {item.payment_reference ? `Ref: ${item.payment_reference} · ` : ''}
                                    {formatDate(item.created_at)}
                                </p>
                                {item.admin_notes && <p className="mt-1 text-gray-700">Admin: {item.admin_notes}</p>}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <Dialog open={infoOpen} onOpenChange={setInfoOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {selectedNetwork ? momoNetworkLabel(selectedNetwork) : 'Payment details'}
                        </DialogTitle>
                        <DialogDescription>
                            Copy the number, send from your phone, then submit proof on this page.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedAccount && selectedNetwork ? (
                        <DirectPaymentDetails
                            accountNumber={selectedAccount.account_number}
                            accountName={selectedAccount.account_name}
                            network={selectedNetwork}
                        />
                    ) : (
                        <p className="text-sm text-gray-500">No account configured for this network.</p>
                    )}

                    <DialogFooter>
                        <Button type="button" className="w-full bg-green-600 hover:bg-green-700" onClick={() => setInfoOpen(false)}>
                            I've copied — continue
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
