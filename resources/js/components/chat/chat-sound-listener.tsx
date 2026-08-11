import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { useChat } from '@/contexts/chat-context';
import {
    ensureBrowserNotifications,
    showBrowserNotification,
} from '@/lib/browser-notifications';
import * as chatApi from '@/lib/chat-api';
import { playChatReceiveSound } from '@/lib/chat-sounds';
import { SharedData } from '@/types';
import type { ChatConversation } from '@/types/chat';

function conversationTitle(c: ChatConversation): string {
    if (c.is_group) return c.name || 'Group';
    return (
        c.other.seller_profile?.business_name ||
        c.other.seller_profile?.store_name ||
        c.other.name ||
        'New message'
    );
}

function conversationPreview(c: ChatConversation): string {
    const latest = c.latest_message;
    if (!latest) return 'Sent you a message';
    const type = latest.type || 'text';
    if (type === 'text') return latest.body?.trim() || 'Sent you a message';
    if (type === 'image') return 'Sent a photo';
    if (type === 'video') return 'Sent a video';
    if (type === 'voice') return 'Sent a voice message';
    if (type === 'product') return latest.body?.trim() || 'Shared a product';
    if (type === 'transfer') return latest.body?.trim() || 'Sent money';
    if (type === 'file') return latest.body?.trim() || 'Sent a file';
    return 'Sent you a message';
}

/**
 * Sound + desktop/phone browser popups when a chat message arrives
 * while the thread is not on screen (minimized, other chat, other tab, or shop).
 */
export default function ChatSoundListener() {
    const { auth } = usePage<SharedData>().props;
    const { isOpen, isMinimized, view, activeConversation, openConversation } = useChat();
    const unreadMapRef = useRef<Record<number, number>>({});
    const initializedRef = useRef(false);
    const lastSoundAtRef = useRef(0);

    useEffect(() => {
        if (!auth.user) return;
        void ensureBrowserNotifications();
    }, [auth.user]);

    useEffect(() => {
        if (!auth.user) return;

        const poll = async () => {
            try {
                const conversations = await chatApi.fetchConversations();

                if (!initializedRef.current) {
                    for (const c of conversations) {
                        unreadMapRef.current[c.id] = c.unread_count;
                    }
                    initializedRef.current = true;
                    return;
                }

                const incoming: ChatConversation[] = [];
                for (const c of conversations) {
                    const prev = unreadMapRef.current[c.id] ?? 0;
                    if (c.unread_count > prev) {
                        incoming.push(c);
                    }
                }

                for (const c of conversations) {
                    unreadMapRef.current[c.id] = c.unread_count;
                }

                if (incoming.length === 0) return;

                const tabHidden = typeof document !== 'undefined' && document.hidden;
                const viewingThread = isOpen && !isMinimized && view === 'thread';
                const activeId = activeConversation?.id;

                const toAlert = incoming.filter((c) => {
                    if (tabHidden) return true;
                    if (viewingThread && activeId === c.id) return false;
                    return true;
                });

                if (toAlert.length === 0) return;

                if (Date.now() - lastSoundAtRef.current > 1200) {
                    lastSoundAtRef.current = Date.now();
                    playChatReceiveSound();
                }

                await ensureBrowserNotifications();
                for (const c of toAlert) {
                    showBrowserNotification({
                        title: conversationTitle(c),
                        body: conversationPreview(c),
                        tag: `cityshop-chat-${c.id}`,
                        onClick: () => {
                            void openConversation(c.id);
                        },
                    });
                }
            } catch {
                // ignore background poll errors
            }
        };

        void poll();
        const interval = window.setInterval(poll, 4000);
        const onVisible = () => {
            if (!document.hidden) void poll();
        };
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [auth.user, isOpen, isMinimized, view, activeConversation?.id, openConversation]);

    useEffect(() => {
        if (!auth.user) {
            unreadMapRef.current = {};
            initializedRef.current = false;
        }
    }, [auth.user]);

    return null;
}
