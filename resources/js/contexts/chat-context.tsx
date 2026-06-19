import { createContext, ReactNode, useCallback, useContext, useMemo, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
import type { ChatConversation, ChatMessage } from '@/types/chat';

type ChatView = 'list' | 'thread';

interface ChatContextValue {
    isOpen: boolean;
    view: ChatView;
    conversations: ChatConversation[];
    activeConversation: ChatConversation | null;
    messages: ChatMessage[];
    loading: boolean;
    openWidget: () => void;
    closeWidget: () => void;
    showList: () => void;
    openConversation: (conversationId: number) => Promise<void>;
    startChatWithSeller: (sellerId: number, productId?: number) => Promise<void>;
    refreshConversations: () => Promise<void>;
    setMessages: React.Dispatch<React.SetStateAction<ChatMessage[]>>;
    setActiveConversation: React.Dispatch<React.SetStateAction<ChatConversation | null>>;
}

const ChatContext = createContext<ChatContextValue | null>(null);

export function ChatProvider({ children }: { children: ReactNode }) {
    const [isOpen, setIsOpen] = useState(false);
    const [view, setView] = useState<ChatView>('list');
    const [conversations, setConversations] = useState<ChatConversation[]>([]);
    const [activeConversation, setActiveConversation] = useState<ChatConversation | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [loading, setLoading] = useState(false);

    const refreshConversations = useCallback(async () => {
        const list = await chatApi.fetchConversations();
        setConversations(list);
    }, []);

    const openWidget = useCallback(async () => {
        setIsOpen(true);
        setLoading(true);
        try {
            await refreshConversations();
            setView('list');
        } finally {
            setLoading(false);
        }
    }, [refreshConversations]);

    const closeWidget = useCallback(() => {
        setIsOpen(false);
    }, []);

    const showList = useCallback(async () => {
        setView('list');
        setActiveConversation(null);
        setMessages([]);
        await refreshConversations();
    }, [refreshConversations]);

    const openConversation = useCallback(async (conversationId: number) => {
        setIsOpen(true);
        setLoading(true);
        setView('thread');
        try {
            const data = await chatApi.fetchConversation(conversationId);
            setActiveConversation(data.conversation);
            setMessages(data.messages);
            await refreshConversations();
        } finally {
            setLoading(false);
        }
    }, [refreshConversations]);

    const startChatWithSeller = useCallback(async (sellerId: number, productId?: number) => {
        setIsOpen(true);
        setLoading(true);
        setView('thread');
        try {
            const data = await chatApi.startConversation(sellerId, productId);
            setActiveConversation(data.conversation);
            setMessages(data.messages);
            await refreshConversations();
        } finally {
            setLoading(false);
        }
    }, [refreshConversations]);

    const value = useMemo(
        () => ({
            isOpen,
            view,
            conversations,
            activeConversation,
            messages,
            loading,
            openWidget,
            closeWidget,
            showList,
            openConversation,
            startChatWithSeller,
            refreshConversations,
            setMessages,
            setActiveConversation,
        }),
        [
            isOpen,
            view,
            conversations,
            activeConversation,
            messages,
            loading,
            openWidget,
            closeWidget,
            showList,
            openConversation,
            startChatWithSeller,
            refreshConversations,
        ],
    );

    return <ChatContext.Provider value={value}>{children}</ChatContext.Provider>;
}

export function useChat(): ChatContextValue {
    const ctx = useContext(ChatContext);
    if (!ctx) {
        throw new Error('useChat must be used within ChatProvider');
    }
    return ctx;
}

export function useChatOptional(): ChatContextValue | null {
    return useContext(ChatContext);
}
