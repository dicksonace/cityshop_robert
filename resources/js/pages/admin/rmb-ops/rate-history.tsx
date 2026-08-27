import { Head, Link } from '@inertiajs/react';

import AdminLayout from '@/layouts/admin-layout';

type RateRow = {
    id: number;
    side: string;
    ghs_per_rmb: number;
    usd_per_rmb?: number;
    ghs_per_usd?: number;
    active: boolean;
    effective_from: string | null;
    effective_to: string | null;
    created_by: string | null;
    created_at: string | null;
};

interface Props {
    buyRates: RateRow[];
    sellRates: RateRow[];
}

export default function RateHistory({ buyRates, sellRates }: Props) {
    return (
        <AdminLayout title="RMB rate history" active="rmb-rate-history">
            <Head title="RMB rate history" />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-lg font-bold text-gray-900">Rate change log</h1>
                    <p className="text-sm text-gray-500">Published buy (China) and sell rates — who and when.</p>
                </div>
                <Link href={route('admin.china-transfer.settings')} className="text-sm text-orange-600 hover:underline">
                    Publish buy rate
                </Link>
            </div>

            <Section title="Buy / convert GHS→RMB (China rates)" rows={buyRates} />
            <Section title="Sell / convert RMB→GHS" rows={sellRates} className="mt-8" />
        </AdminLayout>
    );
}

function Section({ title, rows, className }: { title: string; rows: RateRow[]; className?: string }) {
    return (
        <div className={className}>
            <h2 className="font-bold text-gray-900">{title}</h2>
            <div className="mt-3 overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="border-b bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th className="px-4 py-3">Published</th>
                            <th className="px-4 py-3">1 RMB =</th>
                            <th className="px-4 py-3">By</th>
                            <th className="px-4 py-3">Active</th>
                            <th className="px-4 py-3">Window</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-gray-500">
                                    No rates yet.
                                </td>
                            </tr>
                        ) : (
                            rows.map((r) => (
                                <tr key={`${r.side}-${r.id}`}>
                                    <td className="px-4 py-3">
                                        {r.created_at ? new Date(r.created_at).toLocaleString('en-GH') : '—'}
                                    </td>
                                    <td className="px-4 py-3 font-semibold">GH₵{r.ghs_per_rmb.toFixed(4)}</td>
                                    <td className="px-4 py-3">{r.created_by ?? '—'}</td>
                                    <td className="px-4 py-3">{r.active ? 'Live' : 'Ended'}</td>
                                    <td className="px-4 py-3 text-xs text-gray-500">
                                        {r.effective_from ? new Date(r.effective_from).toLocaleDateString('en-GH') : '—'}
                                        {' → '}
                                        {r.effective_to ? new Date(r.effective_to).toLocaleDateString('en-GH') : 'open'}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
