import { Head, Link, router } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import { ReactNode, useState } from 'react';

import CityShopBrand from '@/components/cityshop-brand';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

interface NavItem {
    label: string;
    href: string;
    active?: boolean;
}

interface PanelLayoutProps {
    children: ReactNode;
    title: string;
    nav: NavItem[];
}

function PanelNav({
    nav,
    panelTitle,
    onNavigate,
    className,
}: {
    nav: NavItem[];
    panelTitle: string;
    onNavigate?: () => void;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col', className)}>
            <div className="border-b border-gray-100 p-6">
                <CityShopBrand showText size="md" />
                <p className="mt-3 text-xs font-medium uppercase tracking-wider text-gray-500">{panelTitle}</p>
            </div>
            <nav className="flex-1 space-y-1 overflow-y-auto p-4">
                {nav.map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        onClick={onNavigate}
                        className={cn(
                            'block rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                            item.active ? 'bg-orange-50 text-orange-600' : 'text-gray-600 hover:bg-gray-50',
                        )}
                    >
                        {item.label}
                    </Link>
                ))}
            </nav>
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

export default function PanelLayout({ children, title, nav }: PanelLayoutProps) {
    const [menuOpen, setMenuOpen] = useState(false);
    const closeMenu = () => setMenuOpen(false);

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title={title} />
            <div className="flex">
                <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-gray-200 bg-white lg:flex">
                    <PanelNav nav={nav} panelTitle={title} className="h-full" />
                </aside>

                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetContent side="left" className="w-[min(100vw-2rem,18rem)] p-0">
                        <SheetHeader className="sr-only">
                            <SheetTitle>{title} menu</SheetTitle>
                        </SheetHeader>
                        <PanelNav nav={nav} panelTitle={title} onNavigate={closeMenu} className="h-full" />
                    </SheetContent>
                </Sheet>

                <div className="flex min-w-0 flex-1 flex-col lg:pl-64">
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
