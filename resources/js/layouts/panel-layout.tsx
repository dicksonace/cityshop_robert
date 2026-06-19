import { Head, Link, router } from '@inertiajs/react';
import { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
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
    brand?: string;
    brandColor?: string;
}

export default function PanelLayout({ children, title, nav, brand = 'CityShop', brandColor = 'text-orange-500' }: PanelLayoutProps) {
    return (
        <div className="min-h-screen bg-gray-50">
            <Head title={title} />
            <div className="flex">
                <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-gray-200 bg-white lg:flex">
                    <div className="border-b border-gray-100 p-6">
                        <Link href={route('home')} className="text-xl font-bold text-gray-900">
                            {brand.split('Shop')[0]}
                            <span className={brandColor}>Shop</span>
                        </Link>
                        <p className="mt-1 text-xs text-gray-500 uppercase tracking-wider">{title}</p>
                    </div>
                    <nav className="flex-1 space-y-1 p-4">
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'block rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    item.active ? 'bg-orange-50 text-orange-600' : 'text-gray-600 hover:bg-gray-50',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="border-t border-gray-100 p-4">
                        <Button variant="outline" size="sm" className="w-full" onClick={() => router.post(route('logout'))}>
                            Logout
                        </Button>
                    </div>
                </aside>

                <div className="flex flex-1 flex-col lg:pl-64">
                    <header className="sticky top-0 z-30 border-b border-gray-200 bg-white px-4 py-4 lg:px-8">
                        <div className="flex items-center justify-between">
                            <h1 className="text-lg font-semibold text-gray-900 lg:text-xl">{title}</h1>
                            <Link href={route('home')} className="text-sm text-gray-500 hover:text-orange-500">
                                View Store
                            </Link>
                        </div>
                    </header>
                    <main className="flex-1 p-4 lg:p-8">{children}</main>
                </div>
            </div>
        </div>
    );
}
