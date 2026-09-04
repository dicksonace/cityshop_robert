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
        const errors = (data as { errors?: Record<string, string[]> }).errors;
        const firstFieldError = errors ? Object.values(errors).flat().find(Boolean) : undefined;
        const message =
            (data as { message?: string }).message ??
            firstFieldError ??
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
    clear_request?: ChatClearRequest | null;
}> {
    const res = await fetch(route('chat.show', conversationId), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export type ChatClearRequest = {
    id: number;
    status: string;
    direction: 'outgoing' | 'incoming';
    from_name?: string | null;
    created_at?: string | null;
};

export type ChatAttachProduct = {
    id: number;
    name: string;
    slug: string;
    price?: number;
    image_url?: string | null;
};

export async function startConversation(sellerId: number, productId?: number): Promise<{
    conversation: ChatConversation;
    messages: ChatMessage[];
    attach_product?: ChatAttachProduct | null;
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

export async function sendChatProduct(conversationId: number, productId: number): Promise<ChatMessage> {
    const res = await fetch(route('chat.messages.product', conversationId), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ product_id: productId }),
    });
    const data = await parseJsonResponse<{ message: ChatMessage }>(res);
    return data.message;
}

export type ChatTransferMeta = {
    available_balance: number;
    has_payment_pin: boolean;
    recipient: {
        id: number;
        name: string;
        mobile?: string | null;
        avatar?: string | null;
    };
};

export async function fetchChatTransferMeta(conversationId: number): Promise<ChatTransferMeta> {
    const res = await fetch(route('chat.messages.transfer.meta', conversationId), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export async function sendChatTransfer(
    conversationId: number,
    payload: { amount: number; note?: string; payment_pin: string },
): Promise<ChatMessage> {
    const res = await fetch(route('chat.messages.transfer', conversationId), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            amount: payload.amount,
            payment_pin: payload.payment_pin,
            ...(payload.note?.trim() ? { note: payload.note.trim() } : {}),
        }),
    });
    const data = await parseJsonResponse<{ message: ChatMessage }>(res);
    return data.message;
}

export async function fetchIceServers(): Promise<RTCIceServer[]> {
    const res = await fetch(route('chat.ice-servers'), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    const data = await parseJsonResponse<{ ice_servers?: RTCIceServer[] }>(res);
    return data.ice_servers ?? [];
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
    viewOnce = false,
): Promise<ChatMessage> {
    const form = new FormData();
    form.append('image', file);
    if (caption?.trim()) {
        form.append('caption', caption.trim());
    }
    if (viewOnce) {
        form.append('view_once', '1');
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

export async function uploadChatFile(
    conversationId: number,
    file: File,
    caption?: string,
): Promise<ChatMessage> {
    const form = new FormData();
    form.append('file', file);
    if (caption?.trim()) {
        form.append('caption', caption.trim());
    }

    const res = await fetch(route('chat.messages.file', conversationId), {
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

export async function uploadChatVoice(
    conversationId: number,
    file: Blob,
    durationSeconds?: number,
): Promise<ChatMessage> {
    const form = new FormData();
    const ext = file.type.includes('ogg') ? 'ogg' : file.type.includes('mp4') ? 'm4a' : 'webm';
    form.append('voice', file, `voice.${ext}`);
    if (durationSeconds && durationSeconds > 0) {
        form.append('duration_seconds', String(Math.round(durationSeconds)));
    }

    const res = await fetch(route('chat.messages.voice', conversationId), {
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

export async function reactToChatMessage(
    conversationId: number,
    messageId: number,
    emoji: string,
): Promise<ChatMessage> {
    const res = await fetch(route('chat.messages.react', { conversation: conversationId, message: messageId }), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ emoji }),
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

export async function openViewOnce(
    conversationId: number,
    messageId: number,
): Promise<{ message: ChatMessage; image_url?: string | null; video_url?: string | null }> {
    const res = await fetch(route('chat.messages.view-once', { conversation: conversationId, message: messageId }), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export async function forwardChatMessage(
    conversationId: number,
    messageId: number,
    memberIds: number[],
): Promise<{ sent: number; message: string }> {
    const res = await fetch(route('chat.messages.forward', { conversation: conversationId, message: messageId }), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ member_ids: memberIds }),
    });
    return parseJsonResponse(res);
}

export type ForwardTarget = {
    id: number;
    name: string;
    avatar: string | null;
};

export async function fetchForwardTargets(): Promise<ForwardTarget[]> {
    const res = await fetch(route('chat.forward-targets'), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    const data = await parseJsonResponse<{ data: ForwardTarget[] }>(res);
    return data.data ?? [];
}

export async function pollConversation(
    conversationId: number,
    after: number,
    updatedAfter?: string,
): Promise<{
    messages: ChatMessage[];
    updated?: ChatMessage[];
    read_message_ids?: number[];
    is_group?: boolean;
    other?: ChatConversation['other'];
}> {
    const params = new URLSearchParams({ after: String(after) });
    if (updatedAfter) {
        params.set('updated_after', updatedAfter);
    }
    const res = await fetch(`${route('chat.poll', conversationId)}?${params.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export async function deleteConversation(conversationId: number): Promise<void> {
    const res = await fetch(route('chat.destroy', conversationId), {
        method: 'DELETE',
        headers: jsonHeaders(),
        credentials: 'same-origin',
    });
    await parseJsonResponse(res);
}

export async function clearChatHistory(conversationId: number): Promise<{
    messages: ChatMessage[];
    message?: string;
    clear_request?: ChatClearRequest | null;
}> {
    const res = await fetch(route('chat.clear', conversationId), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export async function requestClearBoth(conversationId: number): Promise<{
    clear_request?: ChatClearRequest | null;
    message?: string;
}> {
    const res = await fetch(route('chat.clear-request', conversationId), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
    });
    return parseJsonResponse(res);
}

export async function respondClearBoth(
    conversationId: number,
    clearRequestId: number,
    accept: boolean,
): Promise<{
    messages?: ChatMessage[] | null;
    clear_request?: ChatClearRequest | null;
    message?: string;
}> {
    const res = await fetch(route('chat.clear-request.respond', [conversationId, clearRequestId]), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ accept }),
    });
    return parseJsonResponse(res);
}

export async function searchMessages(conversationId: number, q: string): Promise<ChatMessage[]> {
    const res = await fetch(route('chat.search', { conversation: conversationId, q }), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    const data = await parseJsonResponse<{ messages: ChatMessage[] }>(res);
    return data.messages ?? [];
}

export async function reportSeller(payload: {
    seller_id: number;
    reason: string;
    details?: string;
    product_id?: number;
}): Promise<void> {
    const res = await fetch(route('sellers.report'), {
        method: 'POST',
        headers: jsonHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });
    await parseJsonResponse(res);
}
