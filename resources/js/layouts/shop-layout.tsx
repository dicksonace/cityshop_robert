import { Link, usePage } from '@inertiajs/react';
import { ReactNode, useEffect } from 'react';

import BuyerMobileNav from '@/components/shop/buyer-mobile-nav';
import ShopHeader from '@/components/shop/shop-header';
import { SharedData } from '@/types';

interface ShopLayoutProps {
    children: ReactNode;
    hideHeaderSearch?: boolean;
    /** Hide header, footer, and buyer bottom nav (invoice / print-ready pages). */
    hideChrome?: boolean;
}

export default function ShopLayout({ children, hideHeaderSearch = false, hideChrome = false }: ShopLayoutProps) {
    const page = usePage<SharedData>();
    const { auth, flash } = page.props;
    const showBuyerNav = !hideChrome && auth.user?.role === 'buyer';
    const component = typeof page.component === 'string' ? page.component : '';
    const hasValidationErrors = Object.keys(page.props.errors ?? {}).length > 0;
    // Auth/forms already show errors next to the fields — don't duplicate as a top banner.
    const showLayoutError = Boolean(flash?.error) && !hasValidationErrors && !component.startsWith('auth/');

    // Prevent a stuck horizontal scroll offset (looks like content "shifted" with empty space on one side).
    useEffect(() => {
        window.scrollTo({ left: 0, top: window.scrollY });
        document.documentElement.scrollLeft = 0;
        document.body.scrollLeft = 0;
    }, [page.url]);

    return (
        <div
            className={
                showBuyerNav
                    ? 'relative w-full max-w-[100vw] min-h-screen overflow-x-clip bg-gradient-to-b from-gray-50 to-white pb-[calc(4.5rem+env(safe-area-inset-bottom,0px))] print:min-h-0 print:bg-white print:pb-0 sm:pb-0'
                    : hideChrome
                        ? 'relative w-full max-w-[100vw] min-h-0 overflow-x-clip bg-white print:bg-white'
                        : 'relative w-full max-w-[100vw] min-h-screen overflow-x-clip bg-gradient-to-b from-gray-50 to-white print:min-h-0 print:bg-white'
            }
        >
            {!hideChrome && (
                <div className="print:hidden">
                    <ShopHeader hideSearch={hideHeaderSearch} />
                </div>
            )}
            {!hideChrome && flash?.success && (
                <div className="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800 print:hidden">
                    {flash.success}
                </div>
            )}
            {!hideChrome && showLayoutError && (
                <div className="border-b border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-800 print:hidden">
                    {flash.error}
                </div>
            )}
            <main className="w-full min-w-0">{children}</main>
            {!hideChrome && (
            <footer className="mt-12 border-t border-gray-200 bg-white py-8 print:hidden">
                <div className="mx-auto w-full max-w-7xl px-4">
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
                        <div className={showBuyerNav ? 'hidden sm:block' : undefined}>
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
            )}
            {showBuyerNav && (
                <div className="print:hidden">
                    <BuyerMobileNav />
                </div>
            )}
        </div>
    );
}
