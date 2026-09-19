import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Reply = { id: number; body: string; admin: string | null; created_at: string | null };
type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    status_tone?: string;
    service_name: string;
    price_ghs: number;
    can_cancel: boolean;
    admin_result_note: string | null;
    failure_reason: string | null;
    fields: { name: string; label: string; value: string | null }[];
    replies?: Reply[];
    user?: { id: number; name: string; email: string | null; mobile: string | null } | null;
    history?: { to_status: string; note: string | null; actor?: string | null; created_at: string | null }[];
};

interface Props {
    order: Order;
}

function statusClass(status: string): string {
    return {
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-600',
    }[status] ?? 'bg-gray-100 text-gray-700';
}

export default function AdminGsmToolShow({ order }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [completing, setCompleting] = useState(false);
    const replyForm = useForm({ message: '' });
    const completeForm = useForm({ result_note: '' });
    const failForm = useForm({ reason: '' });
    const open = order.status === 'pending' || order.status === 'processing';
    const replies = order.replies ?? [];

    const sendReply: FormEventHandler = (e) => {
        e.preventDefault();
        replyForm.post(route('admin.gsm-tools.reply', order.id), {
            onSuccess: () => replyForm.reset('message'),
        });
    };

    const complete: FormEventHandler = (e) => {
        e.preventDefault();
        const note = completeForm.data.result_note.trim() || replyForm.data.message.trim();
        router.post(
            route('admin.gsm-tools.complete', order.id),
            { result_note: note },
            {
                onStart: () => setCompleting(true),
                onFinish: () => setCompleting(false),
                onSuccess: () => {
                    completeForm.reset('result_note');
                    replyForm.reset('message');
                },
            },
        );
    };

    const fail: FormEventHandler = (e) => {
        e.preventDefault();
        failForm.post(route('admin.gsm-tools.fail', order.id));
    };

    return (
        <AdminLayout title={order.reference} active="gsm-tools">
            <Head title={order.reference} />
            <div className="mx-auto max-w-2xl space-y-4">
                <button type="button" className="text-sm text-orange-600" onClick={() => router.visit(route('admin.gsm-tools.index'))}>
                    ← Orders
                </button>

                <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h1 className="text-lg font-bold text-gray-900">{order.service_name}</h1>
                            <p className="text-sm text-gray-500">{order.reference}</p>
                        </div>
                        <span className={`rounded-full px-3 py-1 text-xs font-extrabold uppercase tracking-wide ${statusClass(order.status)}`}>
                            {order.status_label}
                        </span>
                    </div>
                    <p className="mt-2 text-sm">
                        {formatPrice(order.price_ghs)} · {order.user?.name}
                    </p>
                    <p className="text-xs text-gray-500">
                        {order.user?.mobile} · {order.user?.email}
                    </p>

                    {(flash?.success || flash?.error) && (
                        <div
                            className={`mt-3 rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}
                        >
                            {flash.success ?? flash.error}
                        </div>
                    )}

                    <div className="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        {order.fields.map((field) => (
                            <div key={field.name}>
                                <p className="text-xs font-semibold uppercase text-gray-400">{field.label}</p>
                                <p className="break-all text-sm text-gray-900">{field.value || '—'}</p>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-2xl border border-gray-100 bg-white p-5">
                    <h2 className="text-sm font-bold uppercase tracking-wide text-gray-500">Admin reply</h2>
                    <p className="mt-1 text-xs text-gray-500">Buyer sees these messages, same as FRP GH / unlock shops.</p>
                    <div className="mt-3 space-y-2">
                        {replies.length === 0 ? (
                            <p className="rounded-xl border border-dashed border-gray-200 px-3 py-4 text-sm text-gray-500">
                                No reply yet. Send a message, then mark Processing or Complete.
                            </p>
                        ) : (
                            replies.map((reply) => (
                                <div key={reply.id} className="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2">
                                    <p className="whitespace-pre-wrap text-sm text-emerald-950">{reply.body}</p>
                                    <p className="mt-1 text-[11px] text-emerald-700">
                                        {reply.admin ?? 'Admin'}
                                        {reply.created_at ? ` · ${new Date(reply.created_at).toLocaleString()}` : ''}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {open ? (
                    <div className="space-y-3 rounded-2xl border border-gray-100 bg-white p-5">
                        {order.status === 'pending' ? (
                            <Button type="button" className="w-full bg-blue-600 hover:bg-blue-700" onClick={() => router.post(route('admin.gsm-tools.process', order.id))}>
                                Processing
                            </Button>
                        ) : (
                            <p className="text-center text-xs font-bold uppercase tracking-wide text-blue-700">Currently Processing</p>
                        )}

                        <form onSubmit={sendReply} className="space-y-2">
                            <Label>Reply message</Label>
                            <textarea
                                className="min-h-[110px] w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                placeholder="SUCCESS — device unlocked. Or paste the code / next steps for the buyer."
                                value={replyForm.data.message}
                                onChange={(e) => replyForm.setData('message', e.target.value)}
                            />
                            {replyForm.errors.message ? <p className="text-xs text-red-600">{replyForm.errors.message}</p> : null}
                            <Button type="submit" variant="outline" className="w-full" disabled={replyForm.processing}>
                                Send reply
                            </Button>
                        </form>

                        <form onSubmit={complete} className="space-y-2">
                            <Label>Complete reply (required if you have not sent one yet)</Label>
                            <textarea
                                className="min-h-[80px] w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                placeholder="Optional if you already sent a reply. Buyer will see this as Completed."
                                value={completeForm.data.result_note}
                                onChange={(e) => completeForm.setData('result_note', e.target.value)}
                            />
                            {completeForm.errors.result_note ? <p className="text-xs text-red-600">{completeForm.errors.result_note}</p> : null}
                            <Button type="submit" className="w-full bg-emerald-600 hover:bg-emerald-700" disabled={completing}>
                                Complete
                            </Button>
                        </form>

                        <form onSubmit={fail} className="space-y-2">
                            <Label>Fail reason (refunds wallet)</Label>
                            <textarea
                                className="min-h-[70px] w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                value={failForm.data.reason}
                                onChange={(e) => failForm.setData('reason', e.target.value)}
                            />
                            <Button type="submit" variant="outline" className="w-full" disabled={failForm.processing}>
                                Fail &amp; refund
                            </Button>
                        </form>

                        <Button
                            type="button"
                            variant="outline"
                            className="w-full text-red-600"
                            onClick={() => {
                                if (confirm('Cancel and refund buyer wallet?')) {
                                    router.post(route('admin.gsm-tools.cancel', order.id));
                                }
                            }}
                        >
                            Cancel &amp; refund
                        </Button>
                    </div>
                ) : null}

                {order.failure_reason ? (
                    <div className="rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800">{order.failure_reason}</div>
                ) : null}
            </div>
        </AdminLayout>
    );
}
