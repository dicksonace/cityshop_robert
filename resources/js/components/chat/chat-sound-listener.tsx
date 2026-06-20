import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { useChat } from '@/contexts/chat-context';
import * as chatApi from '@/lib/chat-api';
import { playChatReceiveSound } from '@/lib/chat-sounds';
import { SharedData } from '@/types';

/**
 * Plays receive sounds when new messages arrive while the thread panel is not active
 * (chat minimized, on conversation list, or browsing the shop).
 */
export default function ChatSoundListener() {
    const { auth } = usePage<SharedData>().props;
    const { isOpen, view } = useChat();
    const unreadMapRef = useRef<Record<number, number>>({});
    const initializedRef = useRef(false);

    const threadActive = isOpen && view === 'thread';

    useEffect(() => {
        if (!auth.user || threadActive) return;

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

                for (const c of conversations) {
                    const prev = unreadMapRef.current[c.id] ?? 0;
                    if (c.unread_count > prev) {
                        playChatReceiveSound();
                        break;
                    }
                }

                for (const c of conversations) {
                    unreadMapRef.current[c.id] = c.unread_count;
                }
            } catch {
                // ignore background poll errors
            }
        };

        void poll();
        const interval = setInterval(poll, 4000);
        return () => clearInterval(interval);
    }, [auth.user, threadActive]);

    useEffect(() => {
        if (threadActive) {
            initializedRef.current = false;
        }
    }, [threadActive]);

    useEffect(() => {
        if (!auth.user) {
            unreadMapRef.current = {};
            initializedRef.current = false;
        }
    }, [auth.user]);

    return null;
}
