import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { formatPrice } from '@/types/marketplace';

export type DepositStatusRequest = {
    id: number;
    amount: number;
    payment_reference: string;
    network: string | null;
    user_note: string | null;
    status: string;
    admin_notes: string | null;
    proof_url: string | null;
    created_at: string | null;
    reviewed_at: string | null;
};

interface Props {
    request: DepositStatusRequest;
    walletRoute: string;
    historyRoute: string;
    cancelRoute: string;
    pollUrl: string;
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

export default function ManualTopUpStatus({
    request: initial,
    walletRoute,
    historyRoute,
    cancelRoute,
    pollUrl,
}: Props) {
    const [item, setItem] = useState(initial);
    const [cancelling, setCancelling] = useState(false);

    useEffect(() => {
        setItem(initial);
    }, [initial]);

    useEffect(() => {
        if (item.status !== 'pending') return;

        const timer = window.setInterval(async () => {
            try {
                const res = await fetch(pollUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const body = await res.json();
                if (body?.data?.status) {
                    setItem(body.data);
                    if (body.data.status !== 'pending') {
                        router.reload({ only: ['request'] });
                    }
                }
            } catch {
                // keep polling
            }
        }, 4000);

        return () => window.clearInterval(timer);
    }, [item.status, pollUrl]);

    const cancel = () => {
        if (!confirm('Cancel this deposit request?')) return;
        setCancelling(true);
        router.post(cancelRoute, {}, { onFinish: () => setCancelling(false) });
    };

    const pending = item.status === 'pending';
    const approved = item.status === 'approved';
    const rejected = item.status === 'rejected' || item.status === 'cancelled';

    return (
        <ShopLayout>
            <Head title="Deposit status" />
            <div className="mx-auto max-w-lg px-4 py-6">
                <div
                    className={`rounded-t-2xl px-6 py-10 text-center text-white ${
                        approved ? 'bg-emerald-600' : rejected ? 'bg-red-600' : 'bg-emerald-600'
                    }`}
                >
                    {pending && <LoaderCircle className="mx-auto h-14 w-14 animate-spin" />}
                    {approved && <CheckCircle2 className="mx-auto h-14 w-14" />}
                    {rejected && <XCircle className="mx-auto h-14 w-14" />}
                    <h1 className="mt-4 text-2xl font-bold">
                        {pending ? 'Awaiting Approval' : approved ? 'Deposit Credited' : item.status === 'cancelled' ? 'Request Cancelled' : 'Deposit Rejected'}
                    </h1>
                    <p className="mt-2 text-sm text-white/90">
                        {pending
                            ? 'Your deposit is being reviewed.'
                            : approved
                              ? 'Funds have been credited to your wallet.'
                              : item.admin_notes || 'This request was not credited.'}
                    </p>
                </div>

                <div className="rounded-b-2xl border border-t-0 border-gray-200 bg-white p-5 shadow-sm">
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-3">
                            <dt className="text-gray-500">Deposit Amount</dt>
                            <dd className="font-semibold text-gray-900">{formatPrice(item.amount)}</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-gray-500">Type</dt>
                            <dd className="font-medium text-gray-900">Manual (MoMo)</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-gray-500">Date/Time</dt>
                            <dd className="font-medium text-gray-900">{formatDate(item.created_at)}</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-gray-500">Status</dt>
                            <dd>
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${
                                        pending
                                            ? 'bg-amber-100 text-amber-800'
                                            : approved
                                              ? 'bg-emerald-100 text-emerald-800'
                                              : 'bg-red-100 text-red-800'
                                    }`}
                                >
                                    {item.status === 'pending' ? 'Pending' : item.status}
                                </span>
                            </dd>
                        </div>
                        {item.payment_reference ? (
                            <div className="flex justify-between gap-3">
                                <dt className="text-gray-500">Reference</dt>
                                <dd className="font-medium text-gray-900">{item.payment_reference}</dd>
                            </div>
                        ) : null}
                    </dl>

                    {pending && (
                        <p className="mt-5 text-center text-xs text-gray-500">
                            Checking for updates automatically…
                            <br />
                            You can also leave or come back later.
                        </p>
                    )}

                    <div className="mt-6 grid grid-cols-2 gap-3">
                        <Button asChild className="bg-emerald-600 hover:bg-emerald-700">
                            <Link href={walletRoute}>Back to Wallet</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={historyRoute}>View History</Link>
                        </Button>
                    </div>

                    {pending && (
                        <Button
                            type="button"
                            variant="outline"
                            className="mt-3 w-full border-red-200 text-red-700 hover:bg-red-50"
                            disabled={cancelling}
                            onClick={cancel}
                        >
                            {cancelling ? 'Cancelling…' : 'Cancel request'}
                        </Button>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
