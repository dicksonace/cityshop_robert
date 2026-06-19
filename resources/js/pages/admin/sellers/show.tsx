import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PanelLayout from '@/layouts/panel-layout';
import { SellerProfile } from '@/types/marketplace';

interface SellerShowProps {
    seller: SellerProfile & {
        user: {
            name: string;
            email: string;
            mobile: string;
            whatsapp?: string;
            region?: string;
            city?: string;
            digital_address?: string;
            residential_address?: string;
            ghana_card_number?: string;
        };
        shop_photo?: string;
        id_card_front?: string;
        id_card_back?: string;
        form_a?: string;
        form_b?: string;
        business_certificate?: string;
    };
}

const nav = [
    { label: 'Dashboard', href: route('admin.dashboard') },
    { label: 'Sellers', href: route('admin.sellers.index'), active: true },
    { label: 'Products', href: route('admin.products.index') },
    { label: 'Orders', href: route('admin.orders.index') },
    { label: 'Withdrawals', href: route('admin.withdrawals.index') },
];

function DocLink({ path, label }: { path?: string; label: string }) {
    if (!path) return null;
    return (
        <a href={`/storage/${path}`} target="_blank" rel="noreferrer" className="text-blue-500 hover:underline">
            {label}
        </a>
    );
}

export default function SellerShow({ seller }: SellerShowProps) {
    const [reason, setReason] = useState('');
    const [blockReason, setBlockReason] = useState('');

    const approve = () => router.post(route('admin.sellers.approve', seller.id));
    const reject = () => router.post(route('admin.sellers.reject', seller.id), { rejection_reason: reason });
    const block = () => router.post(route('admin.sellers.block', seller.id), { reason: blockReason });
    const unblock = () => router.post(route('admin.sellers.unblock', seller.id));

    return (
        <PanelLayout title="Review Seller" nav={nav} brandColor="text-blue-500">
            <Head title="Review Seller" />
            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Personal Information</h3>
                    <dl className="mt-4 space-y-2 text-sm">
                        <div><dt className="text-gray-500">Name</dt><dd className="font-medium">{seller.user.name}</dd></div>
                        <div><dt className="text-gray-500">Email</dt><dd>{seller.user.email}</dd></div>
                        <div><dt className="text-gray-500">Mobile</dt><dd>{seller.user.mobile}</dd></div>
                        <div><dt className="text-gray-500">Ghana Card</dt><dd>{seller.user.ghana_card_number}</dd></div>
                        <div><dt className="text-gray-500">Location</dt><dd>{seller.user.city}, {seller.user.region}</dd></div>
                        <div><dt className="text-gray-500">Address</dt><dd>{seller.user.residential_address}</dd></div>
                    </dl>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Business Information</h3>
                    <dl className="mt-4 space-y-2 text-sm">
                        <div><dt className="text-gray-500">Registered</dt><dd>{seller.is_business_registered ? 'Yes' : 'No'}</dd></div>
                        <div><dt className="text-gray-500">Name</dt><dd className="font-medium">{seller.business_name ?? seller.store_name}</dd></div>
                        {seller.business_registration_number && (
                            <div><dt className="text-gray-500">Reg. Number</dt><dd>{seller.business_registration_number}</dd></div>
                        )}
                    </dl>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <DocLink path={seller.shop_photo} label="Shop Photo" />
                        <DocLink path={seller.id_card_front} label="ID Front" />
                        <DocLink path={seller.id_card_back} label="ID Back" />
                        <DocLink path={seller.form_a} label="Form A" />
                        <DocLink path={seller.form_b} label="Form B" />
                        <DocLink path={seller.business_certificate} label="Certificate" />
                    </div>
                </div>
            </div>

            {seller.status === 'pending' && (
                <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Actions</h3>
                    <div className="mt-4 flex flex-wrap gap-4">
                        <Button onClick={approve} className="bg-green-600 hover:bg-green-700">Approve Seller</Button>
                        <div className="flex flex-1 gap-2">
                            <Input placeholder="Rejection reason..." value={reason} onChange={(e) => setReason(e.target.value)} />
                            <Button variant="destructive" onClick={reject}>Reject</Button>
                        </div>
                    </div>
                </div>
            )}

            {seller.status === 'approved' && (
                <div className="mt-6 rounded-xl border border-red-100 bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Block Seller</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Blocked sellers cannot access their dashboard and all their products are hidden from the shop.
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Input
                            placeholder="Reason for blocking..."
                            value={blockReason}
                            onChange={(e) => setBlockReason(e.target.value)}
                            className="max-w-md"
                        />
                        <Button variant="destructive" onClick={block} disabled={!blockReason.trim()}>
                            Block Seller
                        </Button>
                    </div>
                </div>
            )}

            {seller.status === 'suspended' && (
                <div className="mt-6 rounded-xl border border-red-200 bg-red-50 p-6">
                    <h3 className="font-semibold text-red-800">This seller is blocked</h3>
                    {seller.rejection_reason && (
                        <p className="mt-2 text-sm text-red-700">Reason: {seller.rejection_reason}</p>
                    )}
                    <p className="mt-2 text-sm text-red-600">Their products are hidden from the shop.</p>
                    <Button onClick={unblock} className="mt-4 bg-green-600 hover:bg-green-700">
                        Unblock Seller
                    </Button>
                </div>
            )}
        </PanelLayout>
    );
}
