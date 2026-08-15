import { Head, Link, useForm, usePage } from '@inertiajs/react';

import InputError from '@/components/input-error';
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
        fee_usd: number;
        usd_payout: number;
        ghs_payout: number;
        payout_currency: string;
        payout_amount: number;
    };
    receive_method: { name: string; account_number: string | null; type: string } | null;
    payment_reference: string | null;
    payment_proof_url: string | null;
    rejection_reason: string | null;
    payout_amount: number | null;
    payout_ref: string | null;
    payout_channel: string | null;
    user: { id: number; name: string; email: string; mobile: string | null } | null;
    fields: { id: number; label: string; value: string | null; file_url: string | null }[];
    proofs: { id: number; type: string; url: string; original_name: string | null }[];
    history: { to_label: string; note: string | null; actor: string | null; created_at: string | null }[];
    admin_notes: { id: number; note: string; admin: string | null; created_at: string | null }[];
};

interface Props {
    transfer: Transfer;
}

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

    const post = (url: string) => (e: React.FormEvent) => {
        e.preventDefault();
        rejectForm.post(url);
    };

    const payoutLabel =
        transfer.quote.payout_currency === 'ghs'
            ? `GH₵${transfer.quote.ghs_payout.toFixed(2)}`
            : `$${transfer.quote.usd_payout.toFixed(2)}`;

    return (
        <AdminLayout title={transfer.reference} active="sell-rmb">
            <Head title={transfer.reference} />
            <div className="mx-auto max-w-4xl space-y-6">
                <Link href={route('admin.sell-rmb.index')} className="text-sm font-semibold text-orange-600">
                    ← All Sell RMB
                </Link>
                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</p>}

                <div className="rounded-2xl border border-gray-200 bg-white p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{transfer.reference}</h1>
                            <p className="text-sm text-orange-700">{transfer.status_label}</p>
                            <p className="mt-1 text-sm text-gray-600">
                                {transfer.user?.name} · {transfer.user?.mobile} · {transfer.user?.email}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-lg font-black">¥{transfer.quote.rmb_amount.toFixed(2)}</p>
                            <p className="text-sm">{payoutLabel}</p>
                            <p className="text-xs text-gray-500">1 RMB = ${transfer.quote.usd_per_rmb.toFixed(6)}</p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold">Buyer details</h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            {transfer.fields.map((field) => (
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
                                <a href={transfer.payment_proof_url} target="_blank" rel="noreferrer" className="font-semibold text-orange-700">
                                    RMB payment screenshot
                                </a>
                            )}
                        </dl>
                    </div>

                    <div className="space-y-4">
                        {transfer.status === 'submitted' && (
                            <div className="rounded-2xl border border-gray-200 bg-white p-4 space-y-3">
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        rejectForm.transform(() => ({}));
                                        rejectForm.post(route('admin.sell-rmb.verify', transfer.id));
                                    }}
                                >
                                    <Button className="w-full bg-emerald-600 hover:bg-emerald-700">Start RMB verification</Button>
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

                        {transfer.status === 'rmb_verification' && (
                            <div className="rounded-2xl border border-gray-200 bg-white p-4 space-y-3">
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        rejectForm.post(route('admin.sell-rmb.received', transfer.id));
                                    }}
                                >
                                    <Button className="w-full bg-emerald-600 hover:bg-emerald-700">Mark RMB received</Button>
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

                        {transfer.status === 'rmb_received' && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    rejectForm.post(route('admin.sell-rmb.process', transfer.id));
                                }}
                            >
                                <Button className="w-full">Start payout processing</Button>
                            </form>
                        )}

                        {transfer.status === 'payout_processing' && (
                            <>
                            <form
                                className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    paidForm.post(route('admin.sell-rmb.paid', transfer.id), { forceFormData: true });
                                }}
                            >
                                <h2 className="font-bold">Mark payout paid</h2>
                                <p className="text-xs text-gray-500">
                                    Buyer already sent RMB (no wallet). Verify proof, then pay GHS and upload payout proof.
                                </p>
                                <div>
                                    <Label>Amount paid</Label>
                                    <Input
                                        className="mt-1"
                                        value={paidForm.data.payout_amount}
                                        onChange={(e) => paidForm.setData('payout_amount', e.target.value)}
                                    />
                                    <InputError message={paidForm.errors.payout_amount} />
                                </div>
                                <div>
                                    <Label>Payout reference</Label>
                                    <Input
                                        className="mt-1"
                                        value={paidForm.data.payout_ref}
                                        onChange={(e) => paidForm.setData('payout_ref', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label>Channel (optional)</Label>
                                    <Input
                                        className="mt-1"
                                        value={paidForm.data.payout_channel}
                                        onChange={(e) => paidForm.setData('payout_channel', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label>Proof image or PDF</Label>
                                    <input
                                        className="mt-1 block w-full text-sm"
                                        type="file"
                                        accept="image/*,.pdf"
                                        onChange={(e) => paidForm.setData('proof', e.target.files?.[0] ?? null)}
                                    />
                                    <InputError message={paidForm.errors.proof} />
                                </div>
                                <Button className="w-full bg-orange-500 hover:bg-orange-600">Upload proof & mark paid</Button>
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
                            </>
                        )}

                        {transfer.status === 'paid' && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    rejectForm.post(route('admin.sell-rmb.complete', transfer.id));
                                }}
                            >
                                <Button className="w-full bg-emerald-600 hover:bg-emerald-700">Complete</Button>
                            </form>
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
                        <h2 className="font-bold">Proofs</h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            {transfer.proofs.map((proof) => (
                                <li key={proof.id}>
                                    <a href={proof.url} target="_blank" rel="noreferrer" className="font-semibold text-orange-700">
                                        {proof.type}: {proof.original_name || 'Open'}
                                    </a>
                                </li>
                            ))}
                        </ul>
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
