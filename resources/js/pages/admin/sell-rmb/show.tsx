import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { RmbAutoRefreshChip } from '@/components/china/rmb-transfer-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    quote: {
        rmb_amount: number;
        usd_per_rmb: number;
        ghs_per_usd: number;
        ghs_per_rmb?: number;
        fee_usd: number;
        usd_payout: number;
        ghs_payout: number;
        payout_currency: string;
        payout_amount: number;
        breakdown: Record<string, string>;
    };
    receive_method: {
        name: string;
        account_number: string | null;
        type: string;
        qr_url?: string | null;
    } | null;
    payment_reference: string | null;
    payment_proof_url: string | null;
    rejection_reason: string | null;
    payout_amount: number | null;
    payout_ref: string | null;
    payout_channel: string | null;
    can_cancel?: boolean;
    user: { id: number; name: string; email: string; mobile: string | null } | null;
    fields: { id: number; name?: string | null; label: string; value: string | null; file_url: string | null }[];
    proofs: { id: number; type: string; url: string; original_name: string | null }[];
    history: { to_label: string; note: string | null; actor: string | null; created_at: string | null }[];
    admin_notes: { id: number; note: string; admin: string | null; created_at: string | null }[];
};

interface Props {
    transfer: Transfer;
}

const TERMINAL = ['completed', 'cancelled', 'rejected', 'failed'];

