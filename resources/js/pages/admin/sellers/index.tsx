import { Head, Link } from '@inertiajs/react';

import SellerAccountActions from '@/components/admin/seller-account-actions';
import AdminLayout from '@/layouts/admin-layout';
import { Paginated, SellerProfile } from '@/types/marketplace';

interface SellersIndexProps {
    sellers: Paginated<SellerProfile & { user: { name: string; email: string; mobile: string } }>;
    status: string;
}


const tabs = ['pending', 'approved', 'suspended', 'rejected', 'all'];

export default function SellersIndex({ sellers, status }: SellersIndexProps) {
    return (
        <AdminLayout title="Manage Sellers" active="sellers">
            <Head title="Sellers" />
            <div className="scrollbar-hide -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1">
                {tabs.map((tab) => (
                    <Link
                        key={tab}
                        href={route('admin.sellers.index', { status: tab })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium capitalize ${
                            status === tab ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'
                        }`}
                    >
                        {tab}
                    </Link>
                ))}
            </div>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-[640px] w-full text-sm">
                    <thead className="border-b bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Store</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Contact</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th className="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {sellers.data.map((seller) => (
                            <tr key={seller.id}>
                                <td className="px-4 py-3 font-medium">{seller.business_name ?? seller.store_name}</td>
                                <td className="px-4 py-3">
                                    <p>{seller.user.email}</p>
                                    <p className="text-gray-500">{seller.user.mobile}</p>
                                </td>
                                <td className="px-4 py-3">
                                    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium capitalize ${
                                        seller.status === 'approved' ? 'bg-green-100 text-green-800'
                                        : seller.status === 'suspended' ? 'bg-red-100 text-red-800'
                                        : seller.status === 'pending' ? 'bg-yellow-100 text-yellow-800'
                                        : 'bg-gray-100 text-gray-800'
                                    }`}>
                                        {seller.status === 'suspended' ? 'blocked' : seller.status}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <div className="flex flex-col items-end gap-2">
                                        <Link href={route('admin.sellers.show', seller.id)} className="text-blue-500 hover:underline">
                                            Review
                                        </Link>
                                        {(seller.status === 'approved' || seller.status === 'suspended') && (
                                            <SellerAccountActions
                                                sellerId={seller.id}
                                                status={seller.status}
                                                storeName={seller.business_name ?? seller.store_name ?? 'Store'}
                                                compact
                                            />
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
