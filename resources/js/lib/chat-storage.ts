const STORAGE_KEY = 'cityshop_chat_state';

export interface PersistedChatState {
    activeConversationId: number | null;
    view: 'list' | 'thread';
    isMinimized: boolean;
}

const defaultState: PersistedChatState = {
    activeConversationId: null,
    view: 'list',
    isMinimized: false,
};

export function loadChatState(): PersistedChatState {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return defaultState;
        const parsed = JSON.parse(raw) as Partial<PersistedChatState>;
        return {
            activeConversationId:
                typeof parsed.activeConversationId === 'number' ? parsed.activeConversationId : null,
            view: parsed.view === 'thread' ? 'thread' : 'list',
            isMinimized: Boolean(parsed.isMinimized),
        };
    } catch {
        return defaultState;
    }
}

export function saveChatState(state: PersistedChatState): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // ignore quota / private mode errors
    }
}

export function clearActiveConversation(): void {
    saveChatState({ ...loadChatState(), activeConversationId: null, view: 'list' });
}
