import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Camera,
    Heart,
    KeyRound,
    LayoutDashboard,
    LogIn,
    LogOut,
    MapPin,
    Menu,
    MessageCircle,
    Package,
    ShoppingCart,
    Store,
    Truck,
    User,
    Wallet,
    X,
} from 'lucide-react';
import { ComponentType, useEffect, useState } from 'react';

import NotificationBell from '@/components/shop/notification-bell';
import SearchBox from '@/components/shop/search-box';
import CityShopBrand from '@/components/cityshop-brand';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useChatOptional } from '@/contexts/chat-context';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';

type NavLink = {
    label: string;
    href: string;
    icon?: ComponentType<{ className?: string }>;
    chat?: boolean;
    highlight?: boolean;
};

const iconButtonClass =
    'relative inline-flex h-10 w-10 items-center justify-center rounded-xl text-gray-600 transition-colors hover:bg-orange-50 hover:text-orange-600';

/** Wraps an icon so the count sits on the icon corner, not the button corner. */
function IconWithCount({ icon: Icon, count }: { icon: ComponentType<{ className?: string }>; count: number }) {
    return (
        <span className="relative inline-flex">
            <Icon className="h-5 w-5" />
            {count > 0 && (
                <span className="absolute -top-1.5 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                    {count > 9 ? '9+' : count}
                </span>
            )}
        </span>
    );
}

