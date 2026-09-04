import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
import type { ChatAttachProduct, ChatClearRequest } from '@/lib/chat-api';
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
    clearRequest: ChatClearRequest | null;
    attachProduct: ChatAttachProduct | null;
    loading: boolean;
    openWidget: () => void;
    closeWidget: () => void;
    minimizeWidget: () => void;
    expandWidget: () => void;
    showList: () => void;
    openConversation: (conversationId: number) => Promise<void>;
    startChatWithSeller: (sellerId: number, productId?: number) => Promise<void>;
    clearAttachProduct: () => void;
    refreshConversations: () => Promise<void>;
    setMessages: React.Dispatch<React.SetStateAction<ChatMessage[]>>;
    setActiveConversation: React.Dispatch<React.SetStateAction<ChatConversation | null>>;
    setClearRequest: React.Dispatch<React.SetStateAction<ChatClearRequest | null>>;
}

const ChatContext = createContext<ChatContextValue | null>(null);

export function ChatProvider({ children }: { children: ReactNode }) {
    const savedRef = useRef(loadChatState());
    const saved = savedRef.current;
    const activeIdRef = useRef<number | null>(null);
    const messagesLengthRef = useRef(0);

    const [isOpen, setIsOpen] = useState(saved.isOpen && !saved.isMinimized);
    const [isMinimized, setIsMinimized] = useState(saved.isMinimized);
    const [view, setView] = useState<ChatView>(saved.view);
    const [conversations, setConversations] = useState<ChatConversation[]>([]);
    const [activeConversation, setActiveConversation] = useState<ChatConversation | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [clearRequest, setClearRequest] = useState<ChatClearRequest | null>(null);
    const [attachProduct, setAttachProduct] = useState<ChatAttachProduct | null>(null);
    const [loading, setLoading] = useState(
        () => Boolean((saved.isOpen || saved.isMinimized) && saved.activeConversationId),
    );
    const [restored, setRestored] = useState(false);

    useEffect(() => {
        activeIdRef.current = activeConversation?.id ?? null;
    }, [activeConversation?.id]);

    useEffect(() => {
        messagesLengthRef.current = messages.length;
    }, [messages.length]);

    const refreshConversations = useCallback(async () => {
        const list = await chatApi.fetchConversations();
        setConversations(list);
    }, []);

    const loadConversation = useCallback(async (conversationId: number) => {
        setView('thread');
        setAttachProduct(null);
        // Keep the current thread on screen — blanking it is the black/loading flash.
        if (activeIdRef.current == null) {
            setLoading(true);
        }
        try {
            const data = await chatApi.fetchConversation(conversationId);
            setActiveConversation(data.conversation);
            setMessages(data.messages);
            setClearRequest(data.clear_request ?? null);
            await refreshConversations();
        } finally {
            setLoading(false);
        }
    }, [refreshConversations]);

    useEffect(() => {
        if (restored) return;

        const restore = async () => {
            try {
                if (saved.isOpen || saved.isMinimized) {
                    await refreshConversations();
                }
                if (saved.activeConversationId) {
                    await loadConversation(saved.activeConversationId);
                }
            } catch {
                saveChatState({
                    activeConversationId: saved.activeConversationId,
                    view: saved.view,
                    isMinimized: saved.isMinimized,
                    isOpen: saved.isOpen,
                });
            } finally {
                setRestored(true);
                setLoading(false);
            }
        };

        void restore();
    }, [loadConversation, refreshConversations, restored, saved]);

    useEffect(() => {
        saveChatState({
            activeConversationId: activeConversation?.id ?? null,
            view,
            isMinimized,
            isOpen,
        });
    }, [activeConversation?.id, view, isMinimized, isOpen]);

    const openWidget = useCallback(async () => {
        setIsOpen(true);
        setIsMinimized(false);
        try {
            await refreshConversations();
            const currentId = activeIdRef.current;
            if (currentId) {
                setView('thread');
                if (messagesLengthRef.current === 0) {
                    await loadConversation(currentId);
                }
            } else if (saved.activeConversationId) {
                await loadConversation(saved.activeConversationId);
            } else {
                setView('list');
            }
        } catch {
            if (!activeIdRef.current) {
                setView('list');
            }
        }
    }, [loadConversation, refreshConversations, saved.activeConversationId]);

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
        setClearRequest(null);
        setAttachProduct(null);
        await refreshConversations();
    }, [refreshConversations]);

    const clearAttachProduct = useCallback(() => {
        setAttachProduct(null);
    }, []);

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
                setClearRequest(null);
                setAttachProduct(data.attach_product ?? null);
                await refreshConversations();
            } catch (err) {
                setView('list');
                setActiveConversation(null);
                setMessages([]);
                setClearRequest(null);
                setAttachProduct(null);
                try {
                    await refreshConversations();
                } catch {
                    // ignore secondary failure
                }
                throw err;
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
            clearRequest,
            attachProduct,
            loading,
            openWidget,
            closeWidget,
            minimizeWidget,
            expandWidget,
            showList,
            openConversation,
            startChatWithSeller,
            clearAttachProduct,
            refreshConversations,
            setMessages,
            setActiveConversation,
            setClearRequest,
        }),
        [
            isOpen,
            isMinimized,
            view,
            conversations,
            activeConversation,
            messages,
            clearRequest,
            attachProduct,
            loading,
            openWidget,
            closeWidget,
            minimizeWidget,
            expandWidget,
            showList,
            openConversation,
            startChatWithSeller,
            clearAttachProduct,
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
