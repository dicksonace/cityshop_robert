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
    is_seller?: boolean;
    store_slug?: string | null;
    seller_profile?: {
        business_name?: string | null;
        store_name?: string | null;
        slug?: string | null;
        business_address?: string | null;
    } | null;
}

export interface ChatConversation {
    id: number;
    buyer_id?: number;
    seller_id?: number;
    can_complain?: boolean;
    product?: {
        id: number;
        name: string;
        slug: string;
        price?: number;
        image_url?: string | null;
    } | null;
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
    type?: string;
    product?: {
        id?: number | null;
        name?: string | null;
        slug?: string | null;
        price?: number | null;
        image_url?: string | null;
    } | null;
}

export interface ChatTransfer {
    amount: number | string;
    currency?: string;
    note?: string | null;
    reference?: string | null;
    from_user_id?: number;
    to_user_id?: number;
    from_name?: string | null;
    to_name?: string | null;
}

export interface ChatMessage {
    id: number;
    sender_id: number;
    type: string;
    body: string | null;
    metadata?: Record<string, unknown> | null;
    image_url?: string | null;
    video_url?: string | null;
    voice_url?: string | null;
    product?: {
        id: number;
        name: string;
        slug: string;
        price?: number;
        image_url?: string | null;
    } | null;
    transfer?: ChatTransfer | null;
    file_url?: string | null;
    file_name?: string | null;
    file_size?: number | null;
    file_mime?: string | null;
    duration_seconds?: number | null;
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
