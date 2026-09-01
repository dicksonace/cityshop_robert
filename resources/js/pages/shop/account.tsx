import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Bell,
    ChevronRight,
    Heart,
    KeyRound,
    LogOut,
    MapPin,
    QrCode,
    Shield,
    Store,
    User,
} from 'lucide-react';

import ProfileAvatarUpload from '@/components/profile-avatar-upload';
import { type KycPayload } from '@/components/wallet/kyc-verification-form';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

type Props = {
    kyc: KycPayload;
    hasPaymentPin: boolean;
};

const kycHint = (kyc: KycPayload) => {
    if (kyc.status === 'approved') return 'Verified';
    if (kyc.status === 'pending') return 'Under review';
    return 'Required for wallet';
};

export default function BuyerAccount({ kyc, hasPaymentPin }: Props) {
    const { flash } = usePage<SharedData>().props;

    const links = [
        { label: 'Profile settings', href: route('profile.edit'), icon: User, hint: 'Name & email' },
        { label: 'Ghana Card verification', href: route('kyc.index'), icon: BadgeCheck, hint: kycHint(kyc) },
        { label: 'Payment PIN', href: route('shop.payment-pin.edit'), icon: Shield, hint: hasPaymentPin ? 'PIN is set' : 'Set 4-digit PIN' },
        { label: 'Notifications', href: route('notifications.index'), icon: Bell, hint: 'Order & wallet alerts' },
        { label: 'My QR code', href: route('wallet.qr.receive'), icon: QrCode, hint: 'Receive wallet payments' },
        { label: 'Pay with QR', href: route('wallet.qr.pay'), icon: QrCode, hint: 'Scan or paste a code' },
        { label: 'Addresses', href: route('addresses.index'), icon: MapPin, hint: 'Saved delivery addresses' },
        { label: 'Wishlist', href: route('wishlist.index'), icon: Heart, hint: 'Saved products' },
        { label: 'Following', href: route('following.index'), icon: Store, hint: 'Sellers you follow' },
        { label: 'Change password', href: route('password.edit'), icon: KeyRound, hint: 'Account security' },
    ];

    return (
        <ShopLayout>
            <Head title="Profile" />
            <div className="mx-auto max-w-lg px-3 py-4 sm:px-4 sm:py-8">
                {flash?.success && (
                    <div className="mb-3 rounded-xl bg-green-50 px-3 py-2 text-sm text-green-700 ring-1 ring-green-100">
                        {flash.success}
                    </div>
                )}

                <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <ProfileAvatarUpload roleLabel="Buyer" />
                </div>

                <ul className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
                    {links.map((item) => {
                        const Icon = item.icon;
                        return (
                            <li key={item.href} className="border-b border-gray-50 last:border-0">
                                <Link href={item.href} className="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50">
                                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                                        <Icon className="h-4 w-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-medium text-gray-900">{item.label}</span>
                                        <span className="block text-xs text-gray-500">{item.hint}</span>
                                    </span>
                                    <ChevronRight className="h-4 w-4 text-gray-300" />
                                </Link>
                            </li>
                        );
                    })}
                </ul>

                <button
                    type="button"
                    onClick={() => router.post(route('logout'))}
                    className="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-red-100 bg-white px-4 py-3.5 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50"
                >
                    <LogOut className="h-4 w-4" />
                    Log out
                </button>
            </div>
        </ShopLayout>
    );
}
