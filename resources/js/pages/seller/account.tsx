import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronRight, KeyRound, LogOut, Smartphone, Store, User, Users } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import ProfileAvatarUpload from '@/components/profile-avatar-upload';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SellerLayout from '@/layouts/seller-layout';
import { SharedData } from '@/types';

type Props = {
    profile: {
        business_name?: string | null;
        store_name?: string | null;
        shop_photo?: string | null;
        order_sms_mobile_1?: string | null;
        order_sms_mobile_2?: string | null;
    } | null;
    accountMobile?: string | null;
};

const links = [
    { label: 'Profile settings', href: route('profile.edit'), icon: User, hint: 'Name & email' },
    { label: 'Customize store', href: route('seller.store-appearance.index'), icon: Store, hint: 'Store logo & appearance' },
    { label: 'Followers', href: route('seller.followers.index'), icon: Users, hint: 'People following your store' },
    { label: 'Change password', href: route('password.edit'), icon: KeyRound, hint: 'Account security' },
];

export default function SellerAccount({ profile, accountMobile }: Props) {
    const { flash } = usePage<SharedData>().props;
    const storeName = profile?.business_name ?? profile?.store_name ?? 'Your store';
    const smsForm = useForm({
        order_sms_mobile_1: profile?.order_sms_mobile_1 || accountMobile || '',
        order_sms_mobile_2: profile?.order_sms_mobile_2 || '',
    });

    const saveSms: FormEventHandler = (e) => {
        e.preventDefault();
        smsForm.post(route('seller.account.order-sms'), { preserveScroll: true });
    };

    return (
        <SellerLayout title="Account" active="account">
            <Head title="Seller Account" />

            <div className="mx-auto max-w-lg space-y-4">
                {flash?.success && (
                    <div className="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-700 ring-1 ring-green-100">
                        {flash.success}
                    </div>
                )}

                <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <ProfileAvatarUpload roleLabel={`Seller · ${storeName}`} />
                    <p className="mt-3 text-xs text-gray-500">
                        Your profile picture appears in Messenger. Your store photo is set under Customize store.
                    </p>
                </div>

                <form onSubmit={saveSms} className="space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <div className="flex items-start gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                            <Smartphone className="h-4 w-4" />
                        </span>
                        <div>
                            <h2 className="font-semibold text-gray-900">New order SMS</h2>
                            <p className="mt-0.5 text-xs text-gray-500">
                                Up to two Ghana numbers get the “you received new order” SMS. You can change them anytime.
                            </p>
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="order_sms_mobile_1">Number 1</Label>
                        <Input
                            id="order_sms_mobile_1"
                            className="mt-1"
                            inputMode="tel"
                            placeholder="024XXXXXXX"
                            value={smsForm.data.order_sms_mobile_1}
                            onChange={(e) => smsForm.setData('order_sms_mobile_1', e.target.value)}
                        />
                        <InputError message={smsForm.errors.order_sms_mobile_1} />
                    </div>
                    <div>
                        <Label htmlFor="order_sms_mobile_2">Number 2 (optional)</Label>
                        <Input
                            id="order_sms_mobile_2"
                            className="mt-1"
                            inputMode="tel"
                            placeholder="020XXXXXXX"
                            value={smsForm.data.order_sms_mobile_2}
                            onChange={(e) => smsForm.setData('order_sms_mobile_2', e.target.value)}
                        />
                        <InputError message={smsForm.errors.order_sms_mobile_2} />
                    </div>
                    <Button disabled={smsForm.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                        {smsForm.processing ? 'Saving…' : 'Save SMS numbers'}
                    </Button>
                </form>

                <ul className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
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
                    className="flex w-full items-center justify-center gap-2 rounded-2xl border border-red-100 bg-white px-4 py-3.5 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50"
                >
                    <LogOut className="h-4 w-4" />
                    Log out
                </button>
            </div>
        </SellerLayout>
    );
}
