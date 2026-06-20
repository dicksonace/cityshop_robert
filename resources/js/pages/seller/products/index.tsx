import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';
import PanelLayout from '@/layouts/panel-layout';
import { formatPrice, Paginated, Product } from '@/types/marketplace';

interface ProductsIndexProps {
    products: Paginated<Product & { reviews_count?: number }>;
}

const nav = [
    { label: 'Dashboard', href: route('seller.dashboard') },
    { label: 'Products', href: route('seller.products.index'), active: true },
    { label: 'Orders', href: route('seller.orders.index') },
    { label: 'Messages', href: route('chat.index') },
    { label: 'Wallet', href: route('seller.wallet') },
];

const statusBadge: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    draft: 'bg-gray-100 text-gray-800',
};

export default function ProductsIndex({ products }: ProductsIndexProps) {
    return (
        <PanelLayout title="My Products" nav={nav}>
            <Head title="My Products" />
            <div className="mb-4 flex justify-end">
                <Link href={route('seller.products.create')}>
                    <Button className="bg-orange-500 hover:bg-orange-600">
                        <Plus className="mr-2 h-4 w-4" />
                        Add Product
                    </Button>
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="border-b bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Product</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Price</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Stock</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Comments</th>
                            <th className="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {products.data.map((product) => (
                            <tr key={product.id}>
                                <td className="px-4 py-3 font-medium">{product.name}</td>
                                <td className="px-4 py-3">{formatPrice(product.discount_price ?? product.price)}</td>
                                <td className="px-4 py-3">{product.quantity}</td>
                                <td className="px-4 py-3">
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusBadge[product.status]}`}>
                                        {product.status}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <Link
                                        href={route('seller.products.reviews', product.id)}
                                        className="text-orange-500 hover:underline"
                                    >
                                        {product.reviews_count ?? 0} comment{(product.reviews_count ?? 0) !== 1 ? 's' : ''}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link href={route('seller.products.edit', product.id)} className="text-orange-500 hover:underline">
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {products.data.length === 0 && <p className="p-8 text-center text-gray-500">No products yet.</p>}
            </div>
        </PanelLayout>
    );
}
