import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Clock, Eye, Play, Smartphone, X } from 'lucide-react';
import { ChangeEvent, FormEvent, useEffect, useMemo, useRef, useState } from 'react';

import { RmbAutoRefreshChip, RmbTransferStatusBadge } from '@/components/china/rmb-transfer-status-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { Paginated } from '@/types/marketplace';

type PayoutAccount = {
    network: string | null;
    number: string | null;
    account_name: string | null;
};

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    admin_queue_section?: string;
    quote: { rmb_amount: number; usd_payout: number; ghs_payout: number; payout_currency: string; payout_amount: number };
    created_at: string | null;
    user: { id: number; name: string; mobile: string | null } | null;
    payout_account?: PayoutAccount;
};

interface Props {
    transfers: Paginated<Transfer>;
    status: string;
    search: string;
    dashboard: {
        total: number;
        submitted: number;
        awaiting_verification: number;
        processing: number;
        completed: number;
        failed: number;
        rmb_received: number;
        usd_paid: number;
        ghs_paid: number;
        fees_collected: number;
        today: number;
        this_month: number;
        awaiting_review?: number;
        send_momo_now?: number;
        open_count?: number;
        open_rmb_total?: number;
        open_ghs_total?: number;
    };
}

type ModalTransfer = Transfer | null;

