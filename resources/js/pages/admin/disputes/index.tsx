import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PanelLayout from '@/layouts/panel-layout';
import { Paginated } from '@/types/marketplace';

interface Dispute {
    id: number;
    reason: string;
    description: string;
    status: string;
    order: { order_number: string };
    buyer: { name: string };
    seller: { name: string };
    order_item: { product_name: string };
}

interface DisputesIndexProps {
    disputes: Paginated<Dispute>;
    status: string;
}

const nav = [
    { label: 'Dashboard', href: route('admin.dashboard') },
    { label: 'Sellers', href: route('admin.sellers.index') },
    { label: 'Products', href: route('admin.products.index') },
    { label: 'Orders', href: route('admin.orders.index') },
    { label: 'Withdrawals', href: route('admin.withdrawals.index') },
    { label: 'Disputes', href: route('admin.disputes.index'), active: true },
];

const tabs = ['open', 'under_review', 'resolved_buyer', 'resolved_seller', 'closed', 'all'];

export default function DisputesIndex({ disputes, status }: DisputesIndexProps) {
    const [resolving, setResolving] = useState<number | null>(null);
    const [notes, setNotes] = useState('');
    const [resolution, setResolution] = useState('resolved_buyer');

    const resolve = (id: number) => {
        router.post(route('admin.disputes.resolve', id), {
            resolution,
            resolution_notes: notes,
        }, { onSuccess: () => setResolving(null) });
    };

    return (
        <PanelLayout title="Disputes" nav={nav}>
            <Head title="Disputes" />
            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <a
                        key={tab}
                        href={route('admin.disputes.index', { status: tab })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium capitalize ${
                            status === tab ? 'bg-blue-500 text-white' : 'bg-white text-gray-600'
                        }`}
                    >
                        {tab.replace('_', ' ')}
                    </a>
                ))}
            </div>

            <div className="space-y-4">
                {disputes.data.map((d) => (
                    <div key={d.id} className="rounded-xl bg-white p-5 shadow-sm">
                        <div className="flex justify-between">
                            <div>
                                <p className="font-medium">{d.order_item?.product_name}</p>
                                <p className="text-sm text-gray-500">Order {d.order?.order_number} — {d.reason.replace('_', ' ')}</p>
                                <p className="mt-1 text-sm text-gray-600">{d.description}</p>
                                <p className="mt-1 text-xs text-gray-400">Buyer: {d.buyer?.name} | Seller: {d.seller?.name}</p>
                            </div>
                            <span className="text-sm capitalize text-gray-500">{d.status.replace('_', ' ')}</span>
                        </div>

                        {['open', 'under_review'].includes(d.status) && (
                            <div className="mt-4">
                                {resolving === d.id ? (
                                    <div className="space-y-2">
                                        <select value={resolution} onChange={(e) => setResolution(e.target.value)} className="w-full rounded-md border px-3 py-2 text-sm">
                                            <option value="resolved_buyer">Resolve in favor of buyer (refund)</option>
                                            <option value="resolved_seller">Resolve in favor of seller</option>
                                            <option value="closed">Close without action</option>
                                        </select>
                                        <Input placeholder="Resolution notes..." value={notes} onChange={(e) => setNotes(e.target.value)} />
                                        <div className="flex gap-2">
                                            <Button size="sm" onClick={() => resolve(d.id)}>Confirm</Button>
                                            <Button size="sm" variant="outline" onClick={() => setResolving(null)}>Cancel</Button>
                                        </div>
                                    </div>
                                ) : (
                                    <Button size="sm" onClick={() => setResolving(d.id)}>Resolve Dispute</Button>
                                )}
                            </div>
                        )}
                    </div>
                ))}
                {disputes.data.length === 0 && <p className="text-center text-gray-500">No disputes found.</p>}
            </div>
        </PanelLayout>
    );
}
