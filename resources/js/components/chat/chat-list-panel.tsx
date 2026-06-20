import { MapPin, MessageCircle } from 'lucide-react';

import OnlineIndicator from '@/components/shop/online-indicator';
import { useChat } from '@/contexts/chat-context';
import type { ChatConversation } from '@/types/chat';

function formatTime(value?: string): string {
    if (!value) return '';
    const d = new Date(value);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString('en-GH', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString('en-GH', { month: 'short', day: 'numeric' });
}

function conversationName(c: ChatConversation): string {
    return c.other.seller_profile?.business_name ?? c.other.seller_profile?.store_name ?? c.other.name;
}

export default function ChatListPanel() {
    const { conversations, loading, openConversation } = useChat();

    if (loading) {
        return (
            <div className="flex flex-1 items-center justify-center text-sm text-gray-400">
                Loading messages...
            </div>
        );
    }

    if (conversations.length === 0) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center px-6 text-center text-gray-400">
                <MessageCircle className="h-12 w-12 text-gray-300" />
                <p className="mt-3 text-sm font-medium text-gray-600">No messages yet</p>
                <p className="mt-1 text-xs">Message a seller from their store or product page</p>
            </div>
        );
    }

    return (
        <div className="flex-1 overflow-y-auto">
            {conversations.map((c) => {
                const name = conversationName(c);
                const location = [c.other.city, c.other.region].filter(Boolean).join(', ');
                const preview =
                    c.latest_message?.type === 'text'
                        ? c.latest_message.body
                        : c.latest_message?.type === 'image'
                          ? 'Photo'
                          : c.latest_message?.type?.startsWith('call')
                            ? 'Voice call'
                            : '';

                return (
                    <button
                        key={c.id}
                        type="button"
                        onClick={() => openConversation(c.id)}
                        className="flex w-full items-center gap-3 border-b border-gray-50 px-3 py-3 text-left transition-colors hover:bg-gray-50"
                    >
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-orange-500 text-sm font-bold text-white">
                            {name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center justify-between gap-2">
                                <p className="truncate text-sm font-semibold text-gray-900">{name}</p>
                                <span className="shrink-0 text-[10px] text-gray-400">{formatTime(c.last_message_at)}</span>
                            </div>
                            <div className="mt-0.5 flex items-center gap-1.5">
                                <OnlineIndicator online={c.other.online} size="sm" showLabel={false} />
                                {location && (
                                    <span className="flex items-center gap-0.5 truncate text-[10px] text-gray-400">
                                        <MapPin className="h-2.5 w-2.5" />
                                        {location}
                                    </span>
                                )}
                            </div>
                            {preview && <p className="mt-0.5 truncate text-xs text-gray-500">{preview}</p>}
                        </div>
                        {c.unread_count > 0 && (
                            <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-orange-500 px-1 text-[10px] font-bold text-white">
                                {c.unread_count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
