import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, Paginated, Product, productImageUrl } from '@/types/marketplace';

interface ProductsIndexProps {
    products: Paginated<Product & { seller: { name: string } }>;
    status: string;
}

const tabs = [
    { value: 'all', label: 'All' },
    { value: 'approved', label: 'Live' },
    { value: 'pending', label: 'Pending' },
    { value: 'rejected', label: 'Removed' },
];

const statusBadge: Record<string, string> = {
    approved: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
    draft: 'bg-gray-100 text-gray-600',
    rejected: 'bg-red-100 text-red-700',
};

export default function AdminProductsIndex({ products, status }: ProductsIndexProps) {
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [reason, setReason] = useState('');

    return (
        <AdminLayout title="Manage Products" active="products">
            <Head title="Products" />

            <p className="mb-4 text-sm text-gray-500">
                Seller listings go live automatically. Use these controls to hide or remove products when needed.
            </p>

            <div className="scrollbar-hide -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1">
                {tabs.map((tab) => (
                    <Link
                        key={tab.value}
                        href={route('admin.products.index', { status: tab.value })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium ${
                            status === tab.value ? 'bg-blue-500 text-white' : 'bg-white text-gray-600'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>

            <div className="space-y-4">
                {products.data.length === 0 ? (
                    <div className="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">
                        No products in this filter.
                    </div>
                ) : (
                    products.data.map((product) => (
                        <div key={product.id} className="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                            <div className="flex min-w-0 flex-1 gap-4">
                                <img src={productImageUrl(product.images?.[0]?.path)} alt="" className="h-20 w-20 shrink-0 rounded-lg object-contain" />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="font-medium">{product.name}</h3>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusBadge[product.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {product.status === 'approved' ? 'live' : product.status}
                                        </span>
                                    </div>
                                    <p className="text-sm text-gray-500">by {product.seller?.name}</p>
                                    <p className="font-bold text-orange-500">{formatPrice(product.price)}</p>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2 sm:shrink-0">
                                {product.status !== 'approved' && product.status !== 'rejected' && (
                                    <Button size="sm" className="bg-green-600" onClick={() => router.post(route('admin.products.approve', product.id))}>
                                        Make live
                                    </Button>
                                )}
                                {product.status === 'approved' && (
                                    <Button size="sm" variant="outline" onClick={() => router.post(route('admin.products.hide', product.id))}>
                                        Hide
                                    </Button>
                                )}
                                {product.status !== 'rejected' && (
                                    <Button size="sm" variant="destructive" onClick={() => setRejectId(product.id)}>
                                        Remove
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>

            {rejectId && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6">
                        <h3 className="font-semibold">Remove product from shop</h3>
                        <p className="mt-1 text-sm text-gray-500">Buyers will no longer see this listing.</p>
                        <Input className="mt-3" placeholder="Reason..." value={reason} onChange={(e) => setReason(e.target.value)} />
                        <div className="mt-4 flex gap-2">
                            <Button
                                variant="destructive"
                                disabled={!reason.trim()}
                                onClick={() =>
                                    router.post(
                                        route('admin.products.reject', rejectId),
                                        { rejection_reason: reason },
                                        { onSuccess: () => { setRejectId(null); setReason(''); } },
                                    )
                                }
                            >
                                Confirm remove
                            </Button>
                            <Button variant="outline" onClick={() => setRejectId(null)}>Cancel</Button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
