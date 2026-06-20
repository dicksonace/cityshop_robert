import { csrfHeaders } from '@/lib/csrf';
import type { ChatConversation, ChatMessage } from '@/types/chat';

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...csrfHeaders(),
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

export async function sendCallSignal(
    conversationId: number,
    type: string,
    body = '',
    metadata?: Record<string, unknown>,
): Promise<{ call_log?: ChatMessage }> {
    const res = await fetch(route('chat.signal', conversationId), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ type, body, metadata }),
    });
    return parseJsonResponse(res);
}

export async function uploadChatImage(
    conversationId: number,
    file: File,
    caption?: string,
): Promise<ChatMessage> {
    const form = new FormData();
    form.append('image', file);
    if (caption?.trim()) {
        form.append('caption', caption.trim());
    }

    const res = await fetch(route('chat.messages.image', conversationId), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
        body: form,
    });
    const data = await parseJsonResponse<{ message: ChatMessage }>(res);
    return data.message;
}

export async function sendChatMessage(
    conversationId: number,
    body: string,
    replyToId?: number,
): Promise<ChatMessage> {
    const res = await fetch(route('chat.messages.store', conversationId), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            body,
            ...(replyToId ? { reply_to_id: replyToId } : {}),
        }),
    });
    const data = await parseJsonResponse<{ message: ChatMessage }>(res);
    return data.message;
}

export async function updateChatMessage(
    conversationId: number,
    messageId: number,
    body: string,
): Promise<ChatMessage> {
    const res = await fetch(route('chat.messages.update', { conversation: conversationId, message: messageId }), {
        method: 'PATCH',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ body }),
    });
    const data = await parseJsonResponse<{ message: ChatMessage }>(res);
    return data.message;
}

export async function deleteChatMessage(conversationId: number, messageId: number): Promise<ChatMessage> {
    const res = await fetch(route('chat.messages.destroy', { conversation: conversationId, message: messageId }), {
        method: 'DELETE',
        headers: jsonHeaders(),
        credentials: 'same-origin',
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
