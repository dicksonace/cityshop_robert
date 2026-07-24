export interface ChatCallLog {
    status: 'completed' | 'missed' | 'declined' | 'cancelled';
    caller_id: number;
    caller_name: string;
    ended_by_id: number;
    duration_seconds: number;
}

export interface ChatConversationOther {
    id: number;
    name: string;
    avatar?: string | null;
    online: boolean;
    last_seen_at?: string | null;
    city?: string | null;
    region?: string | null;
    seller_profile?: {
        business_name?: string | null;
        store_name?: string | null;
        slug?: string | null;
        business_address?: string | null;
    } | null;
}

export interface ChatConversation {
    id: number;
    product?: { id: number; name: string; slug: string } | null;
    other: ChatConversationOther;
    latest_message?: {
        body?: string | null;
        type?: string;
        created_at?: string;
        sender_id?: number;
        call_log?: ChatCallLog | null;
    } | null;
    unread_count: number;
    last_message_at?: string | null;
}

export interface ChatReplyTo {
    id: number;
    body: string;
    sender_name: string;
}

export interface ChatMessage {
    id: number;
    sender_id: number;
    type: string;
    body: string | null;
    metadata?: Record<string, unknown> | null;
    image_url?: string | null;
    call_log?: ChatCallLog | null;
    read_at?: string | null;
    reply_to?: ChatReplyTo | null;
    edited_at?: string | null;
    is_deleted?: boolean;
    can_edit?: boolean;
    can_delete?: boolean;
    created_at?: string;
    sender: { id: number; name: string };
}
