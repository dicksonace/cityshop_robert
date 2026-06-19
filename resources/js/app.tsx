import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { ChatProvider } from './contexts/chat-context';
import { ToastProvider } from './contexts/toast-context';
import FloatingChatWidget from './components/chat/floating-chat-widget';
import FlashToastListener from './components/shop/flash-toast-listener';

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
                        <Page {...props} />
                        <FloatingChatWidget />
                    </>
                );
            };
        }),
    setup({ el, App, props }) {
        const root = createRoot(el);

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

// This will set light / dark mode on load...
initializeTheme();
