import { Head, Link } from '@inertiajs/react';

import SellerLayout from '@/layouts/seller-layout';
import { Paginated, productImageUrl } from '@/types/marketplace';

interface SellerDispute {
    id: number;
    reason: string;
    description: string;
    status: string;
    created_at: string;
    order: { order_number: string };
    buyer: { name: string };
    order_item: { product_name: string; product?: { images?: { path: string }[] } };
}

interface SellerDisputesProps {
    disputes: Paginated<SellerDispute>;
    status: string;
}

const tabs = [
    { key: 'open', label: 'Open' },
    { key: 'under_review', label: 'Under review' },
    { key: 'resolved_buyer', label: 'Refunded' },
    { key: 'resolved_seller', label: 'Declined' },
    { key: 'cancelled', label: 'Cancelled' },
    { key: 'all', label: 'All' },
];

export default function SellerDisputesIndex({ disputes, status }: SellerDisputesProps) {
    return (
        <SellerLayout title="Refund requests" active="orders">
            <Head title="Refund requests" />
            <p className="mb-4 text-sm text-gray-500">Buyers can request refunds here. Admin approves before any money is returned.</p>

            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={route('seller.refunds.index', { status: tab.key === 'all' ? undefined : tab.key })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium ${
                            status === tab.key ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>

            <div className="space-y-4">
                {disputes.data.map((d) => {
                    const image = d.order_item?.product?.images?.[0]?.path;
                    return (
                        <div key={d.id} className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <div className="flex gap-4">
                                {image && (
                                    <img src={productImageUrl(image)} alt="" className="h-16 w-16 rounded-lg border object-cover" />
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-gray-900">{d.order_item?.product_name}</p>
                                    <p className="text-sm text-gray-500">Order {d.order?.order_number} · Buyer: {d.buyer?.name}</p>
                                    <p className="mt-1 text-sm capitalize text-gray-600">{d.reason.replace(/_/g, ' ')}</p>
                                    <p className="mt-1 text-sm text-gray-500">{d.description}</p>
                                </div>
                                <span className="shrink-0 text-sm capitalize text-gray-500">{d.status.replace(/_/g, ' ')}</span>
                            </div>
                        </div>
                    );
                })}
                {disputes.data.length === 0 && <p className="py-12 text-center text-gray-500">No refund requests in this section.</p>}
            </div>
        </SellerLayout>
    );
}