export default function SellRmbShow({ transfer }: Props) {
    const { flash } = usePage<SharedData>().props;
    const rejectForm = useForm({ reason: '' });
    const failForm = useForm({ reason: '' });
    const noteForm = useForm({ note: '' });
    const paidForm = useForm({
        payout_amount: String(transfer.quote.payout_amount),
        payout_ref: '',
        payout_channel: '',
        note: '',
        proof: null as File | null,
    });
    const proofInputRef = useRef<HTMLInputElement>(null);
    const [proofPreviewUrl, setProofPreviewUrl] = useState<string | null>(null);

    const autoRefresh = !TERMINAL.includes(transfer.status);

    useEffect(() => {
        if (!autoRefresh) return;
        const id = window.setInterval(() => {
            router.reload({ only: ['transfer'], preserveScroll: true, preserveState: true });
        }, 8000);
        return () => window.clearInterval(id);
    }, [autoRefresh, transfer.id, transfer.status]);

    useEffect(() => {
        const file = paidForm.data.proof;
        if (!file || !file.type.startsWith('image/')) {
            setProofPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setProofPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [paidForm.data.proof]);

    const post = (url: string) => (e: React.FormEvent) => {
        e.preventDefault();
        rejectForm.post(url);
    };

    const payoutLabel =
        transfer.quote.payout_currency === 'ghs'
            ? `GH₵${transfer.quote.ghs_payout.toFixed(2)}`
            : `$${transfer.quote.usd_payout.toFixed(2)}`;

    const submitPaid: FormEventHandler = (e) => {
        e.preventDefault();
        paidForm.post(route('admin.sell-rmb.approve-payout', transfer.id), {
            forceFormData: Boolean(paidForm.data.proof),
        });
    };

    const awaitingReview = ['submitted', 'rmb_verification'].includes(transfer.status);
    const sendMomo = ['rmb_received', 'payout_processing', 'paid'].includes(transfer.status);

    return (
        <AdminLayout title={transfer.reference} active="sell-rmb">
            <Head title={transfer.reference} />
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href={route('admin.sell-rmb.index')} className="text-sm font-semibold text-orange-600">
                        ← All Sell RMB
                    </Link>
                    {autoRefresh && (
                        <div className="flex items-center gap-2">
                            <RmbAutoRefreshChip />
                            <button
                                type="button"
                                onClick={() => router.reload({ only: ['transfer'], preserveScroll: true, preserveState: true })}
                                className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                            >
                                Refresh now
                            </button>
                        </div>
                    )}
                </div>
                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</p>}

                <div className="rounded-2xl border border-gray-200 bg-white p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{transfer.reference}</h1>
                            <p className="text-sm text-emerald-700">{transfer.status_label}</p>
                            <p className="mt-1 text-sm text-gray-600">
                                {transfer.user?.name} · {transfer.user?.mobile} · {transfer.user?.email}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-lg font-black text-emerald-800">¥{transfer.quote.rmb_amount.toFixed(2)}</p>
                            <p className="text-sm font-semibold">Payout {payoutLabel}</p>
                            <p className="text-xs text-gray-500">
                                {transfer.quote.breakdown?.rate ??
                                    `1 RMB = GH₵${(transfer.quote.ghs_per_rmb ?? transfer.quote.usd_per_rmb * transfer.quote.ghs_per_usd).toFixed(4)}`}
                            </p>
                        </div>
                    </div>
                </div>

                {transfer.receive_method?.qr_url && (
                    <div className="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 className="text-lg font-bold text-gray-900">
                            {transfer.receive_method.name} · Buyer sends RMB here
                        </h2>
                        <p className="mt-1 text-sm text-gray-600">Scan or save this Alipay QR when verifying buyer payment.</p>
                        <div className="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                            <a href={transfer.receive_method.qr_url} target="_blank" rel="noreferrer" className="block shrink-0">
                                <img
                                    src={transfer.receive_method.qr_url}
                                    alt="CityShop Alipay QR"
                                    className="max-h-72 w-72 rounded-xl border border-gray-100 bg-slate-50 object-contain p-4"
                                />
                            </a>
                            <div className="text-sm text-gray-600">
                                <p>{transfer.receive_method.account_number}</p>
                                <Button type="button" variant="outline" className="mt-3" asChild>
                                    <a href={transfer.receive_method.qr_url} target="_blank" rel="noreferrer">
                                        View full size
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold">Buyer details</h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            {transfer.fields
                                .filter((field) => {
                                    // Same file is also exposed as payment_proof_url — show once.
                                    if (!transfer.payment_proof_url || !field.file_url) return true;
                                    const name = `${field.name ?? ''} ${field.label ?? ''}`.toLowerCase();
                                    return !(name.includes('screenshot') || name.includes('proof') || name === 'payment_screenshot');
                                })
                                .map((field) => (
                                <div key={field.id}>
                                    <dt className="text-gray-500">{field.label}</dt>
                                    <dd>
                                        {field.file_url ? (
                                            <a href={field.file_url} target="_blank" rel="noreferrer" className="font-semibold text-orange-700">
                                                Open file
                                            </a>
                                        ) : (
                                            field.value || '—'
                                        )}
                                    </dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-gray-500">Receive method</dt>
                                <dd>
                                    {transfer.receive_method?.name} {transfer.receive_method?.account_number}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Payment reference</dt>
                                <dd>{transfer.payment_reference || '—'}</dd>
                            </div>
                            {transfer.payment_proof_url && (
                                <div>
                                    <dt className="text-gray-500">RMB payment screenshot</dt>
                                    <dd className="mt-2 space-y-2">
                                        <a href={transfer.payment_proof_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-xl border">
                                            <img
                                                src={transfer.payment_proof_url}
                                                alt="RMB payment screenshot"
                                                className="max-h-56 w-full bg-slate-50 object-contain"
                                            />
                                        </a>
                                        <a href={transfer.payment_proof_url} target="_blank" rel="noreferrer" className="font-semibold text-orange-700">
                                            Open full size
                                        </a>
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    <div className="space-y-4">
                        {awaitingReview && (
                            <div className="space-y-3 rounded-2xl border border-blue-200 bg-blue-50/50 p-4">
                                <p className="text-sm text-blue-900">
                                    <strong>Step 1 — Process:</strong> Verify Alipay proof, then mark processing so you can send MoMo.
                                </p>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        rejectForm.post(route('admin.sell-rmb.mark-processing', transfer.id));
                                    }}
                                >
                                    <Button className="w-full bg-blue-600 hover:bg-blue-700">Process</Button>
                                </form>
                                <form className="space-y-2" onSubmit={post(route('admin.sell-rmb.reject', transfer.id))}>
                                    <Input
                                        placeholder="Reject reason"
                                        value={rejectForm.data.reason}
                                        onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                    />
                                    <Button variant="outline" className="w-full text-red-700">
                                        Reject
                                    </Button>
                                </form>
                            </div>
                        )}

                        {sendMomo && (
                            <div className="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
                                <p className="text-sm text-emerald-900">
                                    <strong>Step 2 — Complete:</strong> Send {payoutLabel} via MoMo, then complete. Proof is optional.
                                </p>
                                <form className="space-y-3" onSubmit={submitPaid}>
                                    <div>
                                        <Label>Amount paid (optional)</Label>
                                        <Input
                                            className="mt-1 bg-white"
                                            value={paidForm.data.payout_amount}
                                            onChange={(e) => paidForm.setData('payout_amount', e.target.value)}
                                        />
                                        <InputError message={paidForm.errors.payout_amount} />
                                    </div>
                                    <div>
                                        <Label>Payout reference (optional)</Label>
                                        <Input
                                            className="mt-1 bg-white"
                                            value={paidForm.data.payout_ref}
                                            onChange={(e) => paidForm.setData('payout_ref', e.target.value)}
                                        />
                                    </div>
                                    {!paidForm.data.proof ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full border-emerald-300"
                                            onClick={() => proofInputRef.current?.click()}
                                        >
                                            Add MoMo proof (optional)
                                        </Button>
                                    ) : (
                                        <div className="rounded-xl border border-emerald-200 bg-white p-3">
                                            {proofPreviewUrl && (
                                                <img
                                                    src={proofPreviewUrl}
                                                    alt="Payout proof preview"
                                                    className="max-h-64 w-full rounded-lg object-contain"
                                                />
                                            )}
                                            <p className="mt-2 text-sm font-semibold">{paidForm.data.proof.name}</p>
                                            <div className="mt-2 flex gap-2">
                                                <Button type="button" variant="outline" size="sm" onClick={() => proofInputRef.current?.click()}>
                                                    Change
                                                </Button>
                                                <Button type="button" variant="outline" size="sm" onClick={() => paidForm.setData('proof', null)}>
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                    <input
                                        ref={proofInputRef}
                                        className="hidden"
                                        type="file"
                                        accept="image/*,.pdf"
                                        onChange={(e) => paidForm.setData('proof', e.target.files?.[0] ?? null)}
                                    />
                                    <InputError message={paidForm.errors.proof} />
                                    <Button className="w-full bg-emerald-600 hover:bg-emerald-700" disabled={paidForm.processing}>
                                        {paidForm.processing ? 'Completing…' : 'Complete'}
                                    </Button>
                                </form>
                                <form className="space-y-2" onSubmit={post(route('admin.sell-rmb.reject', transfer.id))}>
                                    <Input
                                        placeholder="Reject reason"
                                        value={rejectForm.data.reason}
                                        onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                    />
                                    <Button variant="outline" className="w-full text-red-700">
                                        Reject
                                    </Button>
                                </form>
                            </div>
                        )}

                        {['rmb_received', 'payout_processing', 'paid'].includes(transfer.status) && (
                            <form
                                className="space-y-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    failForm.post(route('admin.sell-rmb.fail', transfer.id));
                                }}
                            >
                                <Input
                                    placeholder="Fail reason"
                                    value={failForm.data.reason}
                                    onChange={(e) => failForm.setData('reason', e.target.value)}
                                />
                                <Button variant="outline" className="w-full">
                                    Mark failed
                                </Button>
                            </form>
                        )}
                    </div>
                </div>

                {transfer.proofs.length > 0 && (
                    <div className="rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold">Payout proofs</h2>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {transfer.proofs.map((proof) => (
                                <a key={proof.id} href={proof.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-xl border">
                                    <img src={proof.url} alt={proof.original_name || 'Proof'} className="max-h-48 w-full object-contain bg-slate-50" />
                                    <p className="px-3 py-2 text-sm font-semibold text-orange-700">
                                        {proof.type}: {proof.original_name || 'Open'}
                                    </p>
                                </a>
                            ))}
                        </div>
                    </div>
                )}

                <div className="rounded-2xl border border-gray-200 bg-white p-4">
                    <h2 className="font-bold">Internal notes</h2>
                    <ul className="mt-3 space-y-2 text-sm">
                        {transfer.admin_notes.map((note) => (
                            <li key={note.id}>
                                <span className="font-semibold">{note.admin}:</span> {note.note}
                            </li>
                        ))}
                    </ul>
                    <form
                        className="mt-3 flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            noteForm.post(route('admin.sell-rmb.note', transfer.id), {
                                onSuccess: () => noteForm.reset(),
                            });
                        }}
                    >
                        <Input value={noteForm.data.note} onChange={(e) => noteForm.setData('note', e.target.value)} placeholder="Add note" />
                        <Button type="submit">Save</Button>
                    </form>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-4">
                    <h2 className="font-bold">Status history</h2>
                    <ul className="mt-3 space-y-2 text-sm">
                        {transfer.history.map((row, i) => (
                            <li key={i}>
                                <span className="font-semibold">{row.to_label}</span>
                                {row.note ? ` — ${row.note}` : ''}
                                <span className="text-gray-500"> · {row.actor}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AdminLayout>
    );
}
