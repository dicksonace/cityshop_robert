import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

import ShopHeader from '@/components/shop/shop-header';
import { SharedData } from '@/types';

interface ShopLayoutProps {
    children: ReactNode;
    hideHeaderSearch?: boolean;
}

export default function ShopLayout({ children, hideHeaderSearch = false }: ShopLayoutProps) {
    const { auth, flash } = usePage<SharedData>().props;

    return (
        <div className="min-h-screen overflow-x-hidden bg-gradient-to-b from-gray-50 to-white pb-20 sm:pb-0">
            <ShopHeader hideSearch={hideHeaderSearch} />
            {flash?.success && (
                <div className="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="border-b border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-800">
                    {flash.error}
                </div>
            )}
            <main>{children}</main>
            <footer className="mt-12 border-t border-gray-200 bg-white py-8">
                <div className="mx-auto max-w-7xl px-4">
                    <div className="grid gap-6 sm:grid-cols-2 md:grid-cols-4">
                        <div>
                            <h3 className="text-lg font-bold">
                                City<span className="text-orange-500">Shop</span>
                            </h3>
                            <p className="mt-2 text-sm text-gray-500">Ghana&apos;s trusted online marketplace.</p>
                        </div>
                        <div>
                            <h4 className="font-semibold text-gray-900">Buy</h4>
                            <ul className="mt-2 space-y-1 text-sm text-gray-500">
                                <li>
                                    <Link href={route('home')}>Shop</Link>
                                </li>
                                <li>
                                    <Link href={route('register.buyer')}>Create Account</Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-semibold text-gray-900">Support</h4>
                            <ul className="mt-2 space-y-1 text-sm text-gray-500">
                                <li>
                                    <Link href={route('contact')}>Contact Us</Link>
                                </li>
                                <li>
                                    <Link href={route('faq')}>FAQ</Link>
                                </li>
                                <li>
                                    <Link href={route('faq')}>Buyer Protection</Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-semibold text-gray-900">Account</h4>
                            <ul className="mt-2 space-y-1 text-sm text-gray-500">
                                {auth.user ? (
                                    <>
                                        <li>
                                            <Link href={route('orders.index')}>My Orders</Link>
                                        </li>
                                        <li>
                                            <Link href={route('wallet.index')}>Wallet</Link>
                                        </li>
                                        <li>
                                            <Link href={route('wishlist.index')}>Wishlist</Link>
                                        </li>
                                        <li>
                                            <Link href={route('addresses.index')}>Addresses</Link>
                                        </li>
                                    </>
                                ) : (
                                    <>
                                        <li>
                                            <Link href={route('login')}>Log In</Link>
                                        </li>
                                        <li>
                                            <Link href={route('register.buyer')}>Create Account</Link>
                                        </li>
                                    </>
                                )}
                            </ul>
                        </div>
                    </div>
                    <p className="mt-8 text-center text-xs text-gray-400">&copy; {new Date().getFullYear()} CityShop. All rights reserved.</p>
                </div>
            </footer>
        </div>
    );
}
