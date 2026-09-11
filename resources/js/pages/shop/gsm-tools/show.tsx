import { Head, router, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';
import { formatPrice } from '@/types/marketplace';

type Order = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    service_name: string;
    price_ghs: number;
    admin_result_note: string | null;
    failure_reason: string | null;
    can_cancel: boolean;
    fields: { name: string; label: string; value: string | null }[];
    history?: { to_status: string; note: string | null; created_at: string | null }[];
};

interface Props {
    order: Order;
}

export default function GsmToolShow({ order }: Props) {
    const { flash } = usePage<SharedData>().props;

    return (
        <ShopLayout>
            <Head title={order.reference} />
            <div className="mx-auto max-w-lg px-4 py-6">
                <button type="button" onClick={() => router.visit(route('gsm-tools.index'))} className="mb-3 text-sm text-orange-600">
                    ← GSM Tools
                </button>
                <h1 className="text-xl font-bold text-gray-900">{order.service_name}</h1>
                <p className="mt-1 text-sm text-gray-500">{order.reference}</p>
                <p className="mt-2 text-sm font-semibold text-orange-600">{order.status_label}</p>
                <p className="mt-1 text-sm text-gray-700">Paid {formatPrice(order.price_ghs)}</p>

                {(flash?.success || flash?.error) && (
                    <div className={`mt-3 rounded-xl border px-3 py-2 text-sm ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success ?? flash.error}
                    </div>
                )}

                <div className="mt-5 space-y-2 rounded-2xl border border-gray-100 bg-white p-4">
                    {order.fields.map((field) => (
                        <div key={field.name}>
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">{field.label}</p>
                            <p className="text-sm text-gray-900">{field.value || '—'}</p>
                        </div>
                    ))}
                </div>

                {order.admin_result_note ? (
                    <div className="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        {order.admin_result_note}
                    </div>
                ) : null}
                {order.failure_reason ? (
                    <div className="mt-4 rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {order.failure_reason}
                    </div>
                ) : null}

                {order.can_cancel ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="mt-5 w-full"
                        onClick={() => {
                            if (confirm('Cancel this order and refund your wallet?')) {
                                router.post(route('gsm-tools.orders.cancel', order.id));
                            }
                        }}
                    >
                        Cancel &amp; refund
                    </Button>
                ) : null}
            </div>
        </ShopLayout>
    );
}
