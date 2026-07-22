import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Download, Play, Store, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { momoNetworkLabel } from '@/lib/momo-networks';
import { formatPrice, Paginated, productImageUrl } from '@/types/marketplace';

interface AdminWithdrawalRow {
    id: number;
    amount: number;
    momo_number: string;
    account_name: string;
    network: string;
    status: string;
    payout_channel?: string | null;
    rejection_reason?: string | null;
    failure_reason?: string | null;
    proof_path?: string | null;
    admin_notes?: string | null;
    created_at?: string;
    processed_at?: string;
    user?: {
        id: number;
        name: string;
        email: string;
        mobile?: string | null;
        role?: string;
    } | null;
    seller?: {
        business_name: string;
        store_name?: string | null;
        slug?: string | null;
        business_address?: string | null;
    } | null;
    wallet: {
        available_balance: number;
        pending_balance: number;
        total_earnings: number;
        withdrawn_amount: number;
    };
}

interface WithdrawalsIndexProps {
    withdrawals: Paginated<AdminWithdrawalRow>;
    status: string;
    role: string;
    counts: { pending_sellers: number; pending_buyers: number; processing: number };
}

function formatDateTime(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export default function WithdrawalsIndex({ withdrawals, status, role, counts }: WithdrawalsIndexProps) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [reason, setReason] = useState('');
    const [completeId, setCompleteId] = useState<number | null>(null);
    const [completeNotes, setCompleteNotes] = useState('');
    const [proofFile, setProofFile] = useState<File | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [detailId, setDetailId] = useState<number | null>(null);

    const detail = useMemo(
        () => withdrawals.data.find((w) => w.id === detailId) ?? null,
        [detailId, withdrawals.data],
    );

    const statusTabs = ['pending', 'processing', 'paid', 'rejected', 'all'];
    const roleTabs = [
        { value: 'all', label: 'Everyone' },
        { value: 'seller', label: `Sellers (${counts.pending_sellers})` },
        { value: 'buyer', label: `Buyers (${counts.pending_buyers})` },
    ];

    const statusColor = (value: string) => {
        switch (value) {
            case 'pending':
                return 'bg-amber-100 text-amber-800';
            case 'processing':
                return 'bg-blue-100 text-blue-800';
            case 'paid':
                return 'bg-green-100 text-green-800';
            case 'rejected':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-700';
        }
    };

    const roleBadge = (userRole?: string) => {
        if (userRole === 'seller') return 'bg-orange-100 text-orange-800';
        if (userRole === 'buyer') return 'bg-sky-100 text-sky-800';
        return 'bg-gray-100 text-gray-700';
    };

    const listHref = (nextStatus: string, nextRole: string = role) =>
        route('admin.withdrawals.index', { status: nextStatus, role: nextRole === 'all' ? undefined : nextRole });

    const startProcessing = (id: number) => {
        setBusyId(id);
        router.post(route('admin.withdrawals.start', id), {}, { onFinish: () => setBusyId(null) });
    };

    const submitComplete = (e: FormEvent) => {
        e.preventDefault();
        if (!completeId) return;

        const formData = new FormData();
        if (completeNotes.trim()) formData.append('admin_notes', completeNotes.trim());
        if (proofFile) formData.append('proof', proofFile);

        setBusyId(completeId);
        router.post(route('admin.withdrawals.approve', completeId), formData, {
            forceFormData: true,
            onFinish: () => {
                setBusyId(null);
                setCompleteId(null);
                setCompleteNotes('');
                setProofFile(null);
            },
        });
    };

    return (
        <AdminLayout title="Withdrawals" active="withdrawals">
            <Head title="Withdrawals" />

            <p className="mb-4 text-sm text-gray-500">
                Manual payouts only: 1) Press <strong>Start</strong> so the seller sees Processing. 2) Send MoMo yourself.
                3) Mark complete with optional proof photo, or reject with a reason. Target turnaround: about 1 hour.
            </p>

            {(flash?.success || flash?.error) && (
                <div className={`mb-4 rounded-lg px-4 py-3 text-sm ${flash.error ? 'border border-red-200 bg-red-50 text-red-800' : 'border border-green-200 bg-green-50 text-green-800'}`}>
                    {flash.error || flash.success}
                </div>
            )}

            <div className="scrollbar-hide -mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1">
                {roleTabs.map((tab) => (
                    <Link
                        key={tab.value}
                        href={listHref(status, tab.value)}
                        className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium ${
                            role === tab.value ? 'bg-slate-900 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>

            <div className="scrollbar-hide -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1">
                {statusTabs.map((tab) => (
                    <Link
                        key={tab}
                        href={listHref(tab)}
                        className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium capitalize ${
                            status === tab ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'
                        }`}
                    >
                        {tab === 'processing' ? `processing (${counts.processing})` : tab}
                    </Link>
                ))}
            </div>

            <div className="space-y-4">
                {withdrawals.data.length === 0 ? (
                    <p className="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm">No withdrawals in this view.</p>
                ) : (
                    withdrawals.data.map((w) => {
                        const isSeller = w.user?.role === 'seller';

                        return (
                            <div key={w.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-lg font-bold">{formatPrice(w.amount)}</p>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${statusColor(w.status)}`}>
                                                {w.status}
                                            </span>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${roleBadge(w.user?.role)}`}>
                                                {w.user?.role ?? 'user'}
                                            </span>
                                        </div>

                                        <p className="mt-2 text-sm font-medium text-gray-900">
                                            {w.seller?.business_name ?? w.user?.name}
                                        </p>
                                        <p className="text-sm text-gray-500">
                                            {w.user?.email}
                                            {w.user?.mobile ? ` · ${w.user.mobile}` : ''}
                                        </p>
                                        <p className="mt-1 text-sm text-gray-600">
                                            Pay to: {momoNetworkLabel(w.network)} · {w.momo_number}
                                            {w.account_name ? ` · ${w.account_name}` : ''}
                                        </p>

                                        {isSeller && (
                                            <div className="mt-3 rounded-xl border border-orange-100 bg-orange-50/70 p-3 text-sm">
                                                <p className="flex items-center gap-1.5 font-semibold text-orange-900">
                                                    <Store className="h-4 w-4" />
                                                    Seller details
                                                </p>
                                                <dl className="mt-2 grid gap-1 text-xs text-orange-950 sm:grid-cols-2">
                                                    <div>
                                                        <dt className="text-orange-700/80">Store</dt>
                                                        <dd className="font-medium">{w.seller?.business_name ?? w.user?.name ?? '—'}</dd>
                                                    </div>
                                                    <div>
                                                        <dt className="text-orange-700/80">Current available</dt>
                                                        <dd className="font-semibold text-emerald-700">{formatPrice(w.wallet.available_balance)}</dd>
                                                    </div>
                                                    <div>
                                                        <dt className="text-orange-700/80">Pending balance</dt>
                                                        <dd className="font-medium">{formatPrice(w.wallet.pending_balance)}</dd>
                                                    </div>
                                                    <div>
                                                        <dt className="text-orange-700/80">Lifetime withdrawn</dt>
                                                        <dd className="font-medium">{formatPrice(w.wallet.withdrawn_amount)}</dd>
                                                    </div>
                                                    {w.seller?.business_address && (
                                                        <div className="sm:col-span-2">
                                                            <dt className="text-orange-700/80">Address</dt>
                                                            <dd className="font-medium">{w.seller.business_address}</dd>
                                                        </div>
                                                    )}
                                                </dl>
                                                <p className="mt-2 text-xs font-medium text-orange-700">Seller earnings withdrawal</p>
                                            </div>
                                        )}

                                        {!isSeller && (
                                            <p className="mt-2 text-xs text-gray-500">
                                                Current available: <span className="font-semibold text-emerald-700">{formatPrice(w.wallet.available_balance)}</span>
                                            </p>
                                        )}

                                        {w.admin_notes && (
                                            <p className="mt-1 text-xs text-gray-600">Note: {w.admin_notes}</p>
                                        )}
                                        {w.rejection_reason && (
                                            <p className="mt-1 text-xs text-red-600">Rejected: {w.rejection_reason}</p>
                                        )}
                                        {w.failure_reason && w.failure_reason !== w.rejection_reason && (
                                            <p className="mt-1 text-xs text-red-600">{w.failure_reason}</p>
                                        )}
                                        {w.proof_path && (
                                            <a
                                                href={productImageUrl(w.proof_path)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:underline"
                                            >
                                                <Download className="h-4 w-4" />
                                                View / download proof
                                            </a>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:justify-end">
                                        <Button size="sm" variant="outline" onClick={() => setDetailId(w.id)}>
                                            Full details
                                        </Button>
                                        {w.status === 'pending' && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    className="bg-blue-600 hover:bg-blue-700"
                                                    disabled={busyId === w.id}
                                                    onClick={() => startProcessing(w.id)}
                                                >
                                                    <Play className="mr-1 h-4 w-4" />
                                                    Start processing
                                                </Button>
                                                <Button size="sm" variant="destructive" onClick={() => { setRejectId(w.id); setReason(''); }}>
                                                    <X className="mr-1 h-4 w-4" />
                                                    Reject
                                                </Button>
                                            </>
                                        )}
                                        {w.status === 'processing' && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    className="bg-emerald-600 hover:bg-emerald-700"
                                                    onClick={() => {
                                                        setCompleteId(w.id);
                                                        setCompleteNotes('');
                                                        setProofFile(null);
                                                    }}
                                                >
                                                    <Check className="mr-1 h-4 w-4" />
                                                    Mark complete
                                                </Button>
                                                <Button size="sm" variant="destructive" onClick={() => { setRejectId(w.id); setReason(''); }}>
                                                    Reject & refund
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {detail && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4">
                    <div className="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
                        <div className="flex items-center justify-between bg-gradient-to-r from-slate-900 to-orange-800 px-5 py-4 text-white">
                            <div>
                                <p className="text-sm font-medium text-white/80">Withdrawal details</p>
                                <p className="text-lg font-bold">Withdrawal #{detail.id}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setDetailId(null)}
                                className="rounded-full bg-white/15 p-2 hover:bg-white/25"
                                aria-label="Close"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="space-y-4 p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm text-gray-500">Amount</p>
                                    <p className="text-2xl font-bold text-emerald-600">{formatPrice(detail.amount)}</p>
                                </div>
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${statusColor(detail.status)}`}>
                                    {detail.status === 'paid' ? '✓ Completed' : detail.status}
                                </span>
                            </div>

                            <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm">
                                <p className="font-semibold text-gray-900">
                                    {detail.user?.role === 'seller' ? 'Seller' : 'User'}
                                </p>
                                <p className="mt-1 text-gray-800">{detail.seller?.business_name ?? detail.user?.name}</p>
                                <p className="text-gray-600">{detail.user?.email}</p>
                                {detail.user?.mobile && <p className="text-gray-600">{detail.user.mobile}</p>}
                                {detail.seller?.slug && (
                                    <p className="mt-1 text-xs text-gray-500">Store: /store/{detail.seller.slug}</p>
                                )}
                            </div>

                            <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                                <p className="text-xs font-medium uppercase tracking-wide text-emerald-700">Current available balance</p>
                                <p className="mt-1 text-xl font-bold text-emerald-800">{formatPrice(detail.wallet.available_balance)}</p>
                                <p className="mt-1 text-xs text-emerald-800/80">
                                    Pending {formatPrice(detail.wallet.pending_balance)} · Withdrawn {formatPrice(detail.wallet.withdrawn_amount)}
                                </p>
                            </div>

                            <div className="rounded-xl border border-sky-100 bg-sky-50 p-4 text-sm">
                                <p className="font-semibold text-sky-900">Mobile money</p>
                                <p className="mt-1 text-sky-900">{momoNetworkLabel(detail.network)}</p>
                                <p className="text-sky-800">{detail.momo_number}</p>
                                <p className="text-sky-800">{detail.account_name}</p>
                            </div>

                            {detail.admin_notes && (
                                <div className="rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
                                    <p className="font-semibold">Admin note</p>
                                    <p className="mt-1">{detail.admin_notes}</p>
                                </div>
                            )}

                            <div className="rounded-xl border border-gray-100 p-4 text-sm">
                                <p className="font-semibold text-gray-900">Timeline</p>
                                <dl className="mt-2 space-y-1.5 text-gray-600">
                                    <div className="flex justify-between gap-3">
                                        <dt>Requested</dt>
                                        <dd className="font-medium text-gray-900">{formatDateTime(detail.created_at)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt>Processed</dt>
                                        <dd className="font-medium text-emerald-700">{formatDateTime(detail.processed_at)}</dd>
                                    </div>
                                </dl>
                            </div>

                            {detail.proof_path && (
                                <a
                                    href={productImageUrl(detail.proof_path)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:underline"
                                >
                                    <Download className="h-4 w-4" />
                                    View / download proof
                                </a>
                            )}

                            <Button type="button" variant="outline" className="w-full" onClick={() => setDetailId(null)}>
                                Close
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {completeId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <form onSubmit={submitComplete} className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="font-semibold text-gray-900">Mark payout complete</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Seller will see Paid. Attach a MoMo receipt screenshot if you have one.
                        </p>
                        <div className="mt-4 space-y-3">
                            <div>
                                <Label>Note to seller (optional)</Label>
                                <Input
                                    className="mt-1"
                                    placeholder="e.g. Sent via MTN MoMo"
                                    value={completeNotes}
                                    onChange={(e) => setCompleteNotes(e.target.value)}
                                />
                            </div>
                            <div>
                                <Label>Proof image (optional)</Label>
                                <Input
                                    type="file"
                                    accept="image/*"
                                    className="mt-1"
                                    onChange={(e) => setProofFile(e.target.files?.[0] ?? null)}
                                />
                                <p className="mt-1 text-xs text-gray-400">JPG/PNG up to 5MB. Seller can view and download it.</p>
                            </div>
                        </div>
                        <div className="mt-4 flex gap-2">
                            <Button type="submit" className="bg-emerald-600 hover:bg-emerald-700" disabled={busyId === completeId}>
                                Confirm paid
                            </Button>
                            <Button type="button" variant="outline" onClick={() => setCompleteId(null)}>Cancel</Button>
                        </div>
                    </form>
                </div>
            )}

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6">
                        <h3 className="font-semibold">Reject withdrawal</h3>
                        <p className="mt-1 text-sm text-gray-500">Funds return to the wallet. Seller will see your reason.</p>
                        <Input className="mt-3" placeholder="Reason (required)..." value={reason} onChange={(e) => setReason(e.target.value)} />
                        <div className="mt-4 flex gap-2">
                            <Button
                                variant="destructive"
                                disabled={!reason.trim()}
                                onClick={() => router.post(route('admin.withdrawals.reject', rejectId), { rejection_reason: reason }, { onSuccess: () => { setRejectId(null); setReason(''); } })}
                            >
                                Confirm reject
                            </Button>
                            <Button variant="outline" onClick={() => setRejectId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