function formatPayout(item: Transfer): string {
    if (item.quote.payout_currency === 'ghs') {
        return `GH₵${item.quote.ghs_payout.toFixed(2)}`;
    }

    return `$${item.quote.usd_payout.toFixed(2)}`;
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function momoLine(account?: PayoutAccount): string {
    if (!account?.number) return 'Not provided';
    const parts = [account.network, account.number].filter(Boolean);
    return parts.join(' · ');
}

function canProcess(item: Transfer): boolean {
    return ['submitted', 'rmb_verification'].includes(item.status);
}

function canApprove(item: Transfer): boolean {
    return ['rmb_received', 'payout_processing', 'paid'].includes(item.status);
}

export default function SellRmbIndex({ transfers, status, search, dashboard }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [q, setQ] = useState(search);
    const [processTarget, setProcessTarget] = useState<ModalTransfer>(null);
    const [approveTarget, setApproveTarget] = useState<ModalTransfer>(null);
    const [rejectTarget, setRejectTarget] = useState<ModalTransfer>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [approveProof, setApproveProof] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const proofInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const id = window.setInterval(() => {
            router.reload({
                only: ['transfers', 'dashboard', 'status', 'search'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 8000);
        return () => window.clearInterval(id);
    }, [status, search]);

    const grouped = useMemo(() => {
        if (status !== 'open') {
            return [{ key: 'all', label: 'All requests', items: transfers.data }];
        }

        const sendMomo = transfers.data.filter((item) => item.admin_queue_section === 'send_momo');
        const awaiting = transfers.data.filter((item) => item.admin_queue_section === 'awaiting_review');
        const other = transfers.data.filter((item) => !['send_momo', 'awaiting_review'].includes(item.admin_queue_section ?? ''));

        const sections: { key: string; label: string; items: Transfer[]; tone: 'blue' | 'yellow' | 'gray' }[] = [];

        if (sendMomo.length) {
            sections.push({
                key: 'send_momo',
                label: `Send MoMo now (${sendMomo.length})`,
                items: sendMomo,
                tone: 'blue',
            });
        }
        if (awaiting.length) {
            sections.push({
                key: 'awaiting_review',
                label: `Awaiting review (${awaiting.length})`,
                items: awaiting,
                tone: 'yellow',
            });
        }
        if (other.length) {
            sections.push({
                key: 'other',
                label: `Other open (${other.length})`,
                items: other,
                tone: 'gray',
            });
        }

        return sections;
    }, [status, transfers.data]);

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.sell-rmb.index'), { status, q }, { preserveState: true });
    };

    const submitProcess = () => {
        if (!processTarget) return;
        setBusy(true);
        router.post(route('admin.sell-rmb.mark-processing', processTarget.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                setBusy(false);
                setProcessTarget(null);
            },
        });
    };

    const submitApprove = () => {
        if (!approveTarget) return;
        setBusy(true);
        router.post(
            route('admin.sell-rmb.approve-payout', approveTarget.id),
            approveProof ? { proof: approveProof } : {},
            {
                forceFormData: Boolean(approveProof),
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setApproveTarget(null);
                    setApproveProof(null);
                },
            },
        );
    };

    const submitReject = () => {
        if (!rejectTarget || rejectReason.trim() === '') return;
        setBusy(true);
        router.post(
            route('admin.sell-rmb.reject', rejectTarget.id),
            { reason: rejectReason.trim() },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setRejectTarget(null);
                    setRejectReason('');
                },
            },
        );
    };

    const onProofChange = (event: ChangeEvent<HTMLInputElement>) => {
        setApproveProof(event.target.files?.[0] ?? null);
    };

    const openCount = dashboard.open_count ?? transfers.data.length;
    const awaitingReview = dashboard.awaiting_review ?? 0;
    const sendMomoNow = dashboard.send_momo_now ?? 0;
    const openRmb = dashboard.open_rmb_total ?? 0;
    const openGhs = dashboard.open_ghs_total ?? 0;

    return (
        <AdminLayout title="Open RMB Sells" active="sell-rmb">
            <Head title="Open RMB Sells" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">Open RMB Sells</h1>
                        <p className="text-sm text-gray-500">Verify Alipay proof → Process → send MoMo → Approve.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <RmbAutoRefreshChip />
                        <Link href={route('admin.sell-rmb.settings')}>
                            <Button variant="outline">Settings</Button>
                        </Link>
                    </div>
                </div>

                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}

                <div className="rounded-xl border-l-4 border-blue-400 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <strong>Workflow:</strong> Verify Alipay proof → <strong>Process</strong> (moves to “Send MoMo now”) → send GH₵ to
                    their MoMo → <strong>Approve</strong>. Payout does <strong>not</strong> add to the user&apos;s in-app GHS wallet.
                </div>

                {status === 'open' && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl border-l-4 border-yellow-500 bg-yellow-50 p-4 shadow-sm">
                            <p className="text-sm font-medium text-yellow-700">Awaiting Review</p>
                            <p className="mt-1 text-3xl font-bold text-yellow-800">{awaitingReview}</p>
                        </div>
                        <div className="rounded-xl border-l-4 border-blue-500 bg-blue-50 p-4 shadow-sm">
                            <p className="text-sm font-medium text-blue-700">Processing (send MoMo)</p>
                            <p className="mt-1 text-3xl font-bold text-blue-800">{sendMomoNow}</p>
                        </div>
                        <div className="rounded-xl border-l-4 border-red-500 bg-red-50 p-4 shadow-sm">
                            <p className="text-sm font-medium text-red-700">Sell RMB (open)</p>
                            <p className="mt-1 text-3xl font-bold text-red-800">¥{openRmb.toFixed(2)}</p>
                        </div>
                        <div className="rounded-xl border-l-4 border-green-500 bg-green-50 p-4 shadow-sm">
                            <p className="text-sm font-medium text-green-700">GHS to Pay via MoMo</p>
                            <p className="mt-1 text-3xl font-bold text-green-800">GH₵{openGhs.toFixed(2)}</p>
                        </div>
                    </div>
                )}

                {status === 'open' && (
                    <Link
                        href={route('admin.sell-rmb.index', { status: 'completed', q })}
                        className="flex items-center justify-center gap-2 rounded-xl border border-purple-200 bg-purple-50 px-4 py-3 text-sm font-semibold text-purple-800 hover:bg-purple-100 sm:justify-start"
                    >
                        <Clock className="h-4 w-4" />
                        View completed & rejected sells
                    </Link>
                )}

                <div className="flex flex-wrap gap-2">
                    {[
                        { id: 'open', label: 'Open' },
                        { id: 'completed', label: 'Completed' },
                        { id: 'rejected', label: 'Rejected' },
                        { id: 'all', label: 'All' },
                    ].map((item) => (
                        <Link
                            key={item.id}
                            href={route('admin.sell-rmb.index', { status: item.id, q })}
                            className={`rounded-full px-3 py-1.5 text-sm font-semibold ${
                                status === item.id ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:max-w-xl">
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search by name, email, phone, or reference..."
                    />
                    <Button type="submit" className="sm:min-w-28">
                        Search
                    </Button>
                </form>

                {transfers.data.length === 0 && (
                    <div className="rounded-2xl border border-gray-200 bg-white p-10 text-center text-gray-500">
                        <p className="font-medium">No open RMB sell requests</p>
                        <p className="mt-2 text-sm">Configure Alipay details and sell rate in Settings.</p>
                    </div>
                )}

                {grouped.map((section) => (
                    <div key={section.key} className="space-y-3">
                        {'tone' in section && section.tone === 'blue' && (
                            <div className="flex items-center gap-2 rounded-lg bg-blue-50 px-4 py-2 text-sm font-bold text-blue-800">
                                <Smartphone className="h-4 w-4" />
                                {section.label}
                            </div>
                        )}
                        {'tone' in section && section.tone === 'yellow' && (
                            <div className="flex items-center gap-2 rounded-lg bg-yellow-50 px-4 py-2 text-sm font-bold text-yellow-800">
                                <Clock className="h-4 w-4" />
                                {section.label}
                            </div>
                        )}
                        {'tone' in section && section.tone === 'gray' && (
                            <div className="rounded-lg bg-gray-50 px-4 py-2 text-sm font-bold text-gray-700">{section.label}</div>
                        )}
                        {!('tone' in section) && section.label && (
                            <div className="rounded-lg bg-gray-50 px-4 py-2 text-sm font-bold text-gray-700">{section.label}</div>
                        )}

                        <div className="space-y-3">
                            {section.items.map((item) => (
                                <div
                                    key={item.id}
                                    className={`rounded-2xl border border-gray-200 bg-white p-4 shadow-sm ${
                                        item.admin_queue_section === 'send_momo' ? 'bg-blue-50/40' : ''
                                    }`}
                                >
                                    <div className="mb-2 flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-bold text-gray-900">#{item.id}</p>
                                            <p className="text-xs text-gray-500">{formatDate(item.created_at)}</p>
                                        </div>
                                        <RmbTransferStatusBadge status={item.status} label={item.status_label} />
                                    </div>

                                    <p className="text-sm font-medium text-gray-900">{item.user?.name ?? 'Buyer'}</p>

                                    <div className="my-3 grid grid-cols-2 gap-2">
                                        <div className="rounded-lg bg-red-50 p-3 text-center">
                                            <p className="text-xs text-gray-600">RMB</p>
                                            <p className="font-bold text-red-600">¥{item.quote.rmb_amount.toFixed(2)}</p>
                                        </div>
                                        <div className="rounded-lg bg-green-50 p-3 text-center">
                                            <p className="text-xs text-gray-600">GHS Payout</p>
                                            <p className="font-bold text-green-600">{formatPayout(item)}</p>
                                        </div>
                                    </div>

                                    {item.payout_account?.number && (
                                        <div className="mb-3 rounded-lg bg-green-50 p-3 text-sm">
                                            <p className="text-xs text-gray-600">MoMo payout</p>
                                            <p className="font-medium text-green-800">{momoLine(item.payout_account)}</p>
                                            {item.payout_account.account_name && (
                                                <p className="text-xs text-gray-500">{item.payout_account.account_name}</p>
                                            )}
                                        </div>
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        <Link href={route('admin.sell-rmb.show', item.id)} className="flex-1">
                                            <Button variant="default" className="w-full bg-indigo-600 hover:bg-indigo-700">
                                                <Eye className="mr-1 h-4 w-4" />
                                                View
                                            </Button>
                                        </Link>
                                        {canProcess(item) && (
                                            <Button
                                                className="flex-1 bg-blue-600 hover:bg-blue-700"
                                                onClick={() => setProcessTarget(item)}
                                            >
                                                <Play className="mr-1 h-4 w-4" />
                                                Process
                                            </Button>
                                        )}
                                        {canApprove(item) && (
                                            <Button
                                                className="flex-1 bg-emerald-600 hover:bg-emerald-700"
                                                onClick={() => {
                                                    setApproveTarget(item);
                                                    setApproveProof(null);
                                                }}
                                            >
                                                <Check className="mr-1 h-4 w-4" />
                                                Approve
                                            </Button>
                                        )}
                                        <Button
                                            variant="destructive"
                                            size="icon"
                                            onClick={() => {
                                                setRejectTarget(item);
                                                setRejectReason('');
                                            }}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

                {status !== 'open' && openCount === 0 && transfers.data.length > 0 && null}
            </div>

            <Dialog open={processTarget !== null} onOpenChange={(open) => !open && setProcessTarget(null)}>
                <DialogContent className="max-w-md gap-0 overflow-hidden p-0">
                    <div className="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 text-white">
                        <DialogHeader className="space-y-1 text-left">
                            <DialogTitle className="text-white">Start Processing</DialogTitle>
                            <p className="text-sm text-blue-100">Verify Alipay proof, then send MoMo payout.</p>
                        </DialogHeader>
                    </div>
                    {processTarget && (
                        <div className="space-y-4 p-6">
                            <p className="text-sm text-gray-700">
                                Mark sell request from <strong>{processTarget.user?.name}</strong> as Processing?
                            </p>
                            <div className="rounded-lg bg-gray-50 p-4 text-sm">
                                <p>
                                    <span className="text-gray-600">RMB received:</span>{' '}
                                    <strong className="text-red-600">¥{processTarget.quote.rmb_amount.toFixed(2)}</strong>
                                </p>
                                <p className="mt-2">
                                    Next step: send <strong className="text-emerald-700">{formatPayout(processTarget)}</strong> to their
                                    MoMo, then click <strong>Approve</strong>.
                                </p>
                            </div>
                            {processTarget.payout_account?.number && (
                                <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm">
                                    <p className="flex items-center gap-2 font-semibold text-emerald-800">
                                        <Smartphone className="h-4 w-4" />
                                        Send payout to:
                                    </p>
                                    <p className="mt-1 font-medium">{momoLine(processTarget.payout_account)}</p>
                                    {processTarget.payout_account.account_name && (
                                        <p className="text-gray-600">{processTarget.payout_account.account_name}</p>
                                    )}
                                </div>
                            )}
                            <p className="text-xs text-gray-500">The user will be notified that their payout is being processed.</p>
                            <DialogFooter className="gap-2 sm:gap-2">
                                <Button variant="outline" onClick={() => setProcessTarget(null)} disabled={busy}>
                                    Cancel
                                </Button>
                                <Button className="bg-blue-600 hover:bg-blue-700" onClick={submitProcess} disabled={busy}>
                                    <Play className="mr-1 h-4 w-4" />
                                    Mark Processing
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={approveTarget !== null} onOpenChange={(open) => !open && setApproveTarget(null)}>
                <DialogContent className="max-w-lg gap-0 overflow-hidden p-0">
                    <div className="bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 py-4 text-white">
                        <DialogHeader className="space-y-1 text-left">
                            <DialogTitle className="text-white">Approve RMB Sell</DialogTitle>
                            <p className="text-sm text-emerald-100">Confirm MoMo payout sent (does not add to wallet).</p>
                        </DialogHeader>
                    </div>
                    {approveTarget && (
                        <div className="space-y-4 p-6">
                            <p className="text-sm text-gray-700">
                                {approveTarget.user?.name} sold <strong>¥{approveTarget.quote.rmb_amount.toFixed(2)}</strong>
                            </p>
                            <p className="text-sm">
                                Confirm you sent <strong className="text-emerald-700">{formatPayout(approveTarget)}</strong> via Mobile
                                Money (not wallet credit).
                            </p>
                            {approveTarget.payout_account?.number && (
                                <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm">
                                    <p className="font-semibold text-emerald-800">Send GHS to MoMo:</p>
                                    <p className="mt-1">{momoLine(approveTarget.payout_account)}</p>
                                    {approveTarget.payout_account.account_name && (
                                        <p className="text-gray-600">Name: {approveTarget.payout_account.account_name}</p>
                                    )}
                                </div>
                            )}
                            <p className="text-xs text-gray-500">Only approve after MoMo payment is sent.</p>
                            <div>
                                <p className="mb-2 text-sm font-medium">Upload MoMo Receipt/Proof (Optional)</p>
                                <button
                                    type="button"
                                    onClick={() => proofInputRef.current?.click()}
                                    className="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 px-4 py-8 text-sm text-gray-600 hover:border-blue-400 hover:bg-blue-50/40"
                                >
                                    <span className="font-medium text-blue-600">
                                        {approveProof ? approveProof.name : 'Click to upload MoMo payment screenshot'}
                                    </span>
                                    <span className="mt-1 text-xs">JPG, PNG, or PDF · max 5MB</span>
                                </button>
                                <input ref={proofInputRef} type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" className="hidden" onChange={onProofChange} />
                            </div>
                            <DialogFooter className="gap-2 sm:gap-2">
                                <Button variant="outline" onClick={() => setApproveTarget(null)} disabled={busy}>
                                    Cancel
                                </Button>
                                <Button className="bg-emerald-600 hover:bg-emerald-700" onClick={submitApprove} disabled={busy}>
                                    <Check className="mr-1 h-4 w-4" />
                                    Approve
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={rejectTarget !== null} onOpenChange={(open) => !open && setRejectTarget(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reject sell request</DialogTitle>
                    </DialogHeader>
                    {rejectTarget && (
                        <div className="space-y-4">
                            <p className="text-sm text-gray-600">
                                Reject sell request from <strong>{rejectTarget.user?.name}</strong>?
                            </p>
                            <Input
                                value={rejectReason}
                                onChange={(e) => setRejectReason(e.target.value)}
                                placeholder="Reason for rejection"
                            />
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setRejectTarget(null)} disabled={busy}>
                                    Cancel
                                </Button>
                                <Button variant="destructive" onClick={submitReject} disabled={busy || rejectReason.trim() === ''}>
                                    Reject
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
