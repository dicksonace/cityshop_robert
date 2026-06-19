import type { ChatConversation, ChatMessage } from '@/types/chat';

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function parseJsonResponse<T>(res: Response): Promise<T> {
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const message =
            (data as { message?: string }).message ??
            (data as { errors?: Record<string, string[]> }).errors?.body?.[0] ??
            `Request failed (${res.status})`;
        throw new Error(message);
    }
    return data as T;
}

export async function fetchConversations(): Promise<ChatConversation[]> {
    const res = await fetch(route('chat.index'), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    const data = await parseJsonResponse<{ conversations: ChatConversation[] }>(res);
    return data.conversations ?? [];
}

export async function fetchConversation(conversationId: number): Promise<{
    conversation: ChatConversation;
    messages: ChatMessage[];
}> {
    const res = await fetch(route('chat.show', conversationId), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export async function startConversation(sellerId: number, productId?: number): Promise<{
    conversation: ChatConversation;
    messages: ChatMessage[];
}> {
    const res = await fetch(route('chat.store'), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            seller_id: sellerId,
            ...(productId ? { product_id: productId } : {}),
        }),
    });
    return parseJsonResponse(res);
}

export async function sendChatMessage(conversationId: number, body: string): Promise<ChatMessage> {
    const res = await fetch(route('chat.messages.store', conversationId), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ body }),
    });
    const data = await parseJsonResponse<{ message: ChatMessage }>(res);
    return data.message;
}

export async function pollConversation(conversationId: number, after: number): Promise<{
    messages: ChatMessage[];
    other?: ChatConversation['other'];
}> {
    const res = await fetch(route('chat.poll', { conversation: conversationId, after }), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}
