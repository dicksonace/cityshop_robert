import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Copy, ShieldBan, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import SellerAccountActions from '@/components/admin/seller-account-actions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { SellerProfile } from '@/types/marketplace';
import { SharedData } from '@/types';

interface AdminPaymentMethod {
    id: number;
    type: string;
    label: string;
    account_name: string;
    account_number: string | null;
    network: string | null;
    bank_name: string | null;
    instructions: string | null;
    is_active: boolean;
    is_default: boolean;
    is_disabled: boolean;
    disabled_reason: string | null;
    disabled_at: string | null;
    deleted_at: string | null;
}

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
        accept_marketplace_payments?: boolean;
        accept_direct_payments?: boolean;
    };
    paymentMethods: AdminPaymentMethod[];
    paymentMethodsLocked: boolean;
    paymentMethodsLockReason: string | null;
    paymentMethodsLockedBy: { id: number; name: string } | null;
    paymentMethodsLockedAt: string | null;
}

function DocLink({ path, label }: { path?: string; label: string }) {
    if (!path) return null;
    return (
        <a href={`/storage/${path}`} target="_blank" rel="noreferrer" className="text-blue-500 hover:underline">
            {label}
        </a>
    );
}

export default function SellerShow({
    seller,
    paymentMethods,
    paymentMethodsLocked,
    paymentMethodsLockReason,
    paymentMethodsLockedBy,
    paymentMethodsLockedAt,
}: SellerShowProps) {
    const { flash } = usePage<SharedData>().props;
    const [reason, setReason] = useState('');
    const [sendRegistrationLink, setSendRegistrationLink] = useState(false);
    const [copied, setCopied] = useState(false);
    const [disableReason, setDisableReason] = useState<Record<number, string>>({});

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

    const disableMethod = (methodId: number) => {
        const reasonText = (disableReason[methodId] ?? '').trim();
        if (reasonText.length < 5) return;
        router.post(route('admin.sellers.payment-methods.disable', [seller.id, methodId]), {
            reason: reasonText,
        });
    };

    const enableMethod = (methodId: number) => {
        router.post(route('admin.sellers.payment-methods.enable', [seller.id, methodId]));
    };

    const unlockPaymentSetup = () => {
        router.post(route('admin.sellers.payment-methods.unlock', seller.id));
    };

    return (
        <AdminLayout title="Review Seller" active="sellers">
            <Head title="Review Seller" />

            {flash.success && (
                <div className="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{flash.success}</div>
            )}
            {flash.error && (
                <div className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{flash.error}</div>
            )}

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
                        <div>
                            <dt className="text-gray-500">Buyer payment modes</dt>
                            <dd>
                                {seller.accept_marketplace_payments ? 'Marketplace' : null}
                                {seller.accept_marketplace_payments && seller.accept_direct_payments ? ' · ' : null}
                                {seller.accept_direct_payments ? 'Direct to seller' : null}
                                {!seller.accept_marketplace_payments && !seller.accept_direct_payments ? 'None' : null}
                            </dd>
                        </div>
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

            <div className="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="font-semibold text-gray-900">Seller payment methods</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Disable a suspicious MoMo/bank account. That hides it from checkout and locks the seller from adding new payment methods.
                        </p>
                    </div>
                    {paymentMethodsLocked && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                            <ShieldBan className="h-3.5 w-3.5" />
                            Setup locked
                        </span>
                    )}
                </div>

                {paymentMethodsLocked && (
                    <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                        <p className="font-medium">Payment setup is locked</p>
                        {paymentMethodsLockReason && <p className="mt-1">{paymentMethodsLockReason}</p>}
                        <p className="mt-1 text-xs text-red-700">
                            {paymentMethodsLockedBy ? `By ${paymentMethodsLockedBy.name}` : 'By admin'}
                            {paymentMethodsLockedAt
                                ? ` · ${new Date(paymentMethodsLockedAt).toLocaleString()}`
                                : ''}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mt-3 border-red-300 bg-white"
                            onClick={unlockPaymentSetup}
                        >
                            <ShieldCheck className="mr-1.5 h-4 w-4" />
                            Unlock setup (allow new methods)
                        </Button>
                    </div>
                )}

                {paymentMethods.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">This seller has not added any payment methods yet.</p>
                ) : (
                    <ul className="mt-4 divide-y">
                        {paymentMethods.map((method) => (
                            <li key={method.id} className="py-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-medium text-gray-900">{method.label}</p>
                                        <p className="text-sm text-gray-600">
                                            {method.account_name}
                                            {method.account_number ? ` · ${method.account_number}` : ''}
                                        </p>
                                        <div className="mt-1 flex flex-wrap gap-2 text-xs">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 capitalize text-gray-700">
                                                {method.type.replace(/_/g, ' ')}
                                            </span>
                                            {method.is_default && !method.is_disabled && (
                                                <span className="rounded-full bg-blue-100 px-2 py-0.5 text-blue-800">Default</span>
                                            )}
                                            {method.is_disabled ? (
                                                <span className="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800">Disabled</span>
                                            ) : method.is_active ? (
                                                <span className="rounded-full bg-green-100 px-2 py-0.5 text-green-800">Active</span>
                                            ) : (
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">Inactive</span>
                                            )}
                                            {method.deleted_at && (
                                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">Removed by seller</span>
                                            )}
                                        </div>
                                        {method.disabled_reason && (
                                            <p className="mt-2 text-xs text-red-700">
                                                Reason: {method.disabled_reason}
                                                {method.disabled_at
                                                    ? ` · ${new Date(method.disabled_at).toLocaleString()}`
                                                    : ''}
                                            </p>
                                        )}
                                    </div>

                                    {!method.deleted_at && (
                                        <div className="w-full max-w-sm space-y-2 sm:w-auto">
                                            {method.is_disabled ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="w-full bg-green-600 hover:bg-green-700 sm:w-auto"
                                                    onClick={() => enableMethod(method.id)}
                                                >
                                                    Enable
                                                </Button>
                                            ) : (
                                                <>
                                                    <Input
                                                        placeholder="Why disable? (suspicious, wrong owner…)"
                                                        value={disableReason[method.id] ?? ''}
                                                        onChange={(e) =>
                                                            setDisableReason((prev) => ({
                                                                ...prev,
                                                                [method.id]: e.target.value,
                                                            }))
                                                        }
                                                    />
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        className="w-full sm:w-auto"
                                                        disabled={(disableReason[method.id] ?? '').trim().length < 5}
                                                        onClick={() => disableMethod(method.id)}
                                                    >
                                                        Disable / block
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
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
