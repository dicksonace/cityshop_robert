import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Bell, MessageCircle, Package, Shield } from 'lucide-react';
import { ReactNode } from 'react';

import SellerLayout from '@/layouts/seller-layout';
import ShopLayout from '@/layouts/shop-layout';

interface NotificationItem {
    id: number;
    type: string;
    title: string;
    body?: string;
    data?: { conversation_id?: number; order_id?: number; url?: string };
    read_at?: string | null;
    created_at?: string;
}

interface NotificationsPageProps {
    notifications: NotificationItem[];
    layout?: 'shop' | 'seller';
}

function notificationHref(item: NotificationItem): string | null {
    if (item.data?.url) return item.data.url;
    if (item.data?.conversation_id) return route('chat.show', item.data.conversation_id);
    if (item.data?.order_id) return route('seller.orders.show', item.data.order_id);
    return null;
}

function NotificationIcon({ type }: { type: string }) {
    if (type === 'new_order') return <Package className="h-5 w-5 text-orange-500" />;
    if (type === 'admin_message' || type === 'dispute') return <Shield className="h-5 w-5 text-orange-500" />;
    if (type === 'call') return <Bell className="h-5 w-5 text-orange-500" />;
    return <MessageCircle className="h-5 w-5 text-orange-500" />;
}

function PageShell({ layout, children }: { layout: 'shop' | 'seller'; children: ReactNode }) {
    if (layout === 'seller') {
        return (
            <SellerLayout title="Notifications" active="notifications">
                {children}
            </SellerLayout>
        );
    }

    return <ShopLayout>{children}</ShopLayout>;
}

export default function NotificationsPage({ notifications, layout = 'shop' }: NotificationsPageProps) {
    const markAllRead = () => {
        router.post(route('notifications.read-all'));
    };

    const openItem = (item: NotificationItem) => {
        fetch(route('notifications.read', item.id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(() => {
            const href = notificationHref(item);
            if (href) {
                router.visit(href);
            }
        });
    };

    return (
        <PageShell layout={layout}>
            <Head title="Notifications" />
            <div className={layout === 'seller' ? 'mx-auto max-w-2xl' : 'mx-auto max-w-2xl px-4 py-8'}>
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        {layout === 'shop' && (
                            <Link href={route('home')} className="rounded-lg p-2 hover:bg-gray-100">
                                <ArrowLeft className="h-5 w-5 text-gray-600" />
                            </Link>
                        )}
                        <div>
                            <h1 className={layout === 'seller' ? 'text-xl font-bold text-gray-900' : 'text-2xl font-bold text-gray-900'}>
                                Notifications
                            </h1>
                            <p className="text-sm text-gray-500">Orders, messages, and admin alerts</p>
                        </div>
                    </div>
                    {notifications.some((n) => !n.read_at) && (
                        <button type="button" onClick={markAllRead} className="text-sm text-orange-500 hover:underline">
                            Mark all read
                        </button>
                    )}
                </div>

                <div className="mb-4">
                    <Link href={route('chat.index')} className="inline-flex items-center gap-2 text-sm text-orange-500 hover:underline">
                        <MessageCircle className="h-4 w-4" />
                        Go to Messages
                    </Link>
                </div>

                {notifications.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-gray-200 bg-white py-16 text-center">
                        <Bell className="mx-auto h-12 w-12 text-gray-300" />
                        <p className="mt-4 text-gray-500">No notifications yet</p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                        {notifications.map((n) => (
                            <button
                                key={n.id}
                                type="button"
                                onClick={() => openItem(n)}
                                className={`flex w-full gap-4 border-b border-gray-50 px-4 py-4 text-left transition-colors last:border-0 hover:bg-gray-50 ${!n.read_at ? 'bg-orange-50/40' : ''}`}
                            >
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100">
                                    <NotificationIcon type={n.type} />
                                </div>
                                <div className="min-w-0">
                                    <p className="font-medium text-gray-900">{n.title}</p>
                                    {n.body && <p className="mt-0.5 text-sm text-gray-500">{n.body}</p>}
                                    {n.created_at && (
                                        <p className="mt-1 text-xs text-gray-400">
                                            {new Date(n.created_at).toLocaleString('en-GH')}
                                        </p>
                                    )}
                                </div>
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </PageShell>
    );
}
