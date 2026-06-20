import { router, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useChatOptional } from '@/contexts/chat-context';
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

    const startChat = async () => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }

        onOpen?.();

        if (chat) {
            await chat.startChatWithSeller(sellerId, productId);
            return;
        }

        router.post(route('chat.store'), {
            seller_id: sellerId,
            ...(productId ? { product_id: productId } : {}),
        });
    };

    if (variant === 'banner') {
        return (
            <button
                type="button"
                onClick={startChat}
                className={`inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-orange-500 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-orange-600 sm:w-auto sm:gap-2 sm:px-4 sm:py-2.5 sm:text-sm ${className ?? ''}`}
            >
                <MessageCircle className="h-4 w-4" />
                {label}
            </button>
        );
    }

    if (variant === 'outline') {
        return (
            <Button type="button" variant="outline" onClick={startChat} className={className}>
                <MessageCircle className="mr-2 h-4 w-4" />
                {label}
            </Button>
        );
    }

    return (
        <Button type="button" onClick={startChat} className={`bg-orange-500 hover:bg-orange-600 ${className ?? ''}`}>
            <MessageCircle className="mr-2 h-4 w-4" />
            {label}
        </Button>
    );
}
