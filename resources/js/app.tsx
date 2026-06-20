import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { ChatProvider } from './contexts/chat-context';
import { ToastProvider } from './contexts/toast-context';
import FloatingChatWidget from './components/chat/floating-chat-widget';
import ChatSoundListener from './components/chat/chat-sound-listener';
import FlashToastListener from './components/shop/flash-toast-listener';
import { setCsrfToken } from './lib/csrf';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'CityShop';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')).then((module) => {
            const Page = module.default as ComponentType;
            return function PageWithChat(props: Record<string, unknown>) {
                return (
                    <>
                        <FlashToastListener />
                        <ChatSoundListener />
                        <Page {...props} />
                        <FloatingChatWidget />
                    </>
                );
            };
        }),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const initialToken = (props.initialPage.props as { csrfToken?: string }).csrfToken;
        if (initialToken) {
            setCsrfToken(initialToken);
        }

        root.render(
            <ChatProvider>
                <ToastProvider>
                    <App {...props} />
                </ToastProvider>
            </ChatProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('success', (event) => {
    const token = (event.detail.page.props as { csrfToken?: string }).csrfToken;
    if (token) {
        setCsrfToken(token);
    }
});

// This will set light / dark mode on load...
initializeTheme();
