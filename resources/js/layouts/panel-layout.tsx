import { Head, Link, router } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import { ReactNode, useState } from 'react';

import CityShopBrand from '@/components/cityshop-brand';
import PanelSidebarNav from '@/components/panel/panel-sidebar-nav';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { PanelNavGroup } from '@/lib/panel-nav-types';
import { cn } from '@/lib/utils';

interface PanelLayoutProps {
    children: ReactNode;
    title: string;
    panelTitle?: string;
    panelId: string;
    navGroups: PanelNavGroup[];
    fallbackSection?: string;
    brandHref?: string;
}

function PanelShell({
    panelTitle,
    panelId,
    navGroups,
    fallbackSection,
    brandHref,
    onNavigate,
    className,
}: {
    panelTitle: string;
    panelId: string;
    navGroups: PanelNavGroup[];
    fallbackSection?: string;
    brandHref?: string;
    onNavigate?: () => void;
    className?: string;
}) {
    return (
        <div className={cn('flex h-full flex-col', className)}>
            <div className="border-b border-gray-100 p-5">
                <CityShopBrand showText size="md" href={brandHref} />
                <p className="mt-2 text-xs font-medium uppercase tracking-wider text-gray-500">{panelTitle}</p>
            </div>
            <PanelSidebarNav
                panelId={panelId}
                groups={navGroups}
                fallbackSection={fallbackSection}
                onNavigate={onNavigate}
                className="min-h-0 flex-1"
            />
            <div className="space-y-2 border-t border-gray-100 p-4">
                <Link
                    href={route('home')}
                    onClick={onNavigate}
                    className="block rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50"
                >
                    View Store →
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

export default function PanelLayout({ children, title, panelTitle, panelId, navGroups, fallbackSection, brandHref }: PanelLayoutProps) {
    const sidebarTitle = panelTitle ?? title;
    const [menuOpen, setMenuOpen] = useState(false);
    const closeMenu = () => setMenuOpen(false);

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title={title} />
            <div className="flex">
                <aside className="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-gray-200 bg-white lg:flex">
                    <PanelShell
                        panelTitle={sidebarTitle}
                        panelId={panelId}
                        navGroups={navGroups}
                        fallbackSection={fallbackSection}
                        brandHref={brandHref}
                        className="h-full"
                    />
                </aside>

                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetContent side="left" className="w-[min(100vw-2rem,20rem)] p-0">
                        <SheetHeader className="sr-only">
                            <SheetTitle>{sidebarTitle} menu</SheetTitle>
                        </SheetHeader>
                        <PanelShell
                            panelTitle={sidebarTitle}
                            panelId={panelId}
                            navGroups={navGroups}
                            fallbackSection={fallbackSection}
                            brandHref={brandHref}
                            onNavigate={closeMenu}
                            className="h-full"
                        />
                    </SheetContent>
                </Sheet>

                <div className="flex min-w-0 flex-1 flex-col lg:pl-72">
                    <header className="sticky top-0 z-30 border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur lg:px-8 lg:py-4">
                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="shrink-0 lg:hidden"
                                onClick={() => setMenuOpen(true)}
                                aria-label="Open menu"
                            >
                                <Menu className="h-5 w-5" />
                            </Button>
                            <div className="min-w-0 flex-1">
                                <h1 className="truncate text-base font-semibold text-gray-900 sm:text-lg lg:text-xl">{title}</h1>
                            </div>
                            <Link
                                href={route('home')}
                                className="shrink-0 text-xs text-gray-500 hover:text-orange-500 sm:text-sm"
                            >
                                View Store
                            </Link>
                        </div>
                    </header>
                    <main className="min-w-0 flex-1 p-4 lg:p-8">{children}</main>
                </div>
            </div>
        </div>
    );
}
