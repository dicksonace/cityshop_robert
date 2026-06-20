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
        setMobileMenuOpen(false);
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
            <div className="mx-auto max-w-7xl px-3 py-2 sm:px-4 sm:py-3">
                <div className="flex items-center gap-2 sm:gap-4">
                    <Link href={route('home')} className="flex shrink-0 items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-orange-500 sm:h-9 sm:w-9">
                            <span className="text-base font-bold text-white sm:text-lg">C</span>
                        </div>
                        <span className="hidden text-xl font-bold text-gray-900 sm:inline">
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

                    <div className="ml-auto flex items-center gap-0.5 sm:gap-2">
                        {auth.user ? (
                            <Link
                                href={dashboardLink()}
                                className="hidden items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 md:flex"
                            >
                                <User className="h-4 w-4" />
                                <span className="max-w-[6rem] truncate">{auth.user.name}</span>
                            </Link>
                        ) : (
                            <Link
                                href={route('login')}
                                className="hidden items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 md:flex"
                            >
                                <LogIn className="h-4 w-4" />
                                Login
                            </Link>
                        )}

                        {auth.user && (
                            <button
                                type="button"
                                onClick={openMessages}
                                className="relative hidden rounded-lg p-1.5 hover:bg-gray-50 sm:block sm:p-2"
                                title="Messages"
                            >
                                <MessageCircle className="h-5 w-5 text-gray-700" />
                                {(page.props.unreadMessages ?? 0) > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white sm:h-5 sm:w-5 sm:text-xs">
                                        {(page.props.unreadMessages ?? 0) > 9 ? '9+' : page.props.unreadMessages}
                                    </span>
                                )}
                            </button>
                        )}

                        <NotificationBell />

                        <Link
                            href={auth.user ? route('wishlist.index') : route('login')}
                            className="relative hidden rounded-lg p-1.5 hover:bg-gray-50 sm:block sm:p-2"
                        >
                            <Heart className="h-5 w-5 text-gray-700" />
                            {wishlistCount > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white sm:h-5 sm:w-5 sm:text-xs">
                                    {wishlistCount}
                                </span>
                            )}
                        </Link>

                        <Link
                            href={auth.user ? route('cart.index') : route('login')}
                            className="relative rounded-lg p-1.5 hover:bg-gray-50 sm:p-2"
                        >
                            <ShoppingCart className="h-5 w-5 text-gray-700" />
                            {cartCount > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white sm:h-5 sm:w-5 sm:text-xs">
                                    {cartCount}
                                </span>
                            )}
                        </Link>

                        <button
                            type="button"
                            className="rounded-lg p-1.5 hover:bg-gray-50 md:hidden"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                        >
                            {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                </div>

                <nav className="mt-2 hidden items-center gap-6 border-t border-gray-50 pt-2 md:mt-3 md:flex md:pt-3">
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
                <div className="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-gray-100 bg-white px-3 py-3 md:hidden">
                    <form onSubmit={handleSearch} className="mb-3 flex gap-2">
                        <Input
                            placeholder="Search products..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="flex-1"
                        />
                        <Button type="submit" size="icon" className="shrink-0 bg-orange-500 hover:bg-orange-600">
                            <Search className="h-4 w-4" />
                        </Button>
                    </form>
                    {auth.user && (
                        <Link
                            href={dashboardLink()}
                            className="mb-2 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-700"
                            onClick={() => setMobileMenuOpen(false)}
                        >
                            <User className="h-4 w-4" />
                            {auth.user.name}
                        </Link>
                    )}
                    {navLinks.map((link) =>
                        link.auth && !auth.user ? null : link.chat ? (
                            <button
                                key={link.label}
                                type="button"
                                onClick={() => {
                                    openMessages();
                                    setMobileMenuOpen(false);
                                }}
                                className="block w-full py-2.5 text-left text-sm font-medium text-gray-600"
                            >
                                {link.label}
                            </button>
                        ) : (
                            <Link
                                key={link.label}
                                href={link.href}
                                className="block py-2.5 text-sm font-medium text-gray-600"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                {link.label}
                            </Link>
                        ),
                    )}
                    {!auth.user && (
                        <Link
                            href={route('login')}
                            className="mt-2 block rounded-lg border border-gray-200 py-2.5 text-center text-sm font-medium text-gray-700"
                            onClick={() => setMobileMenuOpen(false)}
                        >
                            Login
                        </Link>
                    )}
                </div>
            )}
        </header>
    );
}
