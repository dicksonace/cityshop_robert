import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

import SellerAccountActions from '@/components/admin/seller-account-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { SellerProfile } from '@/types/marketplace';
import { SharedData } from '@/types';

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


function DocLink({ path, label }: { path?: string; label: string }) {
    if (!path) return null;
    return (
        <a href={`/storage/${path}`} target="_blank" rel="noreferrer" className="text-blue-500 hover:underline">
            {label}
        </a>
    );
}

export default function SellerShow({ seller }: SellerShowProps) {
    const { flash } = usePage<SharedData>().props;
    const [reason, setReason] = useState('');
    const [sendRegistrationLink, setSendRegistrationLink] = useState(false);
    const [copied, setCopied] = useState(false);

    const storeName = seller.business_name ?? seller.store_name ?? 'Store';

    const approve = () => router.post(route('admin.sellers.approve', seller.id));
    const reject = () =>
        router.post(route('admin.sellers.reject', seller.id), {
            rejection_reason: reason,
            send_registration_link: sendRegistrationLink,
        });
    const resendInvite = () => router.post(route('admin.sellers.resend-invite', seller.id));

    const copyInviteUrl = async () => {
        if (!flash.sellerInviteUrl) return;
        await navigator.clipboard.writeText(flash.sellerInviteUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AdminLayout title="Review Seller" active="sellers">
            <Head title="Review Seller" />

            {flash.sellerInviteUrl && (
                <div className="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p className="text-sm font-medium text-blue-900">Registration link (share with applicant):</p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <code className="flex-1 break-all rounded bg-white px-3 py-2 text-xs text-gray-800">
                            {flash.sellerInviteUrl}
                        </code>
                        <Button type="button" variant="outline" size="sm" onClick={copyInviteUrl}>
                            {copied ? <Check className="mr-1 h-3.5 w-3.5" /> : <Copy className="mr-1 h-3.5 w-3.5" />}
                            {copied ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                    <p className="mt-2 text-xs text-blue-700">Expires in 24 hours. Single use only.</p>
                </div>
            )}

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
                        <div><dt className="text-gray-500">Name</dt><dd className="font-medium">{storeName}</dd></div>
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
                        <div className="flex flex-1 flex-col gap-2 sm:flex-row">
                            <Input placeholder="Rejection reason..." value={reason} onChange={(e) => setReason(e.target.value)} />
                            <label className="flex items-center gap-2 text-sm text-gray-600">
                                <input
                                    type="checkbox"
                                    checked={sendRegistrationLink}
                                    onChange={(e) => setSendRegistrationLink(e.target.checked)}
                                />
                                Send new registration link
                            </label>
                            <Button variant="destructive" onClick={reject} disabled={!reason.trim()}>
                                Reject
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {seller.status === 'rejected' && (
                <div className="mt-6 rounded-xl border border-amber-100 bg-amber-50 p-6">
                    <h3 className="font-semibold text-amber-900">Application rejected</h3>
                    {seller.rejection_reason && (
                        <p className="mt-2 text-sm text-amber-800">Reason: {seller.rejection_reason}</p>
                    )}
                    <p className="mt-2 text-sm text-amber-700">
                        Send a new private registration link if the applicant should resubmit.
                    </p>
                    <Button onClick={resendInvite} className="mt-4 bg-orange-500 hover:bg-orange-600">
                        Generate registration link
                    </Button>
                </div>
            )}

            {seller.status === 'approved' && (
                <div className="mt-6 rounded-xl border border-orange-100 bg-orange-50 p-6">
                    <h3 className="font-semibold text-gray-900">Manage store products</h3>
                    <p className="mt-1 text-sm text-gray-600">
                        Open this seller&apos;s dashboard view to search, disable, approve, or delete their listings.
                    </p>
                    <Link
                        href={route('admin.stores.show', seller.id)}
                        className="mt-4 inline-flex rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600"
                    >
                        Open store dashboard
                    </Link>
                </div>
            )}

            {(seller.status === 'approved' || seller.status === 'suspended' || seller.status === 'pending' || seller.status === 'rejected') && (
                <div className="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Account controls</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Block hides the seller and their products from the shop. Delete removes the seller login and trashes all listings.
                    </p>
                    {seller.status === 'suspended' && seller.rejection_reason && (
                        <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                            Block reason: {seller.rejection_reason}
                        </p>
                    )}
                    <div className="mt-4">
                        <SellerAccountActions sellerId={seller.id} status={seller.status} storeName={storeName} />
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
