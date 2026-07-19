import { usePage } from '@inertiajs/react';
import { ChevronDown, MessageCircle, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import ChatListPanel from '@/components/chat/chat-list-panel';
import ChatThreadPanel from '@/components/chat/chat-thread-panel';
import { useChat } from '@/contexts/chat-context';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';

export default function FloatingChatWidget() {
    const { auth, unreadMessages, flash } = usePage<
        SharedData & { unreadMessages?: number; flash?: { openChat?: boolean } }
    >().props;
    const {
        isOpen,
        view,
        activeConversation,
        openWidget,
        closeWidget,
        minimizeWidget,
    } = useChat();
    const [mounted, setMounted] = useState(false);

    useEffect(() => setMounted(true), []);

    useEffect(() => {
        if (flash?.openChat) {
            openWidget();
        }
    }, [flash?.openChat, openWidget]);

    if (!mounted || !auth.user) return null;

    const unread = unreadMessages ?? 0;
    const isBuyer = auth.user.role === 'buyer';
    const otherName =
        activeConversation?.other.seller_profile?.business_name ??
        activeConversation?.other.seller_profile?.store_name ??
        activeConversation?.other.name;
    const hasActiveChat = Boolean(activeConversation);

    return (
        <div
            className={cn(
                'fixed right-0 z-[100] flex flex-col items-end gap-3 p-3 sm:right-4 sm:bottom-4 sm:p-0',
                isBuyer
                    ? 'bottom-[calc(4.25rem+env(safe-area-inset-bottom))] pb-3 sm:bottom-4'
                    : 'bottom-0 pb-[max(0.75rem,env(safe-area-inset-bottom))]',
            )}
        >
            {isOpen && (
                <div
                    className={cn(
                        'flex flex-col overflow-hidden border border-gray-200 bg-white shadow-2xl',
                        'animate-in slide-in-from-bottom-4 fade-in duration-200',
                        'h-[min(100dvh-8rem,520px)] w-full rounded-t-2xl sm:h-[min(520px,calc(100vh-6rem))] sm:w-[min(100vw-2rem,380px)] sm:rounded-2xl',
                    )}
                >
                    <div className="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-3 text-white">
                        <div className="flex min-w-0 items-center gap-2">
                            <MessageCircle className="h-5 w-5 shrink-0" />
                            <span className="truncate font-semibold">
                                {view === 'thread' && otherName ? otherName : 'Messages'}
                            </span>
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                onClick={minimizeWidget}
                                className="rounded-lg p-1.5 hover:bg-white/20"
                                aria-label="Minimize chat"
                            >
                                <ChevronDown className="h-4 w-4" />
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
                    className={cn(
                        'relative flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-orange-600 text-white shadow-lg shadow-orange-500/30 transition-transform hover:scale-105 active:scale-95 sm:h-14 sm:w-14',
                        isBuyer && 'hidden sm:flex',
                    )}
                    aria-label={hasActiveChat ? `Continue chat with ${otherName}` : 'Open messages'}
                    title={hasActiveChat ? `Continue chat with ${otherName}` : 'Open messages'}
                >
                    <MessageCircle className="h-5 w-5 sm:h-6 sm:w-6" />
                    {(unread > 0) && (
                        <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-red-500 px-1 text-[10px] font-bold sm:h-6 sm:min-w-6 sm:text-xs">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                    {hasActiveChat && unread === 0 && (
                        <span className="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-400" />
                    )}
                </button>
            )}
        </div>
    );
}
