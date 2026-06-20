import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
import { loadChatState, saveChatState } from '@/lib/chat-storage';
import type { ChatConversation, ChatMessage } from '@/types/chat';

type ChatView = 'list' | 'thread';

interface ChatContextValue {
    isOpen: boolean;
    isMinimized: boolean;
    view: ChatView;
    conversations: ChatConversation[];
    activeConversation: ChatConversation | null;
    messages: ChatMessage[];
    loading: boolean;
    openWidget: () => void;
    closeWidget: () => void;
    minimizeWidget: () => void;
    expandWidget: () => void;
    showList: () => void;
    openConversation: (conversationId: number) => Promise<void>;
    startChatWithSeller: (sellerId: number, productId?: number) => Promise<void>;
    refreshConversations: () => Promise<void>;
    setMessages: React.Dispatch<React.SetStateAction<ChatMessage[]>>;
    setActiveConversation: React.Dispatch<React.SetStateAction<ChatConversation | null>>;
}

const ChatContext = createContext<ChatContextValue | null>(null);

export function ChatProvider({ children }: { children: ReactNode }) {
    const saved = loadChatState();

    const [isOpen, setIsOpen] = useState(false);
    const [isMinimized, setIsMinimized] = useState(saved.isMinimized);
    const [view, setView] = useState<ChatView>(saved.view);
    const [conversations, setConversations] = useState<ChatConversation[]>([]);
    const [activeConversation, setActiveConversation] = useState<ChatConversation | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [loading, setLoading] = useState(false);
    const [restored, setRestored] = useState(false);

    const refreshConversations = useCallback(async () => {
        const list = await chatApi.fetchConversations();
        setConversations(list);
    }, []);

    const loadConversation = useCallback(async (conversationId: number) => {
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

    useEffect(() => {
        if (restored || !saved.activeConversationId) {
            setRestored(true);
            return;
        }

        loadConversation(saved.activeConversationId)
            .catch(() => {
                saveChatState({ activeConversationId: null, view: 'list', isMinimized: false });
            })
            .finally(() => setRestored(true));
    }, [loadConversation, restored, saved.activeConversationId, saved.isMinimized]);

    useEffect(() => {
        saveChatState({
            activeConversationId: activeConversation?.id ?? null,
            view,
            isMinimized,
        });
    }, [activeConversation?.id, view, isMinimized]);

    const openWidget = useCallback(async () => {
        setIsOpen(true);
        setIsMinimized(false);
        setLoading(true);
        try {
            await refreshConversations();
            if (activeConversation) {
                setView('thread');
                if (messages.length === 0) {
                    await loadConversation(activeConversation.id);
                }
            } else if (saved.activeConversationId) {
                await loadConversation(saved.activeConversationId);
            } else {
                setView('list');
            }
        } finally {
            setLoading(false);
        }
    }, [activeConversation, loadConversation, messages.length, refreshConversations, saved.activeConversationId]);

    const closeWidget = useCallback(() => {
        setIsOpen(false);
        setIsMinimized(false);
    }, []);

    const minimizeWidget = useCallback(() => {
        setIsMinimized(true);
        setIsOpen(false);
    }, []);

    const expandWidget = useCallback(() => {
        setIsMinimized(false);
        setIsOpen(true);
    }, []);

    const showList = useCallback(async () => {
        setView('list');
        setActiveConversation(null);
        setMessages([]);
        await refreshConversations();
    }, [refreshConversations]);

    const openConversation = useCallback(
        async (conversationId: number) => {
            setIsOpen(true);
            setIsMinimized(false);
            await loadConversation(conversationId);
        },
        [loadConversation],
    );

    const startChatWithSeller = useCallback(
        async (sellerId: number, productId?: number) => {
            setIsOpen(true);
            setIsMinimized(false);
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
        },
        [refreshConversations],
    );

    const value = useMemo(
        () => ({
            isOpen,
            isMinimized,
            view,
            conversations,
            activeConversation,
            messages,
            loading,
            openWidget,
            closeWidget,
            minimizeWidget,
            expandWidget,
            showList,
            openConversation,
            startChatWithSeller,
            refreshConversations,
            setMessages,
            setActiveConversation,
        }),
        [
            isOpen,
            isMinimized,
            view,
            conversations,
            activeConversation,
            messages,
            loading,
            openWidget,
            closeWidget,
            minimizeWidget,
            expandWidget,
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
