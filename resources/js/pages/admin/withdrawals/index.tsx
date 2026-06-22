import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PanelLayout from '@/layouts/panel-layout';
import { formatPrice, Paginated, Withdrawal } from '@/types/marketplace';

interface WithdrawalsIndexProps {
    withdrawals: Paginated<Withdrawal & { user: { name: string; email: string; role?: string } }>;
    status: string;
}

const nav = [
    { label: 'Dashboard', href: route('admin.dashboard') },
    { label: 'Sellers', href: route('admin.sellers.index') },
    { label: 'Products', href: route('admin.products.index') },
    { label: 'Orders', href: route('admin.orders.index') },
    { label: 'Withdrawals', href: route('admin.withdrawals.index'), active: true },
];

export default function WithdrawalsIndex({ withdrawals, status }: WithdrawalsIndexProps) {
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [reason, setReason] = useState('');

    const tabs = ['pending', 'paid', 'rejected', 'all'];

    return (
        <PanelLayout title="Withdrawals" nav={nav}>
            <Head title="Withdrawals" />
            <div className="mb-4 flex gap-2">
                {tabs.map((tab) => (
                    <Link
                        key={tab}
                        href={route('admin.withdrawals.index', { status: tab })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium capitalize ${
                            status === tab ? 'bg-blue-500 text-white' : 'bg-white text-gray-600'
                        }`}
                    >
                        {tab}
                    </Link>
                ))}
            </div>

            <div className="space-y-4">
                {withdrawals.data.map((w) => (
                    <div key={w.id} className="flex items-center justify-between rounded-xl bg-white p-4 shadow-sm">
                        <div>
                            <p className="font-bold text-lg">{formatPrice(w.amount)}</p>
                            <p className="text-sm text-gray-500">
                                {w.user?.name} ({w.user?.role ?? 'user'}) — {w.momo_number} ({w.network})
                            </p>
                            <p className="text-sm text-gray-500">{w.account_name}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="capitalize text-sm text-gray-500">{w.status}</span>
                            {w.status === 'pending' && (
                                <>
                                    <Button size="sm" className="bg-green-600" onClick={() => router.post(route('admin.withdrawals.approve', w.id))}>
                                        Mark Paid
                                    </Button>
                                    <Button size="sm" variant="destructive" onClick={() => setRejectId(w.id)}>
                                        Reject
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6">
                        <h3 className="font-semibold">Reject Withdrawal</h3>
                        <Input className="mt-3" placeholder="Reason..." value={reason} onChange={(e) => setReason(e.target.value)} />
                        <div className="mt-4 flex gap-2">
                            <Button variant="destructive" onClick={() => router.post(route('admin.withdrawals.reject', rejectId), { rejection_reason: reason }, { onSuccess: () => setRejectId(null) })}>
                                Confirm
                            </Button>
                            <Button variant="outline" onClick={() => setRejectId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}
