import { usePage } from '@inertiajs/react';
import { MessageCircle, Minus, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import ChatListPanel from '@/components/chat/chat-list-panel';
import ChatThreadPanel from '@/components/chat/chat-thread-panel';
import { useChat } from '@/contexts/chat-context';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';

export default function FloatingChatWidget() {
    const { auth, unreadMessages, flash } = usePage<SharedData & { unreadMessages?: number; flash?: { openChat?: boolean } }>().props;
    const { isOpen, view, openWidget, closeWidget } = useChat();
    const [mounted, setMounted] = useState(false);

    useEffect(() => setMounted(true), []);

    useEffect(() => {
        if (flash?.openChat) {
            openWidget();
        }
    }, [flash?.openChat, openWidget]);

    if (!mounted || !auth.user) return null;

    const unread = unreadMessages ?? 0;

    return (
        <div className="fixed bottom-4 right-4 z-[100] flex flex-col items-end gap-3">
            {isOpen && (
                <div
                    className={cn(
                        'flex w-[min(100vw-2rem,380px)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl',
                        'animate-in slide-in-from-bottom-4 fade-in duration-200',
                    )}
                    style={{ height: 'min(520px, calc(100vh - 6rem))' }}
                >
                    <div className="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-3 text-white">
                        <div className="flex items-center gap-2">
                            <MessageCircle className="h-5 w-5" />
                            <span className="font-semibold">Messages</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={closeWidget}
                                className="rounded-lg p-1.5 hover:bg-white/20"
                                aria-label="Minimize chat"
                            >
                                <Minus className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                onClick={closeWidget}
                                className="rounded-lg p-1.5 hover:bg-white/20"
                                aria-label="Close chat"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div className="flex min-h-0 flex-1 flex-col">
                        {view === 'list' ? <ChatListPanel /> : <ChatThreadPanel />}
                    </div>
                </div>
            )}

            {!isOpen && (
                <button
                    type="button"
                    onClick={openWidget}
                    className="relative flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-orange-600 text-white shadow-lg shadow-orange-500/30 transition-transform hover:scale-105 active:scale-95"
                    aria-label="Open messages"
                >
                    <MessageCircle className="h-6 w-6" />
                    {unread > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-6 min-w-6 items-center justify-center rounded-full border-2 border-white bg-red-500 px-1 text-xs font-bold">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </button>
            )}
        </div>
    );
}
