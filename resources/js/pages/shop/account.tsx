import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Heart, KeyRound, LogOut, MapPin, User } from 'lucide-react';

import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

const links = [
    { label: 'Profile settings', href: route('profile.edit'), icon: User, hint: 'Name & email' },
    { label: 'Addresses', href: route('addresses.index'), icon: MapPin, hint: 'Saved delivery addresses' },
    { label: 'Wishlist', href: route('wishlist.index'), icon: Heart, hint: 'Saved products' },
    { label: 'Change password', href: route('password.edit'), icon: KeyRound, hint: 'Account security' },
];

export default function BuyerAccount() {
    const { auth } = usePage<SharedData>().props;

    return (
        <ShopLayout>
            <Head title="Profile" />
            <div className="mx-auto max-w-lg px-3 py-4 sm:px-4 sm:py-8">
                <div className="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <div className="flex h-14 w-14 items-center justify-center rounded-full bg-orange-100 text-xl font-bold text-orange-600">
                        {auth.user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-lg font-semibold text-gray-900">{auth.user?.name}</p>
                        <p className="truncate text-sm text-gray-500">{auth.user?.email}</p>
                    </div>
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
