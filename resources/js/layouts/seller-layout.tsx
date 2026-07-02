import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    LogOut,
    Menu,
    MessageSquare,
    Package,
    Plus,
    ShoppingCart,
    Wallet,
} from 'lucide-react';
import { ReactNode, useState } from 'react';

import CityShopBrand from '@/components/cityshop-brand';
import PanelSidebarNav from '@/components/panel/panel-sidebar-nav';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { SellerNavKey, sellerMobileNavItems, sellerNavGroups, sellerNavSection } from '@/lib/seller-nav';
import { isNavSubItemActive } from '@/lib/panel-nav-utils';
import { cn } from '@/lib/utils';

const mobileIcons: Record<string, typeof LayoutDashboard> = {
    overview: LayoutDashboard,
    'products-all': Package,
    'orders-all': ShoppingCart,
    messages: MessageSquare,
    wallet: Wallet,
};

interface SellerLayoutProps {
    children: ReactNode;
    title: string;
    active: SellerNavKey;
    showFab?: boolean;
}

function SellerSidebar({
    active,
    onNavigate,
    className,
}: {
    active: SellerNavKey;
    onNavigate?: () => void;
    className?: string;
}) {
    return (
        <div className={cn('flex h-full flex-col', className)}>
            <div className="border-b border-gray-100 p-5">
                <CityShopBrand showText size="md" />
                <p className="mt-2 text-xs font-medium uppercase tracking-wider text-gray-400">Seller Hub</p>
            </div>
            <PanelSidebarNav
                panelId="seller"
                groups={sellerNavGroups(active)}
                fallbackSection={sellerNavSection(active)}
                onNavigate={onNavigate}
                className="min-h-0 flex-1"
            />
            <div className="space-y-2 border-t border-gray-100 p-4">
                <Link href={route('home')} onClick={onNavigate} className="block rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50">
                    View marketplace →
                </Link>
                <Button
                    variant="outline"
                    size="sm"
                    className="w-full"
                    onClick={() => {
                        onNavigate?.();
                        router.post(route('logout'));
                    }}
                >
                    <LogOut className="mr-2 h-4 w-4" />
                    Logout
                </Button>
            </div>
        </div>
    );
}

export default function SellerLayout({ children, title, active, showFab = false }: SellerLayoutProps) {
    const { url } = usePage();
    const mobileNav = sellerMobileNavItems(active);
    const [menuOpen, setMenuOpen] = useState(false);
    const closeMenu = () => setMenuOpen(false);

    return (
        <div className="min-h-screen bg-gray-50 pb-20 lg:pb-0">
            <Head title={title} />
            <div className="flex">
                <aside className="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-gray-200 bg-white lg:flex">
                    <SellerSidebar active={active} className="h-full" />
                </aside>

                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetContent side="left" className="w-[min(100vw-2rem,20rem)] p-0">
                        <SheetHeader className="sr-only">
                            <SheetTitle>Seller Hub menu</SheetTitle>
                        </SheetHeader>
                        <SellerSidebar active={active} onNavigate={closeMenu} className="h-full" />
                    </SheetContent>
                </Sheet>

                <div className="flex min-w-0 flex-1 flex-col lg:pl-72">
                    <header className="sticky top-0 z-30 border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur lg:px-8 lg:py-4">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex min-w-0 items-center gap-2 lg:hidden">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="shrink-0"
                                    onClick={() => setMenuOpen(true)}
                                    aria-label="Open menu"
                                >
                                    <Menu className="h-5 w-5" />
                                </Button>
                                <CityShopBrand size="sm" />
                            </div>
                            <h1 className="min-w-0 flex-1 truncate text-base font-semibold text-gray-900 sm:text-lg lg:text-xl">{title}</h1>
                            <Link href={route('seller.products.create')} className="hidden sm:inline-flex lg:hidden">
                                <Button size="sm" className="bg-orange-500 hover:bg-orange-600">
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add
                                </Button>
                            </Link>
                        </div>
                    </header>
                    <main className="min-w-0 flex-1 p-4 lg:p-8">{children}</main>
                </div>
            </div>

            <nav className="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white lg:hidden">
                <div className="mx-auto flex max-w-lg items-stretch justify-around">
                    {mobileNav.map((item) => {
                        const Icon = mobileIcons[item.key] ?? LayoutDashboard;
                        const isActive = isNavSubItemActive(url, item, mobileNav);
                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={cn(
                                    'flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium',
                                    isActive ? 'text-orange-600' : 'text-gray-500',
                                )}
                            >
                                <Icon className="h-5 w-5" />
                                <span className="max-w-[4.5rem] truncate">{item.label.split(' ')[0]}</span>
                            </Link>
                        );
                    })}
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

export type { SellerNavKey };
export { sellerNavGroups, sellerMobileNavItems };
