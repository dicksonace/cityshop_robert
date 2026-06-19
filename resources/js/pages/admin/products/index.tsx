import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PanelLayout from '@/layouts/panel-layout';
import { formatPrice, Paginated, Product, productImageUrl } from '@/types/marketplace';

interface ProductsIndexProps {
    products: Paginated<Product & { seller: { name: string } }>;
    status: string;
}

const nav = [
    { label: 'Dashboard', href: route('admin.dashboard') },
    { label: 'Sellers', href: route('admin.sellers.index') },
    { label: 'Products', href: route('admin.products.index'), active: true },
    { label: 'Orders', href: route('admin.orders.index') },
    { label: 'Withdrawals', href: route('admin.withdrawals.index') },
];

export default function AdminProductsIndex({ products, status }: ProductsIndexProps) {
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [reason, setReason] = useState('');

    const tabs = ['pending', 'approved', 'rejected', 'all'];

    return (
        <PanelLayout title="Manage Products" nav={nav} brandColor="text-blue-500">
            <Head title="Products" />
            <div className="mb-4 flex gap-2">
                {tabs.map((tab) => (
                    <Link
                        key={tab}
                        href={route('admin.products.index', { status: tab })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium capitalize ${
                            status === tab ? 'bg-blue-500 text-white' : 'bg-white text-gray-600'
                        }`}
                    >
                        {tab}
                    </Link>
                ))}
            </div>

            <div className="space-y-4">
                {products.data.map((product) => (
                    <div key={product.id} className="flex gap-4 rounded-xl bg-white p-4 shadow-sm">
                        <img src={productImageUrl(product.images?.[0]?.path)} alt="" className="h-20 w-20 rounded-lg object-contain" />
                        <div className="flex-1">
                            <h3 className="font-medium">{product.name}</h3>
                            <p className="text-sm text-gray-500">by {product.seller?.name}</p>
                            <p className="font-bold text-orange-500">{formatPrice(product.price)}</p>
                        </div>
                        {product.status === 'pending' && (
                            <div className="flex items-center gap-2">
                                <Button size="sm" className="bg-green-600" onClick={() => router.post(route('admin.products.approve', product.id))}>
                                    Approve
                                </Button>
                                <Button size="sm" variant="destructive" onClick={() => setRejectId(product.id)}>
                                    Reject
                                </Button>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6">
                        <h3 className="font-semibold">Reject Product</h3>
                        <Input className="mt-3" placeholder="Reason..." value={reason} onChange={(e) => setReason(e.target.value)} />
                        <div className="mt-4 flex gap-2">
                            <Button variant="destructive" onClick={() => router.post(route('admin.products.reject', rejectId), { rejection_reason: reason }, { onSuccess: () => setRejectId(null) })}>
                                Confirm Reject
                            </Button>
                            <Button variant="outline" onClick={() => setRejectId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}
