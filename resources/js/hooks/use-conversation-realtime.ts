import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { getEcho, leaveConversation } from '@/lib/echo';
import type { ChatMessage } from '@/types/chat';
import { SharedData } from '@/types';

type IncomingPayload = {
    message?: ChatMessage;
};

/**
 * Subscribe to Reverb `message.sent` for a conversation. Returns whether a
 * live socket was attached so callers can slow down HTTP polling.
 */
export function useConversationRealtime(
    conversationId: number | null | undefined,
    onMessage: (message: ChatMessage) => void | Promise<void>,
): boolean {
    const { reverb } = usePage<SharedData>().props;
    const handlerRef = useRef(onMessage);
    handlerRef.current = onMessage;

    const key = (reverb?.key || import.meta.env.VITE_REVERB_APP_KEY || '').toString().trim();
    const host = (reverb?.host || '').toString();
    const port = String(reverb?.port ?? '');
    const scheme = (reverb?.scheme || '').toString();
    const live = Boolean(conversationId && key);

    useEffect(() => {
        if (!conversationId || !key) return;

        const echo = getEcho(reverb);
        if (!echo) return;

        const channel = echo.private(`conversation.${conversationId}`);
        channel.listen('.message.sent', (payload: IncomingPayload) => {
            if (payload?.message) {
                void handlerRef.current(payload.message);
            }
        });

        return () => {
            channel.stopListening('.message.sent');
            leaveConversation(conversationId);
        };
    }, [conversationId, key, host, port, scheme, reverb]);

    return live;
}
