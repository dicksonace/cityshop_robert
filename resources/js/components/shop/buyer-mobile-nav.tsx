import { Link, router, usePage } from '@inertiajs/react';
import { MessageCircle, Package, Store, UserRound, Wallet } from 'lucide-react';

import { useChatOptional } from '@/contexts/chat-context';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';

type BuyerNavKey = 'shop' | 'wallet' | 'orders' | 'messages' | 'profile';

const items: {
    key: BuyerNavKey;
    label: string;
    href?: string;
    match: (path: string) => boolean;
    chat?: boolean;
}[] = [
    { key: 'shop', label: 'Shop', href: route('home'), match: (p) => p === '/' || p.startsWith('/search') || p.startsWith('/products') || p.startsWith('/store') },
    { key: 'wallet', label: 'Wallet', href: route('wallet.index'), match: (p) => p.startsWith('/wallet') },
    { key: 'orders', label: 'My Order', href: route('orders.index'), match: (p) => p.startsWith('/my-orders') || p.startsWith('/checkouts') || p.startsWith('/checkout') },
    { key: 'messages', label: 'Message', chat: true, match: (p) => p.startsWith('/messages') },
    { key: 'profile', label: 'Profile', href: route('account.index'), match: (p) => p.startsWith('/account') || p.startsWith('/settings') || p.startsWith('/addresses') || p.startsWith('/wishlist') },
];

const icons: Record<BuyerNavKey, typeof Store> = {
    shop: Store,
    wallet: Wallet,
    orders: Package,
    messages: MessageCircle,
    profile: UserRound,
};

export default function BuyerMobileNav() {
    const { url, props } = usePage<SharedData & { unreadMessages?: number }>();
    const chat = useChatOptional();
    const unread = props.unreadMessages ?? 0;
    const path = url.split('?')[0] || '/';

    const openMessages = () => {
        if (chat) {
            chat.openWidget();
            return;
        }
        router.visit(route('chat.index'));
    };

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] sm:hidden"
            aria-label="Buyer navigation"
        >
            <div className="mx-auto flex max-w-lg items-stretch justify-around">
                {items.map((item) => {
                    const Icon = icons[item.key];
                    const isActive = item.match(path) || (item.chat && chat?.isOpen);
                    const content = (
                        <>
                            <span
                                className={cn(
                                    'relative flex h-9 w-9 items-center justify-center rounded-full transition',
                                    isActive ? 'bg-orange-500 text-white' : 'text-gray-500',
                                )}
                            >
                                <Icon className="h-5 w-5" />
                                {item.chat && unread > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">
                                        {unread > 9 ? '9+' : unread}
                                    </span>
                                )}
                            </span>
                            <span className={cn('max-w-[4.5rem] truncate', isActive ? 'text-orange-600' : 'text-gray-500')}>
                                {item.label}
                            </span>
                        </>
                    );

                    if (item.chat) {
                        return (
                            <button
                                key={item.key}
                                type="button"
                                onClick={openMessages}
                                className="flex flex-1 flex-col items-center gap-0.5 py-1.5 text-[10px] font-medium"
                            >
                                {content}
                            </button>
                        );
                    }

                    return (
                        <Link
                            key={item.key}
                            href={item.href!}
                            className="flex flex-1 flex-col items-center gap-0.5 py-1.5 text-[10px] font-medium"
                        >
                            {content}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
