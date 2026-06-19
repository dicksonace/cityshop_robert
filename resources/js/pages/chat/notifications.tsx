import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Bell, MessageCircle } from 'lucide-react';

import ShopLayout from '@/layouts/shop-layout';

interface NotificationItem {
    id: number;
    type: string;
    title: string;
    body?: string;
    data?: { conversation_id?: number };
    read_at?: string | null;
    created_at?: string;
}

interface NotificationsPageProps {
    notifications: NotificationItem[];
}

export default function NotificationsPage({ notifications }: NotificationsPageProps) {
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
            if (item.data?.conversation_id) {
                router.visit(route('chat.show', item.data.conversation_id));
            }
        });
    };

    return (
        <ShopLayout>
            <Head title="Notifications" />
            <div className="mx-auto max-w-2xl px-4 py-8">
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('home')} className="rounded-lg p-2 hover:bg-gray-100">
                            <ArrowLeft className="h-5 w-5 text-gray-600" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Notifications</h1>
                            <p className="text-sm text-gray-500">Messages, calls, and activity alerts</p>
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
                    <div className="rounded-2xl border border-dashed border-gray-200 py-16 text-center">
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
                                    {n.type === 'call' ? (
                                        <Bell className="h-5 w-5 text-orange-500" />
                                    ) : (
                                        <MessageCircle className="h-5 w-5 text-orange-500" />
                                    )}
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
        </ShopLayout>
    );
}
