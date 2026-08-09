import { router, usePage } from '@inertiajs/react';
import { LoaderCircle, MessageCircle } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { useChatOptional } from '@/contexts/chat-context';
import { useToastOptional } from '@/contexts/toast-context';
import { SharedData } from '@/types';

interface MessageSellerButtonProps {
    sellerId: number;
    productId?: number;
    className?: string;
    variant?: 'default' | 'outline' | 'banner';
    label?: string;
    onOpen?: () => void;
}

export default function MessageSellerButton({
    sellerId,
    productId,
    className,
    variant = 'default',
    label = 'Message Seller',
    onOpen,
}: MessageSellerButtonProps) {
    const { auth } = usePage<SharedData>().props;
    const chat = useChatOptional();
    const toast = useToastOptional();
    const [busy, setBusy] = useState(false);

    const startChat = async () => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }

        if (busy) return;
        onOpen?.();

        if (!chat) {
            router.post(route('chat.store'), {
                seller_id: sellerId,
                ...(productId ? { product_id: productId } : {}),
            });
            return;
        }

        setBusy(true);
        try {
            await chat.startChatWithSeller(sellerId, productId);
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not open chat. Try again.');
        } finally {
            setBusy(false);
        }
    };

    const icon = busy ? (
        <LoaderCircle className="h-4 w-4 animate-spin" />
    ) : (
        <MessageCircle className="h-4 w-4" />
    );

    if (variant === 'banner') {
        return (
            <button
                type="button"
                onClick={() => void startChat()}
                disabled={busy}
                className={`inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-orange-500 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-orange-600 disabled:opacity-60 sm:w-auto sm:gap-2 sm:px-4 sm:py-2.5 sm:text-sm ${className ?? ''}`}
            >
                {icon}
                {busy ? 'Opening…' : label}
            </button>
        );
    }

    if (variant === 'outline') {
        return (
            <Button type="button" variant="outline" onClick={() => void startChat()} disabled={busy} className={className}>
                <span className="mr-2 inline-flex">{icon}</span>
                {busy ? 'Opening…' : label}
            </Button>
        );
    }

    return (
        <Button
            type="button"
            onClick={() => void startChat()}
            disabled={busy}
            className={`bg-orange-500 hover:bg-orange-600 disabled:opacity-60 ${className ?? ''}`}
        >
            <span className="mr-2 inline-flex">{icon}</span>
            {busy ? 'Opening…' : label}
        </Button>
    );
}
