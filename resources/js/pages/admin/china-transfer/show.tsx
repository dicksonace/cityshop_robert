import { Head, Link, useForm, usePage } from '@inertiajs/react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type TransferField = {
    id: number;
    name?: string | null;
    label: string;
    type?: string | null;
    group?: string | null;
    value: string | null;
    file_url: string | null;
};

type Transfer = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    funding_source?: string;
    funding_source_label?: string;
    needs_approval?: boolean;
    ip_address?: string | null;
    user_agent?: string | null;
    quote: {
        ghs_amount: number;
        ghs_per_rmb: number;
        rmb_amount: number;
        fee_ghs: number;
        total_payable_ghs: number;
    };
    payment_method: { name: string; account_number: string | null } | null;
    payment_reference: string | null;
    payment_proof_url: string | null;
    rejection_reason: string | null;
    rmb_sent_amount: number | null;
    rmb_transfer_ref: string | null;
    user: { id: number; name: string; email: string; mobile: string | null } | null;
    fields: TransferField[];
    proofs: { id: number; type: string; url: string; original_name: string | null }[];
    history: { to_label: string; note: string | null; actor: string | null; created_at: string | null }[];
    admin_notes: { id: number; note: string; admin: string | null; created_at: string | null }[];
};

interface Props {
    transfer: Transfer;
}

function isQrField(field: TransferField): boolean {
    if (!field.file_url) {
        return false;
    }
    const blob = `${field.name ?? ''} ${field.label ?? ''}`.toLowerCase();
    const type = (field.type ?? '').toLowerCase();
    return ['image', 'document', 'files'].includes(type) || blob.includes('qr');
}

async function downloadQr(url: string, reference: string) {
    const res = await fetch(url);
    if (!res.ok) {
        throw new Error('Could not download QR');
    }
    const blob = await res.blob();
    const ext = url.toLowerCase().includes('.png') ? 'png' : 'jpg';
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = `alipay_qr_${reference}.${ext}`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(objectUrl);
}

