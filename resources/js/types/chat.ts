export interface ChatMessage {
    id: number;
    sender_id: number;
    type: string;
    body: string;
    metadata?: Record<string, unknown> | null;
    created_at?: string;
    sender: { id: number; name: string };
}

export interface ChatParticipant {
    id: number;
    name: string;
    online: boolean;
    last_seen_at?: string;
    city?: string;
    region?: string;
    seller_profile?: {
        business_name?: string;
        store_name?: string;
        slug?: string;
        business_address?: string;
    } | null;
}

export interface ChatConversation {
    id: number;
    product?: { id: number; name: string; slug: string } | null;
    other: ChatParticipant;
    latest_message?: {
        body: string;
        type: string;
        created_at?: string;
        sender_id: number;
    } | null;
    unread_count: number;
    last_message_at?: string;
}
