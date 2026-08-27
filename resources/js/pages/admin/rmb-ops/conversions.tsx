import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

type Row = {
    id: number;
    reference: string;
    direction: string;
    amount_ghs: number;
    amount_rmb: number;
    rate: number;
    status: string;
    ip_address: string | null;
    created_at: string | null;
    user: { id: number; name: string; mobile: string | null } | null;
};

interface Props {
    conversions: Paginated<Row>;
    search?: string | null;
}

export default function RmbConversions({ conversions, search }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [query, setQuery] = useState(search ?? '');

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.rmb-ops.conversions'), { search: query || undefined }, { preserveState: true });
    };

    return (
        <AdminLayout title="RMB conversions" active="rmb-conversions">
            <Head title="RMB conversions" />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-lg font-bold text-gray-900">Wallet conversions</h1>
                    <p className="text-sm text-gray-500">Instant GHS ↔ RMB exchanges with audit IP.</p>
                </div>
                <div className="flex gap-2 text-sm">
                    <Link href={route('admin.rmb-ops.reconciliation')} className="text-orange-600 hover:underline">
                        Reconciliation
                    </Link>
                    <Link href={route('admin.rmb-ops.rate-history')} className="text-orange-600 hover:underline">
                        Rate history
                    </Link>
                </div>
            </div>
            {flash?.success && <p className="mb-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</p>}
            <form onSubmit={submit} className="mb-4 max-w-md">
                <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search ref, name, mobile…" />
            </form>
            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="border-b bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th className="px-4 py-3">When</th>
                            <th className="px-4 py-3">User</th>
                            <th className="px-4 py-3">Direction</th>
                            <th className="px-4 py-3">Amounts</th>
                            <th className="px-4 py-3">Ref / IP</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {conversions.data.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-10 text-center text-gray-500">
                                    No conversions yet.
                                </td>
                            </tr>
                        ) : (
                            conversions.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-3 text-gray-600">
                                        {row.created_at ? new Date(row.created_at).toLocaleString('en-GH') : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{row.user?.name ?? '—'}</p>
                                        <p className="text-xs text-gray-500">{row.user?.mobile}</p>
                                    </td>
                                    <td className="px-4 py-3 font-semibold">
                                        {row.direction === 'ghs_to_rmb' ? 'GHS → RMB' : 'RMB → GHS'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatPrice(row.amount_ghs)} · ¥{row.amount_rmb.toFixed(2)}
                                        <p className="text-xs text-gray-500">Rate {row.rate}</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        <p className="font-mono">{row.reference}</p>
                                        <p className="text-gray-500">{row.ip_address ?? '—'}</p>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
