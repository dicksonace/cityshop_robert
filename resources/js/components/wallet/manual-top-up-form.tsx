import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, LoaderCircle, X } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import DocumentUploadField from '@/components/forms/document-upload-field';
import DirectPaymentDetails from '@/components/shop/direct-payment-details';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MomoNetworkPicker from '@/components/wallet/momo-network-picker';
import { normalizeMomoNetworkId } from '@/lib/momo-networks';
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
    network?: string | null;
    user_note?: string | null;
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
    statusRouteName?: string;
    cancelRouteName?: string;
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

export default function ManualTopUpForm({
    settings,
    requests,
    walletRoute,
    submitRoute,
    statusRouteName,
    cancelRouteName,
    showFlash = false,
}: Props) {
    const { flash } = usePage<SharedData>().props;
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

    const form = useForm({
        amount: '',
        payment_reference: '',
        network: '',
        user_note: '',
        proof: null as File | null,
    });

    useEffect(() => {
        if (selectedNetwork && momoAccountsByNetwork[selectedNetwork]) return;
        const defaultId = ['mtn', 'telecel', 'airteltigo'].find((id) => momoAccountsByNetwork[id]);
        if (defaultId) {
            setSelectedNetwork(defaultId);
            form.setData('network', defaultId);
        }
    }, [momoAccountsByNetwork, selectedNetwork]);

    const bankAccounts = useMemo(
        () => settings.accounts.filter((account) => account.type === 'bank'),
        [settings.accounts],
    );

    const selectedAccount = selectedNetwork ? momoAccountsByNetwork[selectedNetwork] ?? null : null;

    const selectNetwork = (networkId: string) => {
        if (!momoAccountsByNetwork[networkId]) return;
        setSelectedNetwork(networkId);
        form.setData('network', networkId);
        form.clearErrors('network');
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
        cancelled: 'bg-gray-100 text-gray-600',
    };

    const [detail, setDetail] = useState<TopUpHistoryItem | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    const cancelRequest = (id: number) => {
        if (!cancelRouteName || !confirm('Cancel this deposit request?')) return;
        setBusyId(id);
        router.post(route(cancelRouteName, id), {}, { onFinish: () => setBusyId(null) });
    };

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <Link href={walletRoute} className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
                <ArrowLeft className="h-4 w-4" />
                Back to wallet
            </Link>

            <div>
                <h1 className="text-2xl font-bold text-gray-900">Manual deposit</h1>
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

                <MomoNetworkPicker
                    className="mt-3"
                    value={selectedNetwork ?? ''}
                    onChange={selectNetwork}
                    enabledNetworks={Object.keys(momoAccountsByNetwork)}
                    variant="selected"
                />
                <InputError message={form.errors.network} className="mt-2" />

                {selectedAccount && selectedNetwork && (
                    <div className="mt-3">
                        <DirectPaymentDetails
                            accountNumber={selectedAccount.account_number}
                            accountName={selectedAccount.account_name}
                            network={selectedNetwork}
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
                    <h2 className="mb-3 text-sm font-semibold text-gray-900">Recent Deposit</h2>
                    <div className="space-y-2">
                        {requests.map((item) => (
                            <div key={item.id} className="rounded-xl border border-gray-100 bg-white px-4 py-3 text-sm shadow-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="font-semibold text-gray-900">{formatPrice(item.amount)}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusColor[item.status] ?? 'bg-gray-100'}`}>
                                        {item.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-gray-500">
                                    {item.payment_reference ? `Ref: ${item.payment_reference} · ` : ''}
                                    {formatDate(item.created_at)}
                                </p>
                                {item.admin_notes && item.status !== 'cancelled' && (
                                    <p className="mt-1 text-gray-700">Admin: {item.admin_notes}</p>
                                )}
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {statusRouteName ? (
                                        <Button asChild size="sm" variant="outline" className="h-8">
                                            <Link href={route(statusRouteName, item.id)}>
                                                <Eye className="mr-1 h-3.5 w-3.5" />
                                                View full details
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button size="sm" variant="outline" className="h-8" type="button" onClick={() => setDetail(item)}>
                                            <Eye className="mr-1 h-3.5 w-3.5" />
                                            View full details
                                        </Button>
                                    )}
                                    {item.status === 'pending' && cancelRouteName && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="h-8 border-red-200 text-red-700 hover:bg-red-50"
                                            disabled={busyId === item.id}
                                            onClick={() => cancelRequest(item.id)}
                                        >
                                            <X className="mr-1 h-3.5 w-3.5" />
                                            Cancel request
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <Dialog open={!!detail} onOpenChange={(open) => !open && setDetail(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Deposit details</DialogTitle>
                        <DialogDescription>#{detail?.id}</DialogDescription>
                    </DialogHeader>
                    {detail && (
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between"><dt className="text-gray-500">Amount</dt><dd className="font-semibold">{formatPrice(detail.amount)}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">Status</dt><dd className="capitalize">{detail.status}</dd></div>
                            <div className="flex justify-between"><dt className="text-gray-500">Date</dt><dd>{formatDate(detail.created_at)}</dd></div>
                            {detail.payment_reference && (
                                <div className="flex justify-between"><dt className="text-gray-500">Reference</dt><dd>{detail.payment_reference}</dd></div>
                            )}
                            {detail.proof_url && (
                                <a href={detail.proof_url} target="_blank" rel="noreferrer" className="mt-2 block">
                                    <img src={detail.proof_url} alt="Proof" className="max-h-40 rounded-lg border object-contain" />
                                </a>
                            )}
                        </dl>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
