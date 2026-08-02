import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { csrfHeaders } from '@/lib/csrf';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

export type ReverbSharedConfig = {
    key?: string | null;
    host?: string | null;
    port?: number | string | null;
    scheme?: string | null;
};

/**
 * One shared Echo client for the shop. Returns null when Reverb is not
 * configured so callers can fall back to HTTP polling.
 */
export function getEcho(reverb?: ReverbSharedConfig | null): Echo<'reverb'> | null {
    if (typeof window === 'undefined') return null;

    const key = (reverb?.key || import.meta.env.VITE_REVERB_APP_KEY || '').toString().trim();
    if (!key) return null;

    if (window.Echo) return window.Echo;

    window.Pusher = Pusher;

    const scheme = (reverb?.scheme || import.meta.env.VITE_REVERB_SCHEME || 'https').toString();
    const host = (reverb?.host || import.meta.env.VITE_REVERB_HOST || window.location.hostname).toString();
    const port = Number(reverb?.port || import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80));

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
        },
    });

    return window.Echo;
}

export function leaveConversation(conversationId: number): void {
    window.Echo?.leave(`conversation.${conversationId}`);
}
