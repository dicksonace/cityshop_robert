import { Head, Link, router } from '@inertiajs/react';
import {
    LayoutDashboard,
    LogOut,
    MessageSquare,
    Package,
    Palette,
    Plus,
    ShoppingCart,
    Wallet,
    CreditCard,
    Star,
    Tag,
} from 'lucide-react';
import { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import CityShopBrand from '@/components/cityshop-brand';
import { cn } from '@/lib/utils';

export interface SellerNavItem {
    key: string;
    label: string;
    href: string;
    icon: typeof LayoutDashboard;
    active?: boolean;
    mobile?: boolean;
}

export function buildSellerNav(active: string): SellerNavItem[] {
    return [
        { key: 'dashboard', label: 'Dashboard', href: route('seller.dashboard'), icon: LayoutDashboard, active: active === 'dashboard', mobile: true },
        { key: 'appearance', label: 'Customize Store', href: route('seller.store-appearance.index'), icon: Palette, active: active === 'appearance' },
        { key: 'products', label: 'Products', href: route('seller.products.index'), icon: Package, active: active === 'products', mobile: true },
        { key: 'orders', label: 'Orders', href: route('seller.orders.index'), icon: ShoppingCart, active: active === 'orders', mobile: true },
        { key: 'payment-methods', label: 'Payment Methods', href: route('seller.payment-methods.index'), icon: CreditCard, active: active === 'payment-methods' },
        { key: 'promotions', label: 'Promotions', href: route('seller.promotions.index'), icon: Tag, active: active === 'promotions' },
        { key: 'reviews', label: 'Reviews', href: route('seller.reviews.index'), icon: Star, active: active === 'reviews' },
        { key: 'messages', label: 'Messages', href: route('chat.index'), icon: MessageSquare, active: active === 'messages', mobile: true },
        { key: 'wallet', label: 'Finance', href: route('seller.wallet'), icon: Wallet, active: active === 'wallet', mobile: true },
    ];
}

interface SellerLayoutProps {
    children: ReactNode;
    title: string;
    active: string;
    showFab?: boolean;
}

export default function SellerLayout({ children, title, active, showFab = false }: SellerLayoutProps) {
    const nav = buildSellerNav(active);
    const mobileNav = nav.filter((item) => item.mobile);

    return (
        <div className="min-h-screen bg-gray-50 pb-20 lg:pb-0">
            <Head title={title} />
            <div className="flex">
                <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-gray-200 bg-white lg:flex">
                    <div className="border-b border-gray-100 p-6">
                        <CityShopBrand showText size="md" />
                        <p className="mt-2 text-xs font-medium uppercase tracking-wider text-gray-400">Seller Hub</p>
                    </div>
                    <nav className="flex-1 space-y-0.5 p-3">
                        {nav.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                    item.active ? 'bg-orange-50 text-orange-600' : 'text-gray-600 hover:bg-gray-50',
                                )}
                            >
                                <item.icon className="h-4 w-4 shrink-0" />
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="space-y-2 border-t border-gray-100 p-4">
                        <Link href={route('home')} className="block rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50">
                            View marketplace →
                        </Link>
                        <Button variant="outline" size="sm" className="w-full" onClick={() => router.post(route('logout'))}>
                            <LogOut className="mr-2 h-4 w-4" />
                            Logout
                        </Button>
                    </div>
                </aside>

                <div className="flex flex-1 flex-col lg:pl-64">
                    <header className="sticky top-0 z-30 border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur lg:px-8 lg:py-4">
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3 lg:hidden">
                                <CityShopBrand size="sm" />
                            </div>
                            <h1 className="text-lg font-semibold text-gray-900 lg:text-xl">{title}</h1>
                            <Link href={route('seller.products.create')} className="hidden sm:inline-flex lg:hidden">
                                <Button size="sm" className="bg-orange-500 hover:bg-orange-600">
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add
                                </Button>
                            </Link>
                        </div>
                    </header>
                    <main className="flex-1 p-4 lg:p-8">{children}</main>
                </div>
            </div>

            <nav className="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white lg:hidden">
                <div className="mx-auto flex max-w-lg items-stretch justify-around">
                    {mobileNav.map((item) => (
                        <Link
                            key={item.key}
                            href={item.href}
                            className={cn(
                                'flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium',
                                item.active ? 'text-orange-600' : 'text-gray-500',
                            )}
                        >
                            <item.icon className="h-5 w-5" />
                            {item.label}
                        </Link>
                    ))}
                </div>
            </nav>

            {showFab && (
                <Link
                    href={route('seller.products.create')}
                    className="fixed right-4 bottom-20 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-orange-500 text-white shadow-lg shadow-orange-500/30 transition hover:bg-orange-600 lg:hidden"
                >
                    <Plus className="h-6 w-6" />
                </Link>
            )}
        </div>
    );
}
