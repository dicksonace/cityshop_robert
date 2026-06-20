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
    read_at?: string | null;
    reply_to?: ChatReplyTo | null;
    edited_at?: string | null;
    is_deleted?: boolean;
    can_edit?: boolean;
    can_delete?: boolean;
    created_at?: string;
    sender: { id: number; name: string };
}
