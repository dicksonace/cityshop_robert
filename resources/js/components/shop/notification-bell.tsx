import { Link, router, usePage } from '@inertiajs/react';
import { Bell, MessageCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { useChatOptional } from '@/contexts/chat-context';
import { SharedData } from '@/types';

export default function NotificationBell() {
    const { auth, unreadMessages, unreadNotifications } = usePage<
        SharedData & { unreadMessages?: number; unreadNotifications?: number }
    >().props;
    const chat = useChatOptional();
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<
        Array<{ id: number; type: string; title: string; body?: string; read_at?: string | null; created_at?: string; data?: { conversation_id?: number } }>
    >([]);
    const [loading, setLoading] = useState(false);
    const panelRef = useRef<HTMLDivElement>(null);

    const totalUnread = (unreadMessages ?? 0) + (unreadNotifications ?? 0);

    useEffect(() => {
        if (!open || !auth.user) return;

        setLoading(true);
        fetch(route('notifications.index'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => r.json())
            .then((data) => setNotifications(data.notifications ?? []))
            .finally(() => setLoading(false));
    }, [open, auth.user]);

    useEffect(() => {
        const handleClick = (e: MouseEvent) => {
            if (panelRef.current && !panelRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    if (!auth.user) return null;

    const markAllRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
        setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })));
    };

    const openNotification = (id: number, conversationId?: number) => {
        fetch(route('notifications.read', id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        setOpen(false);
        if (conversationId && chat) {
            chat.openConversation(conversationId);
        } else if (conversationId) {
            router.visit(route('chat.show', conversationId));
        } else {
            router.visit(route('notifications.index'));
        }
    };

    return (
        <div className="relative" ref={panelRef}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="relative rounded-lg p-2 hover:bg-gray-50"
                aria-label="Notifications"
            >
                <Bell className="h-5 w-5 text-gray-700" />
                {totalUnread > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">
                        {totalUnread > 9 ? '9+' : totalUnread}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-50 mt-2 w-[min(calc(100vw-1.5rem),20rem)] overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl">
                    <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <h3 className="text-sm font-semibold text-gray-900">Notifications</h3>
                        {totalUnread > 0 && (
                            <button type="button" onClick={markAllRead} className="text-xs text-orange-500 hover:underline">
                                Mark all read
                            </button>
                        )}
                    </div>

                    <div className="max-h-80 overflow-y-auto">
                        {loading ? (
                            <p className="px-4 py-6 text-center text-sm text-gray-400">Loading...</p>
                        ) : notifications.length === 0 ? (
                            <p className="px-4 py-6 text-center text-sm text-gray-400">No notifications yet</p>
                        ) : (
                            notifications.map((n) => (
                                <button
                                    key={n.id}
                                    type="button"
                                    onClick={() => openNotification(n.id, n.data?.conversation_id)}
                                    className={`flex w-full gap-3 border-b border-gray-50 px-4 py-3 text-left hover:bg-gray-50 ${!n.read_at ? 'bg-orange-50/50' : ''}`}
                                >
                                    <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100">
                                        {n.type === 'message' || n.type === 'call' ? (
                                            <MessageCircle className="h-4 w-4 text-orange-500" />
                                        ) : (
                                            <Bell className="h-4 w-4 text-orange-500" />
                                        )}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-gray-900">{n.title}</p>
                                        {n.body && <p className="truncate text-xs text-gray-500">{n.body}</p>}
                                    </div>
                                </button>
                            ))
                        )}
                    </div>

                    <div className="flex border-t border-gray-100">
                        <button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                chat?.openWidget();
                            }}
                            className="flex flex-1 items-center justify-center gap-1 py-2.5 text-xs font-medium text-orange-500 hover:bg-gray-50"
                        >
                            <MessageCircle className="h-3.5 w-3.5" />
                            Messages
                            {(unreadMessages ?? 0) > 0 && (
                                <span className="rounded-full bg-red-500 px-1.5 text-[10px] text-white">{unreadMessages}</span>
                            )}
                        </button>
                        <Link
                            href={route('notifications.index')}
                            className="flex flex-1 items-center justify-center border-l border-gray-100 py-2.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                            onClick={() => setOpen(false)}
                        >
                            View all
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
