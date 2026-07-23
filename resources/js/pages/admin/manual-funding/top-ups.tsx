import { Head, router, usePage } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
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
    counts: { pending: number; approved: number; rejected: number };
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

export default function ManualTopUps({ requests, status, counts }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [busyId, setBusyId] = useState<number | null>(null);
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [notes, setNotes] = useState('');

    const setFilter = (next: string) => {
        router.get(route('admin.manual-top-ups.index'), { status: next === 'pending' ? undefined : next }, { preserveState: true });
    };

    const approve = (id: number) => {
        setBusyId(id);
        router.post(
            route('admin.manual-top-ups.approve', id),
            { admin_notes: notes || undefined },
            { onFinish: () => { setBusyId(null); setNotes(''); } },
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
                },
            },
        );
    };

    return (
        <AdminLayout title="Manual Top-ups" active="manual-top-ups">
            <Head title="Manual Top-ups" />

            <div className="mb-4">
                <h1 className="text-lg font-bold text-gray-900">Manual top-up requests</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Users send money to your MoMo/bank account, then submit proof. Approve to credit their wallet.
                </p>
            </div>

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

            <div className="mb-4 flex flex-wrap gap-2">
                {(
                    [
                        ['pending', `Pending (${counts.pending})`],
                        ['approved', `Approved (${counts.approved})`],
                        ['rejected', `Rejected (${counts.rejected})`],
                        ['all', 'All'],
                    ] as const
                ).map(([key, label]) => (
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
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-lg font-bold text-gray-900">{formatPrice(item.amount)}</p>
                                    <p className="text-sm text-gray-600">
                                        {item.user?.name} · {item.user?.role} · {item.user?.email}
                                    </p>
                                    <p className="mt-1 text-xs text-gray-500">Submitted {formatDate(item.created_at)}</p>
                                </div>
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        item.status === 'pending'
                                            ? 'bg-amber-100 text-amber-800'
                                            : item.status === 'approved'
                                              ? 'bg-emerald-100 text-emerald-800'
                                              : 'bg-red-100 text-red-800'
                                    }`}
                                >
                                    {item.status}
                                </span>
                            </div>

                            <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-gray-500">Payment reference</dt>
                                    <dd className="font-medium text-gray-900">{item.payment_reference || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-gray-500">Sender</dt>
                                    <dd className="font-medium text-gray-900">
                                        {item.sender_name}
                                        {item.sender_number ? ` · ${item.sender_number}` : ''}
                                        {item.network ? ` · ${item.network}` : ''}
                                    </dd>
                                </div>
                                {item.user_note && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-gray-500">User note</dt>
                                        <dd className="text-gray-800">{item.user_note}</dd>
                                    </div>
                                )}
                                {item.admin_notes && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-gray-500">Admin notes</dt>
                                        <dd className="text-gray-800">{item.admin_notes}</dd>
                                    </div>
                                )}
                            </dl>

                            {item.proof_url && (
                                <div className="mt-4">
                                    <p className="mb-1 text-xs font-medium text-gray-500">Payment proof</p>
                                    <a href={item.proof_url} target="_blank" rel="noreferrer" className="inline-block">
                                        <img
                                            src={item.proof_url}
                                            alt="Payment proof"
                                            className="max-h-48 rounded-lg border border-gray-200 object-contain"
                                        />
                                    </a>
                                </div>
                            )}

                            {item.status === 'pending' && (
                                <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="bg-emerald-600 hover:bg-emerald-700"
                                        disabled={busyId === item.id}
                                        onClick={() => approve(item.id)}
                                    >
                                        <Check className="mr-1 h-4 w-4" />
                                        Approve & credit
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="border-red-200 text-red-700"
                                        disabled={busyId === item.id}
                                        onClick={() => {
                                            setRejectId(item.id);
                                            setNotes('');
                                        }}
                                    >
                                        <X className="mr-1 h-4 w-4" />
                                        Reject
                                    </Button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {rejectId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
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
