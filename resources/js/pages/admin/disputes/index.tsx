import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
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

const tabs = [
    { key: 'open', label: 'New requests' },
    { key: 'under_review', label: 'Under review' },
    { key: 'resolved_buyer', label: 'Refunded' },
    { key: 'resolved_seller', label: 'Declined' },
    { key: 'cancelled', label: 'Cancelled' },
    { key: 'all', label: 'All' },
];

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

    const markReview = (id: number) => {
        router.post(route('admin.disputes.review', id));
    };

    return (
        <AdminLayout title="Refund requests" active="disputes">
            <Head title="Refund requests" />
            <p className="mb-4 text-sm text-gray-500">Review buyer refund requests before approving or declining. Sellers are notified automatically.</p>

            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <a
                        key={tab.key}
                        href={route('admin.disputes.index', { status: tab.key })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium ${
                            status === tab.key ? 'bg-blue-500 text-white' : 'bg-white text-gray-600'
                        }`}
                    >
                        {tab.label}
                    </a>
                ))}
            </div>

            <div className="space-y-4">
                {disputes.data.map((d) => (
                    <div key={d.id} className="rounded-xl bg-white p-5 shadow-sm">
                        <div className="flex justify-between gap-4">
                            <div>
                                <p className="font-medium">{d.order_item?.product_name}</p>
                                <p className="text-sm text-gray-500">Order {d.order?.order_number} — {d.reason.replace(/_/g, ' ')}</p>
                                <p className="mt-1 text-sm text-gray-600">{d.description}</p>
                                <p className="mt-1 text-xs text-gray-400">Buyer: {d.buyer?.name} | Seller: {d.seller?.name}</p>
                            </div>
                            <span className="shrink-0 text-sm capitalize text-gray-500">{d.status.replace(/_/g, ' ')}</span>
                        </div>

                        {d.status === 'open' && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" onClick={() => markReview(d.id)}>Mark under review</Button>
                                <Button size="sm" onClick={() => setResolving(d.id)}>Approve or decline</Button>
                            </div>
                        )}

                        {d.status === 'under_review' && (
                            <div className="mt-4">
                                <Button size="sm" onClick={() => setResolving(d.id)}>Approve or decline refund</Button>
                            </div>
                        )}

                        {resolving === d.id && (
                            <div className="mt-4 space-y-2 border-t border-gray-100 pt-4">
                                <select value={resolution} onChange={(e) => setResolution(e.target.value)} className="w-full rounded-md border px-3 py-2 text-sm">
                                    <option value="resolved_buyer">Approve refund (return money to buyer)</option>
                                    <option value="resolved_seller">Decline refund (favor seller)</option>
                                    <option value="closed">Close without action</option>
                                </select>
                                <Input placeholder="Admin notes for buyer and seller..." value={notes} onChange={(e) => setNotes(e.target.value)} />
                                <div className="flex gap-2">
                                    <Button size="sm" onClick={() => resolve(d.id)}>Confirm decision</Button>
                                    <Button size="sm" variant="outline" onClick={() => setResolving(null)}>Cancel</Button>
                                </div>
                            </div>
                        )}
                    </div>
                ))}
                {disputes.data.length === 0 && <p className="text-center text-gray-500">No refund requests found.</p>}
            </div>
        </AdminLayout>
    );
}