export default function ShopHeader({ hideSearch = false }: { hideSearch?: boolean }) {
    const page = usePage<SharedData & { cartCount: number; wishlistCount: number; unreadMessages?: number }>();
    const { auth, cartCount, wishlistCount } = page.props;
    const chat = useChatOptional();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const params = new URLSearchParams(page.url.split('?')[1] ?? '');
    const initialSearch = params.get('q') ?? params.get('search') ?? '';
    const component = typeof page.component === 'string' ? page.component : '';
    const path = page.url.split('?')[0] || '/';
    // Back beside search on store / product / search pages (not shop home).
    const showSearchBack = ['shop/store', 'shop/product-show', 'shop/search', 'shop/image-search'].includes(component);

    const role = auth.user?.role as string | undefined;
    const isSeller = role === 'seller';
    const isAdmin = role === 'admin';
    const isStaff = isAdmin || isSeller;
    const unreadMessages = page.props.unreadMessages ?? 0;
    const firstName = auth.user?.name?.split(' ')[0] ?? '';

    useEffect(() => {
        setMobileMenuOpen(false);
    }, [page.url]);

    const isActive = (href: string) => {
        let target = href;
        try {
            target = new URL(href, 'http://cityshop.local').pathname;
        } catch {
            return false;
        }
        return target === '/' ? path === '/' : path.startsWith(target);
    };

    // Browse-focused nav only — account destinations live in the account menu.
    const buyerNavLinks: NavLink[] = [
        { label: 'Shop all', href: route('home'), icon: Store },
        { label: 'Search by photo', href: route('search.image'), icon: Camera },
        { label: 'My orders', href: route('orders.index'), icon: Package },
        { label: 'Wishlist', href: route('wishlist.index'), icon: Heart },
    ];

    const guestNavLinks: NavLink[] = [
        { label: 'Shop all', href: route('home'), icon: Store },
        { label: 'Search by photo', href: route('search.image'), icon: Camera },
        { label: 'Help', href: route('faq') },
        { label: 'Contact', href: route('contact') },
    ];

    const sellerNavLinks: NavLink[] = [
        { label: 'Seller Centre', href: route('seller.dashboard'), icon: LayoutDashboard, highlight: true },
        { label: 'Products', href: route('seller.products.index'), icon: Package },
        { label: 'Orders', href: route('seller.orders.index'), icon: ShoppingCart },
        { label: 'Earnings', href: route('seller.wallet'), icon: Wallet },
        { label: 'Messages', href: route('chat.index'), icon: MessageCircle, chat: true },
        { label: 'Browse marketplace', href: route('home'), icon: Store },
    ];

    const adminNavLinks: NavLink[] = [
        { label: 'Admin Dashboard', href: route('admin.dashboard'), icon: LayoutDashboard, highlight: true },
        { label: 'Browse marketplace', href: route('home'), icon: Store },
        { label: 'Help', href: route('faq') },
        { label: 'Contact', href: route('contact') },
    ];

    const activeNavLinks = isSeller ? sellerNavLinks : isAdmin ? adminNavLinks : auth.user ? buyerNavLinks : guestNavLinks;

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
        if (isAdmin) return route('admin.dashboard');
        if (isSeller) return route('seller.dashboard');
        return route('orders.index');
    };

    const dashboardLabel = () => {
        if (!auth.user) return 'Dashboard';
        if (isAdmin) return 'Admin Dashboard';
        if (isSeller) return 'Seller Centre';
        return 'My Orders';
    };

    const renderDesktopNavLink = (link: NavLink) => {
        const active = !link.chat && isActive(link.href);
        const className = cn(
            'relative inline-flex items-center gap-1.5 py-2 text-sm transition-colors',
            link.highlight ? 'font-semibold text-orange-600 hover:text-orange-700' : 'font-medium text-gray-600 hover:text-orange-600',
            active && !link.highlight && 'text-orange-600',
        );
        const Icon = link.icon;
        const underline = active && (
            <span className="absolute inset-x-0 -bottom-[9px] h-0.5 rounded-full bg-orange-500" />
        );

        if (link.chat) {
            return (
                <button key={link.label} type="button" onClick={() => openMessages()} className={className}>
                    {Icon && <Icon className="h-4 w-4" />}
                    {link.label}
                    {unreadMessages > 0 && (
                        <span className="rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">
                            {unreadMessages > 9 ? '9+' : unreadMessages}
                        </span>
                    )}
                </button>
            );
        }

        return (
            <Link key={link.label} href={link.href} className={className}>
                {Icon && <Icon className="h-4 w-4" />}
                {link.label}
                {underline}
            </Link>
        );
    };

    const renderMobileNavLink = (link: NavLink) => {
        const active = !link.chat && isActive(link.href);
        const className = cn(
            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
            active ? 'bg-orange-50 text-orange-700' : 'text-gray-700 hover:bg-gray-50',
            link.highlight && 'bg-orange-50 text-orange-700',
        );
        const Icon = link.icon;

        if (link.chat) {
            return (
                <button
                    key={link.label}
                    type="button"
                    onClick={() => {
                        openMessages();
                        setMobileMenuOpen(false);
                    }}
                    className={cn(className, 'w-full text-left')}
                >
                    {Icon && <Icon className="h-4 w-4 text-gray-400" />}
                    {link.label}
                </button>
            );
        }

        return (
            <Link key={link.label} href={link.href} className={className} onClick={() => setMobileMenuOpen(false)}>
                {Icon && <Icon className={cn('h-4 w-4', active ? 'text-orange-500' : 'text-gray-400')} />}
                {link.label}
            </Link>
        );
    };

    return (
        <header className="sticky top-0 z-50 border-b border-gray-200/80 bg-white/95 shadow-sm backdrop-blur-md">
            {/* Utility strip — trust signals and quick help */}
            <div className="hidden border-b border-gray-100 bg-gray-50/80 md:block">
                <div className="mx-auto flex h-9 max-w-7xl items-center justify-between px-4 text-xs">
                    <p className="flex items-center gap-1.5 text-gray-500">
                        <Truck className="h-3.5 w-3.5 text-orange-500" />
                        Delivery across Ghana
                        <span className="mx-1 text-gray-300">|</span>
                        <MapPin className="h-3.5 w-3.5 text-orange-500" />
                        Buy from verified local stores
                    </p>
                    <div className="flex items-center gap-4 text-gray-500">
                        <Link href={route('faq')} className="hover:text-orange-600">
                            Help
                        </Link>
                        <Link href={route('contact')} className="hover:text-orange-600">
                            Contact
                        </Link>
                        {auth.user ? (
                            <span className="text-gray-400">
                                Hi, <span className="font-medium text-gray-600">{firstName}</span>
                            </span>
                        ) : (
                            <>
                                <Link href={route('login')} className="hover:text-orange-600">
                                    Log in
                                </Link>
                                <Link href={route('register.buyer')} className="font-medium text-orange-600 hover:text-orange-700">
                                    Create account
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* Main bar — brand, search, actions */}
            <div className="mx-auto max-w-7xl px-3 py-2.5 sm:px-4 sm:py-3">
                <div className="flex items-center gap-3 sm:gap-5">
                    <CityShopBrand size="sm" className="shrink-0" />

                    <div className={cn('hidden min-w-0 flex-1 md:flex', hideSearch && 'md:hidden')}>
                        <SearchBox
                            initialQuery={initialSearch}
                            className="w-full"
                            showBack={showSearchBack}
                            backHref={route('home')}
                        />
                    </div>

                    {hideSearch && <div className="hidden flex-1 md:block" />}

                    <div className="ml-auto flex items-center gap-1 sm:gap-1.5">
                        {auth.user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        className="hidden items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-orange-200 hover:bg-orange-50 hover:text-orange-700 md:flex"
                                    >
                                        <span className="flex h-6 w-6 items-center justify-center rounded-full bg-orange-100 text-[11px] font-bold text-orange-700">
                                            {(firstName.charAt(0) || 'U').toUpperCase()}
                                        </span>
                                        <span className="max-w-[6rem] truncate">{firstName}</span>
                                        <ChevronDown className="h-3.5 w-3.5 text-gray-400" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                    <div className="px-2 py-1.5">
                                        <p className="truncate text-sm font-semibold text-gray-900">{auth.user.name}</p>
                                        <p className="text-xs text-gray-500">
                                            {isSeller ? 'Seller account' : isAdmin ? 'Admin account' : 'Buyer account'}
                                        </p>
                                    </div>
                                    <DropdownMenuSeparator />
                                    {isStaff && (
                                        <DropdownMenuItem asChild>
                                            <Link href={dashboardLink()} className="flex w-full cursor-pointer items-center font-medium text-orange-600">
                                                <LayoutDashboard className="mr-2 h-4 w-4" />
                                                {dashboardLabel()}
                                            </Link>
                                        </DropdownMenuItem>
                                    )}
                                    {isSeller && (
                                        <>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('seller.products.index')} className="flex w-full cursor-pointer items-center">
                                                    <Package className="mr-2 h-4 w-4" />
                                                    My Products
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('seller.orders.index')} className="flex w-full cursor-pointer items-center">
                                                    <ShoppingCart className="mr-2 h-4 w-4" />
                                                    Seller Orders
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('seller.wallet')} className="flex w-full cursor-pointer items-center">
                                                    <Wallet className="mr-2 h-4 w-4" />
                                                    Earnings
                                                </Link>
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                    {isStaff && <DropdownMenuSeparator />}
                                    {!isStaff && (
                                        <>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('account.index')} className="flex w-full cursor-pointer items-center">
                                                    <User className="mr-2 h-4 w-4" />
                                                    My account
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('orders.index')} className="flex w-full cursor-pointer items-center">
                                                    <Package className="mr-2 h-4 w-4" />
                                                    My Orders
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('wallet.index')} className="flex w-full cursor-pointer items-center">
                                                    <Wallet className="mr-2 h-4 w-4" />
                                                    Wallet
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('wishlist.index')} className="flex w-full cursor-pointer items-center">
                                                    <Heart className="mr-2 h-4 w-4" />
                                                    Wishlist
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={route('addresses.index')} className="flex w-full cursor-pointer items-center">
                                                    <MapPin className="mr-2 h-4 w-4" />
                                                    Addresses
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                        </>
                                    )}
                                    <DropdownMenuItem asChild>
                                        <Link href={route('profile.edit')} className="flex w-full cursor-pointer items-center">
                                            <User className="mr-2 h-4 w-4" />
                                            Profile settings
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route('password.edit')} className="flex w-full cursor-pointer items-center">
                                            <KeyRound className="mr-2 h-4 w-4" />
                                            Change password
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="flex w-full cursor-pointer items-center text-red-600"
                                        >
                                            <LogOut className="mr-2 h-4 w-4" />
                                            Log out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <Link
                                href={route('login')}
                                className="hidden items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-orange-200 hover:bg-orange-50 hover:text-orange-700 md:flex"
                            >
                                <LogIn className="h-4 w-4" />
                                Login
                            </Link>
                        )}

                        {auth.user && !isStaff && (
                            <button type="button" onClick={openMessages} className={cn(iconButtonClass, 'hidden sm:inline-flex')} title="Messages">
                                <IconWithCount icon={MessageCircle} count={unreadMessages} />
                            </button>
                        )}

                        <NotificationBell />

                        {auth.user && !isStaff && (
                            <Link href={route('wishlist.index')} className={cn(iconButtonClass, 'hidden sm:inline-flex')} title="Wishlist">
                                <IconWithCount icon={Heart} count={wishlistCount} />
                            </Link>
                        )}

                        <Link
                            href={auth.user ? route('cart.index') : route('login')}
                            className={cn(iconButtonClass, 'md:w-auto md:gap-2.5 md:px-3')}
                            title="Cart"
                        >
                            <IconWithCount icon={ShoppingCart} count={cartCount} />
                            <span className="hidden text-sm font-medium md:inline">Cart</span>
                        </Link>

                        <button
                            type="button"
                            className={cn(iconButtonClass, 'md:hidden')}
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={mobileMenuOpen}
                        >
                            {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                </div>

                {/* Mobile search — hidden on shop home (search lives above products) */}
                {!hideSearch && (
                    <div className="mt-2.5 md:hidden">
                        <SearchBox
                            initialQuery={initialSearch}
                            compact
                            showBack={showSearchBack}
                            backHref={route('home')}
                            onSubmitted={() => setMobileMenuOpen(false)}
                        />
                    </div>
                )}
            </div>

            {/* Browse nav */}
            <div className="hidden border-t border-gray-100 md:block">
                <nav className="mx-auto flex max-w-7xl items-center gap-6 px-4">
                    {activeNavLinks.map(renderDesktopNavLink)}
                    {!auth.user && (
                        <Link
                            href={route('register.buyer')}
                            className="my-1.5 ml-auto inline-flex items-center gap-1.5 rounded-full bg-orange-500 px-4 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-orange-600"
                        >
                            <Store className="h-3.5 w-3.5" />
                            Create free account
                        </Link>
                    )}
                </nav>
            </div>

            {/* Mobile drawer */}
            {mobileMenuOpen && (
                <div className="max-h-[calc(100dvh-8rem)] overflow-y-auto border-t border-gray-100 bg-white px-3 py-3 md:hidden">
                    {auth.user ? (
                        <div className="mb-3 flex items-center gap-3 rounded-xl bg-gray-50 px-3 py-2.5">
                            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-orange-100 text-sm font-bold text-orange-700">
                                {(firstName.charAt(0) || 'U').toUpperCase()}
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-gray-900">{auth.user.name}</p>
                                <p className="text-xs text-gray-500">
                                    {isSeller ? 'Seller account' : isAdmin ? 'Admin account' : 'Buyer account'}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="mb-3 grid grid-cols-2 gap-2">
                            <Link
                                href={route('login')}
                                className="rounded-xl border border-gray-200 py-2.5 text-center text-sm font-medium text-gray-700"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Log in
                            </Link>
                            <Link
                                href={route('register.buyer')}
                                className="rounded-xl bg-orange-500 py-2.5 text-center text-sm font-semibold text-white"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Create account
                            </Link>
                        </div>
                    )}

                    <p className="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Browse</p>
                    <div className="space-y-0.5">{activeNavLinks.map(renderMobileNavLink)}</div>

                    {auth.user && !isStaff && (
                        <>
                            <p className="mt-3 px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Account</p>
                            <div className="space-y-0.5">
                                {renderMobileNavLink({ label: 'My account', href: route('account.index'), icon: User })}
                                {renderMobileNavLink({ label: 'Wallet', href: route('wallet.index'), icon: Wallet })}
                                {renderMobileNavLink({ label: 'Addresses', href: route('addresses.index'), icon: MapPin })}
                            </div>
                        </>
                    )}

                    <p className="mt-3 px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Support</p>
                    <div className="space-y-0.5">
                        {renderMobileNavLink({ label: 'Contact us', href: route('contact') })}
                        {renderMobileNavLink({ label: 'FAQ', href: route('faq') })}
                    </div>

                    {auth.user && (
                        <>
                            <div className="mt-3 space-y-0.5 border-t border-gray-100 pt-3">
                                {renderMobileNavLink({ label: 'Profile settings', href: route('profile.edit'), icon: User })}
                                {renderMobileNavLink({ label: 'Change password', href: route('password.edit'), icon: KeyRound })}
                            </div>
                            <Button
                                variant="outline"
                                className="mt-3 w-full"
                                onClick={() => {
                                    setMobileMenuOpen(false);
                                    router.post(route('logout'));
                                }}
                            >
                                <LogOut className="mr-2 h-4 w-4" />
                                Log out
                            </Button>
                        </>
                    )}
                </div>
            )}
        </header>
    );
}