export default function ChinaTransferShow({ transfer }: Props) {
    const { flash } = usePage<SharedData>().props;
    const rejectForm = useForm({ reason: '' });
    const failForm = useForm({ reason: '' });
    const noteForm = useForm({ note: '' });
    const sentForm = useForm({
        rmb_sent_amount: String(transfer.quote.rmb_amount),
        rmb_transfer_ref: '',
        note: '',
        proof: null as File | null,
    });

    const post = (url: string) => (e: React.FormEvent) => {
        e.preventDefault();
        rejectForm.post(url);
    };

    const qrFields = transfer.fields.filter(isQrField);
    const textFields = transfer.fields.filter((field) => {
        if (isQrField(field)) {
            return false;
        }
        const group = (field.group ?? '').toLowerCase();
        return !['payment', 'payment_proof', 'proof'].includes(group);
    });

    return (
        <AdminLayout title={transfer.reference} active="china-transfers">
            <Head title={transfer.reference} />
            <div className="mx-auto max-w-4xl space-y-6">
                <Link href={route('admin.china-transfers.index')} className="text-sm font-semibold text-orange-600">
                    ← All transfers
                </Link>
                {flash?.success && <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}
                {flash?.error && <p className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</p>}

                <div className="rounded-2xl border border-gray-200 bg-white p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{transfer.reference}</h1>
                            <p className="text-sm text-orange-700">{transfer.status_label}</p>
                            <p className="mt-1 text-xs font-semibold text-teal-700">
                                {transfer.funding_source_label ??
                                    (transfer.funding_source === 'rmb_wallet'
                                        ? 'RMB wallet'
                                        : 'External GHS payment')}
                                {transfer.funding_source === 'rmb_wallet' ? ' · RMB held from wallet' : ''}
                                {transfer.needs_approval ? ' · Needs large-transfer approval' : ''}
                            </p>
                            <p className="mt-1 text-sm text-gray-600">
                                {transfer.user?.name} · {transfer.user?.mobile} · {transfer.user?.email}
                            </p>
                            {(transfer.ip_address || transfer.user_agent) && (
                                <p className="mt-1 text-xs text-gray-500">
                                    IP {transfer.ip_address ?? '—'}
                                    {transfer.user_agent ? ` · ${transfer.user_agent.slice(0, 80)}` : ''}
                                </p>
                            )}
                        </div>
                        <div className="text-right">
                            {transfer.funding_source === 'rmb_wallet' ? (
                                <p className="text-lg font-black">¥{transfer.quote.rmb_amount.toFixed(2)}</p>
                            ) : (
                                <>
                                    <p className="text-lg font-black">{formatPrice(transfer.quote.total_payable_ghs)}</p>
                                    <p className="text-sm">¥{transfer.quote.rmb_amount.toFixed(2)}</p>
                                </>
                            )}
                            <p className="text-xs text-gray-500">1 RMB = GH₵{transfer.quote.ghs_per_rmb.toFixed(4)}</p>
                        </div>
                    </div>
                </div>

                {qrFields.map((field) => (
                    <div key={field.id} className="rounded-2xl border border-gray-200 bg-white p-5">
                        <div className="flex items-center gap-2">
                            <span className="text-xl" aria-hidden>
                                ▦
                            </span>
                            <h2 className="text-lg font-bold text-gray-900">{field.label || 'Alipay QR code'}</h2>
                        </div>
                        <p className="mt-1 text-sm text-gray-600">
                            Scan or save this QR when sending RMB on Alipay.
                        </p>
                        <div className="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                            <a href={field.file_url!} target="_blank" rel="noreferrer" className="block shrink-0">
                                <img
                                    src={field.file_url!}
                                    alt={field.label || 'Alipay QR code'}
                                    className="max-h-72 w-72 rounded-xl border border-gray-100 bg-slate-50 object-contain p-4"
                                />
                            </a>
                            <div className="flex w-full flex-col gap-2 sm:w-auto">
                                <Button
                                    type="button"
                                    className="bg-orange-600 hover:bg-orange-700"
                                    onClick={() => downloadQr(field.file_url!, transfer.reference).catch(() => window.open(field.file_url!, '_blank'))}
                                >
                                    Download QR
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <a href={field.file_url!} target="_blank" rel="noreferrer">
                                        View full size
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                ))}

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-gray-200 bg-white p-4">
                        <h2 className="font-bold">Buyer details</h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            {textFields.map((field) => (
                                <div key={field.id}>
                                    <dt className="text-gray-500">{field.label}</dt>
                                    <dd>{field.value || '—'}</dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-gray-500">Payment method</dt>
                                <dd>
                                    {transfer.payment_method?.name} {transfer.payment_method?.account_number}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Payment reference</dt>
                                <dd>{transfer.payment_reference || '—'}</dd>
                            </div>
                            {transfer.payment_proof_url && (
                                <a href={transfer.payment_proof_url} target="_blank" rel="noreferrer" className="font-semibold text-orange-700">
                                    Payment screenshot
                                </a>
                            )}
                        </dl>
                    </div>

                    <div className="space-y-4">
                        {transfer.status === 'payment_submitted' && (
                            <div className="rounded-2xl border border-gray-200 bg-white p-4">
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        rejectForm.transform(() => ({}));
                                        rejectForm.post(route('admin.china-transfers.verify', transfer.id));
                                    }}
                                >
                                    <Button className="w-full bg-emerald-600 hover:bg-emerald-700">Verify payment</Button>
                                </form>
                                <form className="mt-3 space-y-2" onSubmit={post(route('admin.china-transfers.reject', transfer.id))}>
                                    <Input
                                        placeholder="Reject reason"
                                        value={rejectForm.data.reason}
                                        onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                    />
                                    <Button variant="outline" className="w-full text-red-700">
                                        Reject payment
                                    </Button>
                                </form>
                            </div>
                        )}

                        {['payment_verification', 'payment_submitted'].includes(transfer.status) && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    rejectForm.post(route('admin.china-transfers.process', transfer.id));
                                }}
                            >
                                <Button className="w-full">Start processing</Button>
                            </form>
                        )}

                        {transfer.status === 'processing' && (
                            <form
                                className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    sentForm.post(route('admin.china-transfers.sent', transfer.id), { forceFormData: true });
                                }}
                            >
                                <h2 className="font-bold">Mark RMB sent</h2>
                                <p className="text-xs text-gray-500">Proof is required before this can be completed.</p>
                                <div>
                                    <Label>RMB amount sent</Label>
                                    <Input
                                        className="mt-1"
                                        value={sentForm.data.rmb_sent_amount}
                                        onChange={(e) => sentForm.setData('rmb_sent_amount', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label>Alipay / transfer reference</Label>
                                    <Input
                                        className="mt-1"
                                        value={sentForm.data.rmb_transfer_ref}
                                        onChange={(e) => sentForm.setData('rmb_transfer_ref', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label>Proof image or PDF</Label>
                                    <input
                                        className="mt-1 block w-full text-sm"
                                        type="file"
                                        accept="image/*,.pdf"
                                        onChange={(e) => sentForm.setData('proof', e.target.files?.[0] ?? null)}
                                    />
                                    <InputError message={sentForm.errors.proof} />
                                </div>
                                <Button className="w-full bg-orange-500 hover:bg-orange-600">Upload proof & mark sent</Button>
                            </form>
                        )}

                        {transfer.status === 'rmb_sent' && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    rejectForm.post(route('admin.china-transfers.complete', transfer.id));
                                }}
                            >
                                <Button className="w-full bg-emerald-600 hover:bg-emerald-700">Complete transaction</Button>
                            </form>
                        )}

                        {['processing', 'rmb_sent'].includes(transfer.status) && (
                            <form className="space-y-2" onSubmit={(e) => {
                                e.preventDefault();
                                failForm.post(route('admin.china-transfers.fail', transfer.id));
                            }}>
                                <Input
                                    placeholder="Fail reason"
                                    value={failForm.data.reason}
                                    onChange={(e) => failForm.setData('reason', e.target.value)}
                                />
                                <Button variant="outline" className="w-full">Mark failed</Button>
                            </form>
                        )}
                    </div>
                </div>

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
                            noteForm.post(route('admin.china-transfers.note', transfer.id), {
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
