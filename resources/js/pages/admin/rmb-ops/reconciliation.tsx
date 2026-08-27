import { Head, Link } from '@inertiajs/react';

import AdminLayout from '@/layouts/admin-layout';
import { formatPrice } from '@/types/marketplace';

interface Props {
    summary: {
        total_rmb_balances: number;
        total_ghs_available: number;
        open_rmb_holds: number;
        open_hold_count: number;
        float_check: number;
        today_converts: number;
        today_rmb_out: number;
    };
    openTransfers: Array<{
        id: number;
        reference: string;
        status_label: string;
        rmb_amount: number;
        needs_approval: boolean;
        user: string | null;
        created_at: string | null;
    }>;
}

export default function RmbReconciliation({ summary, openTransfers }: Props) {
    return (
        <AdminLayout title="RMB reconciliation" active="rmb-reconciliation">
            <Head title="RMB reconciliation" />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-lg font-bold text-gray-900">RMB float reconciliation</h1>
                    <p className="text-sm text-gray-500">
                        Spendable wallet RMB + open Alipay holds should match operational float.
                    </p>
                </div>
                <Link href={route('admin.rmb-ops.conversions')} className="text-sm text-orange-600 hover:underline">
                    Conversions
                </Link>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Wallet RMB balances" value={`¥${summary.total_rmb_balances.toFixed(2)}`} />
                <Stat label="Open Alipay holds" value={`¥${summary.open_rmb_holds.toFixed(2)}`} hint={`${summary.open_hold_count} tickets`} />
                <Stat label="Float check (balances + holds)" value={`¥${summary.float_check.toFixed(2)}`} />
                <Stat label="GHS available (all wallets)" value={formatPrice(summary.total_ghs_available)} />
                <Stat label="Converts today" value={String(summary.today_converts)} />
                <Stat label="RMB out today" value={`¥${summary.today_rmb_out.toFixed(2)}`} />
            </div>

            <h2 className="mt-8 font-bold text-gray-900">Open wallet-funded transfers</h2>
            <div className="mt-3 space-y-2">
                {openTransfers.length === 0 ? (
                    <p className="text-sm text-gray-500">No open holds.</p>
                ) : (
                    openTransfers.map((t) => (
                        <Link
                            key={t.id}
                            href={route('admin.china-transfers.show', t.id)}
                            className="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3"
                        >
                            <div>
                                <p className="font-semibold">{t.reference}</p>
                                <p className="text-sm text-gray-500">
                                    {t.user} · {t.status_label}
                                    {t.needs_approval ? ' · needs approval' : ''}
                                </p>
                            </div>
                            <p className="font-bold">¥{t.rmb_amount.toFixed(2)}</p>
                        </Link>
                    ))
                )}
            </div>
        </AdminLayout>
    );
}

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4">
            <p className="text-xs font-semibold text-gray-500">{label}</p>
            <p className="mt-1 text-xl font-black text-gray-900">{value}</p>
            {hint && <p className="text-xs text-gray-400">{hint}</p>}
        </div>
    );
}
