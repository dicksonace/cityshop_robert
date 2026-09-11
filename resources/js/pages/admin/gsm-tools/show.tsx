import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    price_ghs: number;
    can_cancel: boolean;
    admin_result_note: string | null;
    failure_reason: string | null;
    fields: { name: string; label: string; value: string | null }[];
    user?: { id: number; name: string; email: string | null; mobile: string | null } | null;
    history?: { to_status: string; note: string | null; actor?: string | null; created_at: string | null }[];
};

interface Props {
    order: Order;
}

export default function AdminGsmToolShow({ order }: Props) {
    const { flash } = usePage<SharedData>().props;
    const completeForm = useForm({ result_note: order.admin_result_note ?? '' });
    const failForm = useForm({ reason: '' });

    const complete: FormEventHandler = (e) => {
        e.preventDefault();
        completeForm.post(route('admin.gsm-tools.complete', order.id));
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
                    <h1 className="text-lg font-bold text-gray-900">{order.service_name}</h1>
                    <p className="text-sm text-gray-500">{order.reference}</p>
                    <p className="mt-2 text-sm font-semibold text-orange-600">{order.status_label}</p>
                    <p className="mt-1 text-sm">{formatPrice(order.price_ghs)} · {order.user?.name}</p>
                    <p className="text-xs text-gray-500">{order.user?.mobile} · {order.user?.email}</p>

                    {(flash?.success || flash?.error) && (
                        <div className={`mt-3 rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                            {flash.success ?? flash.error}
                        </div>
                    )}

                    <div className="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        {order.fields.map((field) => (
                            <div key={field.name}>
                                <p className="text-xs font-semibold uppercase text-gray-400">{field.label}</p>
                                <p className="text-sm text-gray-900 break-all">{field.value || '—'}</p>
                            </div>
                        ))}
                    </div>
                </div>

                {order.status === 'pending' || order.status === 'processing' ? (
                    <div className="space-y-3 rounded-2xl border border-gray-100 bg-white p-5">
                        {order.status === 'pending' ? (
                            <Button type="button" className="w-full" onClick={() => router.post(route('admin.gsm-tools.process', order.id))}>
                                Start processing
                            </Button>
                        ) : null}

                        <form onSubmit={complete} className="space-y-2">
                            <Label>Result note</Label>
                            <Input value={completeForm.data.result_note} onChange={(e) => completeForm.setData('result_note', e.target.value)} />
                            <Button type="submit" className="w-full bg-emerald-600 hover:bg-emerald-700" disabled={completeForm.processing}>
                                Complete
                            </Button>
                        </form>

                        <form onSubmit={fail} className="space-y-2">
                            <Label>Fail reason (refunds wallet)</Label>
                            <Input value={failForm.data.reason} onChange={(e) => failForm.setData('reason', e.target.value)} />
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
            </div>
        </AdminLayout>
    );
}
