import { Link, router, usePage } from '@inertiajs/react';
import { Heart, LogIn, Menu, MessageCircle, Search, ShoppingCart, Store, User, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

import NotificationBell from '@/components/shop/notification-bell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useChatOptional } from '@/contexts/chat-context';
import { SharedData } from '@/types';

export default function ShopHeader() {
    const page = usePage<SharedData & { cartCount: number; wishlistCount: number; unreadMessages?: number }>();
    const { auth, cartCount, wishlistCount } = page.props;
    const chat = useChatOptional();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const params = new URLSearchParams(page.url.split('?')[1] ?? '');
    const [search, setSearch] = useState(params.get('search') ?? '');

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('home'), { search });
    };

    const navLinks = [
        { label: 'Shop', href: route('home') },
        { label: 'Wallet', href: route('wallet.index'), auth: true },
        { label: 'Wishlist', href: route('wishlist.index'), auth: true },
        { label: 'My Orders', href: route('orders.index'), auth: true },
        { label: 'Messages', href: route('chat.index'), auth: true, chat: true },
        { label: 'Sell on CityShop', href: route('register.seller') },
        { label: 'Contact', href: route('contact') },
        { label: 'FAQ', href: route('faq') },
    ];

    const openMessages = (e?: React.MouseEvent) => {
        e?.preventDefault();
        if (chat) {
            chat.openWidget();
        } else {
            router.visit(route('chat.index'));
        }
    };

    const dashboardLink = () => {
        if (!auth.user) return route('login');
        const role = auth.user.role as string;
        if (role === 'admin') return route('admin.dashboard');
        if (role === 'seller') return route('seller.dashboard');
        return route('orders.index');
    };

    return (
        <header className="sticky top-0 z-50 border-b border-gray-100/80 bg-white/95 shadow-sm backdrop-blur-md">
            <div className="mx-auto max-w-7xl px-4 py-3">
                <div className="flex items-center gap-4">
                    <Link href={route('home')} className="flex shrink-0 items-center gap-2">
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-orange-500">
                            <span className="text-lg font-bold text-white">C</span>
                        </div>
                        <span className="text-xl font-bold text-gray-900">
                            City<span className="text-orange-500">Shop</span>
                        </span>
                    </Link>

                    <form onSubmit={handleSearch} className="mx-auto hidden max-w-2xl flex-1 md:flex">
                        <div className="flex w-full overflow-hidden rounded-2xl border-2 border-orange-100 bg-gray-50 transition-colors focus-within:border-orange-300 focus-within:bg-white">
                            <Input
                                type="search"
                                placeholder="Search products, brands, categories..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="border-0 bg-transparent focus-visible:ring-0"
                            />
                            <Button type="submit" className="rounded-none rounded-r-2xl bg-gradient-to-r from-orange-500 to-orange-600 px-6 hover:from-orange-600 hover:to-orange-700">
                                <Search className="h-4 w-4" />
                                <span className="ml-1 hidden sm:inline">Search</span>
                            </Button>
                        </div>
                    </form>

                    <div className="ml-auto flex items-center gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboardLink()}
                                className="hidden items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:flex"
                            >
                                <User className="h-4 w-4" />
                                {auth.user.name}
                            </Link>
                        ) : (
                            <Link
                                href={route('login')}
                                className="hidden items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:flex"
                            >
                                <LogIn className="h-4 w-4" />
                                Login
                            </Link>
                        )}

                        {auth.user && (
                            <button
                                type="button"
                                onClick={openMessages}
                                className="relative hidden rounded-lg p-2 hover:bg-gray-50 sm:block"
                                title="Messages"
                            >
                                <MessageCircle className="h-5 w-5 text-gray-700" />
                                {(page.props.unreadMessages ?? 0) > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                                        {(page.props.unreadMessages ?? 0) > 9 ? '9+' : page.props.unreadMessages}
                                    </span>
                                )}
                            </button>
                        )}

                        <NotificationBell />

                        <Link href={auth.user ? route('wishlist.index') : route('login')} className="relative rounded-lg p-2 hover:bg-gray-50">
                            <Heart className="h-5 w-5 text-gray-700" />
                            {wishlistCount > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                                    {wishlistCount}
                                </span>
                            )}
                        </Link>

                        <Link href={auth.user ? route('cart.index') : route('login')} className="relative rounded-lg p-2 hover:bg-gray-50">
                            <ShoppingCart className="h-5 w-5 text-gray-700" />
                            {cartCount > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                                    {cartCount}
                                </span>
                            )}
                        </Link>

                        <button
                            type="button"
                            className="rounded-lg p-2 hover:bg-gray-50 md:hidden"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        >
                            {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                </div>

                <nav className="mt-3 hidden items-center gap-6 border-t border-gray-50 pt-3 md:flex">
                    {navLinks.map((link) =>
                        link.auth && !auth.user ? null : link.chat ? (
                            <button
                                key={link.label}
                                type="button"
                                onClick={openMessages}
                                className="text-sm font-medium text-gray-600 hover:text-orange-500"
                            >
                                {link.label}
                            </button>
                        ) : (
                            <Link key={link.label} href={link.href} className="text-sm font-medium text-gray-600 hover:text-orange-500">
                                {link.label}
                            </Link>
                        ),
                    )}
                    {!auth.user && (
                        <Link
                            href={route('register.buyer')}
                            className="ml-auto flex items-center gap-1 rounded-full bg-blue-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-600"
                        >
                            <Store className="h-3.5 w-3.5" />
                            Register
                        </Link>
                    )}
                </nav>
            </div>

            {mobileMenuOpen && (
                <div className="border-t border-gray-100 bg-white px-4 py-3 md:hidden">
                    <form onSubmit={handleSearch} className="mb-3">
                        <Input placeholder="Search products..." value={search} onChange={(e) => setSearch(e.target.value)} />
                    </form>
                    {navLinks.map((link) =>
                        link.chat ? (
                            <button
                                key={link.label}
                                type="button"
                                onClick={() => {
                                    openMessages();
                                    setMobileMenuOpen(false);
                                }}
                                className="block py-2 text-sm font-medium text-gray-600"
                            >
                                {link.label}
                            </button>
                        ) : (
                            <Link
                                key={link.label}
                                href={link.href}
                                className="block py-2 text-sm font-medium text-gray-600"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                {link.label}
                            </Link>
                        ),
                    )}
                </div>
            )}
        </header>
    );
}
