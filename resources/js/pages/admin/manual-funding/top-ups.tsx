import { Head, router, usePage } from '@inertiajs/react';
import { Check, Eye, Pencil, Search, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface TopUpRequest {
    id: number;
    amount: number;
    payment_reference: string;
    sender_name: string | null;
    sender_number: string | null;
    network: string | null;
    proof_url: string | null;
    user_note: string | null;
    status: string;
    admin_notes: string | null;
    created_at: string | null;
    reviewed_at: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        mobile: string | null;
        role: string;
    } | null;
}

interface Props {
    requests: Paginated<TopUpRequest>;
    status: string;
    search?: string;
    pendingTotal?: number;
    counts: { pending: number; approved: number; rejected: number; cancelled?: number };
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

function initial(name?: string | null): string {
    return (name?.trim()?.[0] ?? '?').toUpperCase();
}

export default function ManualTopUps({ requests, status, search = '', pendingTotal = 0, counts }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [busyId, setBusyId] = useState<number | null>(null);
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [detail, setDetail] = useState<TopUpRequest | null>(null);
    const [notes, setNotes] = useState('');
    const [query, setQuery] = useState(search);
    const [editAmount, setEditAmount] = useState('');

    const setFilter = (next: string) => {
        router.get(
            route('admin.manual-top-ups.index'),
            { status: next === 'pending' ? undefined : next, q: query || undefined },
            { preserveState: true },
        );
    };

    const runSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('admin.manual-top-ups.index'),
            { status: status === 'pending' ? undefined : status, q: query || undefined },
            { preserveState: true },
        );
    };

    const openDetail = (item: TopUpRequest) => {
        setDetail(item);
        setEditAmount(Number(item.amount).toFixed(2));
        setNotes(item.admin_notes ?? '');
    };

    const saveAmount = () => {
        if (!detail) return;
        const next = Number(editAmount);
        if (!Number.isFinite(next) || next < 1) {
            window.alert('Enter a valid amount (minimum GH₵1).');
            return;
        }
        setBusyId(detail.id);
        // POST (not PATCH) — some hosts block PATCH and Save looks broken.
        router.post(
            route('admin.manual-top-ups.amount', detail.id),
            { amount: next },
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
                onSuccess: () => {
                    setDetail((d) => (d ? { ...d, amount: next } : d));
                    setEditAmount(next.toFixed(2));
                    // Refresh list + pending total so the card amount updates too.
                    router.reload({ only: ['requests', 'pendingTotal', 'counts'] });
                },
                onError: (errors) => {
                    const msg =
                        (errors as Record<string, string>).amount ||
                        (errors as Record<string, string>).message ||
                        'Could not update amount.';
                    window.alert(msg);
                },
            },
        );
    };

    const approve = (id: number, amount?: string) => {
        setBusyId(id);
        router.post(
            route('admin.manual-top-ups.approve', id),
            {
                admin_notes: notes || undefined,
                amount: amount || undefined,
            },
            {
                onFinish: () => {
                    setBusyId(null);
                    setNotes('');
                    setDetail(null);
                },
            },
        );
    };

    const reject = () => {
        if (!rejectId) return;
        setBusyId(rejectId);
        router.post(
            route('admin.manual-top-ups.reject', rejectId),
            { admin_notes: notes },
            {
                onFinish: () => {
                    setBusyId(null);
                    setRejectId(null);
                    setNotes('');
                    setDetail(null);
                },
            },
        );
    };

    const filters = useMemo(
        () =>
            [
                ['pending', `Pending (${counts.pending})`],
                ['approved', `Approved (${counts.approved})`],
                ['rejected', `Rejected (${counts.rejected})`],
                ['cancelled', `Cancelled (${counts.cancelled ?? 0})`],
                ['all', 'All'],
            ] as const,
        [counts],
    );

    return (
        <AdminLayout title="Manual Top-ups" active="manual-top-ups">
            <Head title="Recent Deposits" />

            <div className="mb-4">
                <h1 className="text-lg font-bold text-gray-900">Recent Deposit</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Review manual MoMo/bank proofs. Approve credits the wallet. Pending deposits stay off the user
                    transaction list until credited.
                </p>
            </div>

            {status === 'pending' && (
                <div className="mb-4 rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4">
                    <p className="text-xs font-semibold uppercase tracking-wide text-amber-700">Pending Approval</p>
                    <p className="mt-1 text-sm text-amber-800">Total Pending Amount</p>
                    <p className="text-2xl font-bold text-amber-950">{formatPrice(pendingTotal)}</p>
                </div>
            )}

            {flash.success && (
                <div className="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {flash.error}
                </div>
            )}

            <form onSubmit={runSearch} className="mb-4 space-y-2">
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search by name, email, or phone..."
                />
                <Button type="submit" className="w-full bg-violet-600 hover:bg-violet-700 sm:w-auto">
                    <Search className="mr-2 h-4 w-4" />
                    Search
                </Button>
            </form>

            <div className="mb-4 flex flex-wrap gap-2">
                {filters.map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setFilter(key)}
                        className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                            status === key ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 shadow-sm'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {requests.data.length === 0 ? (
                <p className="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow-sm">No requests in this view.</p>
            ) : (
                <div className="space-y-4">
                    {requests.data.map((item) => (
                        <div key={item.id} className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div className="flex items-start justify-between gap-3">
                                <p className="font-bold text-gray-900">#{item.id}</p>
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
                                        item.status === 'pending'
                                            ? 'bg-amber-100 text-amber-800'
                                            : item.status === 'approved'
                                              ? 'bg-emerald-100 text-emerald-800'
                                              : item.status === 'cancelled'
                                                ? 'bg-gray-100 text-gray-600'
                                                : 'bg-red-100 text-red-800'
                                    }`}
                                >
                                    {item.status === 'pending' ? 'Pending' : item.status}
                                </span>
                            </div>

                            <div className="mt-3 flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-800">
                                    {initial(item.user?.name)}
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-gray-900">{item.user?.name}</p>
                                    <p className="truncate text-xs text-gray-500">{item.user?.email}</p>
                                    <p className="text-xs text-gray-500">{item.user?.mobile || '—'}</p>
                                </div>
                            </div>

                            <div className="mt-4 flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <p className="text-xl font-bold text-emerald-600">{formatPrice(item.amount)}</p>
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-800">
                                            Manual
                                        </span>
                                        <span className="text-xs text-gray-500">{formatDate(item.created_at)}</span>
                                    </div>
                                </div>
                            </div>

                            {item.status === 'pending' && (
                                <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                                    <Button type="button" size="sm" className="bg-violet-600 hover:bg-violet-700" onClick={() => openDetail(item)}>
                                        <Eye className="mr-1 h-4 w-4" />
                                        View
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="bg-emerald-600 hover:bg-emerald-700"
                                        disabled={busyId === item.id}
                                        onClick={() => approve(item.id)}
                                    >
                                        <Check className="mr-1 h-4 w-4" />
                                        Approve
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="bg-red-600 px-2 hover:bg-red-700"
                                        disabled={busyId === item.id}
                                        onClick={() => {
                                            setRejectId(item.id);
                                            setNotes('');
                                        }}
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}

                            {item.status !== 'pending' && (
                                <div className="mt-4 border-t border-gray-100 pt-4">
                                    <Button type="button" size="sm" variant="outline" onClick={() => openDetail(item)}>
                                        <Eye className="mr-1 h-4 w-4" />
                                        View full details
                                    </Button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {detail && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4">
                    <div className="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h3 className="text-lg font-bold text-gray-900">Deposit Details</h3>
                                <p className="text-sm text-gray-500">#{detail.id}</p>
                            </div>
                            <button type="button" className="rounded-lg p-1 text-gray-500 hover:bg-gray-100" onClick={() => setDetail(null)}>
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {detail.status === 'pending' ? (
                            <div className="mt-4 rounded-xl border border-amber-100 bg-amber-50/60 p-3">
                                <p className="text-xs font-semibold uppercase tracking-wide text-amber-800">Amount (edit before approve)</p>
                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <div className="relative min-w-[140px] flex-1">
                                        <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-500">
                                            GH₵
                                        </span>
                                        <Input
                                            type="number"
                                            min="1"
                                            step="0.01"
                                            value={editAmount}
                                            onChange={(e) => setEditAmount(e.target.value)}
                                            className="h-11 bg-white pl-12 text-lg font-bold"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        className="h-11 bg-violet-600 hover:bg-violet-700"
                                        disabled={busyId === detail.id}
                                        onClick={saveAmount}
                                    >
                                        <Pencil className="mr-1 h-4 w-4" />
                                        {busyId === detail.id ? 'Saving…' : 'Save'}
                                    </Button>
                                </div>
                                <p className="mt-2 text-xs text-amber-900/80">
                                    Change the amount, tap Save, then Approve. Approve can also use the amount in this field.
                                </p>
                            </div>
                        ) : (
                            <p className="mt-4 text-2xl font-bold text-emerald-600">{formatPrice(detail.amount)}</p>
                        )}

                        <dl className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between gap-3"><dt className="text-gray-500">Name</dt><dd className="font-medium">{detail.user?.name}</dd></div>
                            <div className="flex justify-between gap-3"><dt className="text-gray-500">Email</dt><dd className="font-medium">{detail.user?.email}</dd></div>
                            <div className="flex justify-between gap-3"><dt className="text-gray-500">Phone</dt><dd className="font-medium">{detail.user?.mobile || '—'}</dd></div>
                            <div className="flex justify-between gap-3"><dt className="text-gray-500">Method</dt><dd className="font-medium uppercase">Manual</dd></div>
                            <div className="flex justify-between gap-3"><dt className="text-gray-500">Network</dt><dd className="font-medium uppercase">{detail.network || '—'}</dd></div>
                            <div className="flex justify-between gap-3"><dt className="text-gray-500">Status</dt><dd className="capitalize font-medium">{detail.status}</dd></div>
                            <div className="flex justify-between gap-3"><dt className="text-gray-500">Created</dt><dd>{formatDate(detail.created_at)}</dd></div>
                            {detail.payment_reference && (
                                <div className="flex justify-between gap-3"><dt className="text-gray-500">Reference</dt><dd className="font-medium">{detail.payment_reference}</dd></div>
                            )}
                            {detail.user_note && (
                                <div><dt className="text-gray-500">User note</dt><dd className="mt-1 text-gray-800">{detail.user_note}</dd></div>
                            )}
                        </dl>

                        <div className="mt-5">
                            <p className="text-sm font-semibold text-gray-900">Transaction Timeline</p>
                            <div className="mt-2 border-l-2 border-violet-200 pl-3 text-sm text-gray-600">
                                <p>Deposit created · {formatDate(detail.created_at)}</p>
                                {detail.reviewed_at && <p className="mt-2">Reviewed · {formatDate(detail.reviewed_at)}</p>}
                            </div>
                        </div>

                        {detail.proof_url && (
                            <div className="mt-5">
                                <p className="mb-2 text-sm font-semibold text-gray-900">Payment Proof</p>
                                <a href={detail.proof_url} target="_blank" rel="noreferrer">
                                    <img src={detail.proof_url} alt="Payment proof" className="max-h-56 rounded-lg border object-contain" />
                                </a>
                            </div>
                        )}

                        {detail.status === 'pending' && (
                            <div className="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                                <Button
                                    type="button"
                                    className="bg-emerald-600 hover:bg-emerald-700"
                                    disabled={busyId === detail.id}
                                    onClick={() => approve(detail.id, editAmount)}
                                >
                                    <Check className="mr-1 h-4 w-4" />
                                    Approve
                                </Button>
                                <Button
                                    type="button"
                                    className="bg-red-600 hover:bg-red-700"
                                    disabled={busyId === detail.id}
                                    onClick={() => {
                                        setRejectId(detail.id);
                                        setNotes('');
                                    }}
                                >
                                    <X className="mr-1 h-4 w-4" />
                                    Reject
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {rejectId !== null && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                        <h3 className="font-semibold text-gray-900">Reject top-up</h3>
                        <p className="mt-1 text-sm text-gray-500">Tell the user why (shown on their request).</p>
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={3}
                            className="mt-3 w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Reason for rejection"
                            required
                        />
                        <div className="mt-4 flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setRejectId(null)}>
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                className="bg-red-600 hover:bg-red-700"
                                disabled={!notes.trim() || busyId !== null}
                                onClick={reject}
                            >
                                Reject
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
