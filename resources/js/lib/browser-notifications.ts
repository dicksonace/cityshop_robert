const PERMISSION_ASKED_KEY = 'cityshop_chat_notify_asked';

export function browserNotificationsSupported(): boolean {
    return typeof window !== 'undefined' && 'Notification' in window;
}

export function browserNotificationPermission(): NotificationPermission | 'unsupported' {
    if (!browserNotificationsSupported()) return 'unsupported';
    return Notification.permission;
}

export async function ensureBrowserNotifications(): Promise<boolean> {
    if (!browserNotificationsSupported()) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;

    try {
        const result = await Notification.requestPermission();
        try {
            window.localStorage.setItem(PERMISSION_ASKED_KEY, '1');
        } catch {
            // ignore
        }
        return result === 'granted';
    } catch {
        return false;
    }
}

export function showBrowserNotification(options: {
    title: string;
    body?: string | null;
    tag?: string;
    icon?: string;
    onClick?: () => void;
}): void {
    if (!browserNotificationsSupported() || Notification.permission !== 'granted') {
        return;
    }

    try {
        const notification = new Notification(options.title, {
            body: options.body?.trim() || 'New message',
            tag: options.tag,
            icon: options.icon || '/images/logo.png',
            badge: '/images/branding/icon-192.png',
            silent: false,
        });
        notification.onclick = () => {
            window.focus();
            options.onClick?.();
            notification.close();
        };
        window.setTimeout(() => notification.close(), 12_000);
    } catch {
        // Some browsers throw if the tab is not allowed to show notifications.
    }
}
