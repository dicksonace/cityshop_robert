export interface StatusAuthor {
    id: number;
    name: string;
    avatar?: string | null;
    role?: string | null;
}

export interface StatusItem {
    id: number;
    type: 'image' | 'text' | string;
    body?: string | null;
    media_url?: string | null;
    background_color?: string | null;
    created_at?: string | null;
    expires_at?: string | null;
    viewed?: boolean;
    view_count?: number | null;
}

export interface StatusBundle {
    user: StatusAuthor;
    items: StatusItem[];
    unseen_count: number;
    latest_at?: string | null;
}

export interface StatusFeed {
    mine: StatusBundle;
    users: StatusBundle[];
}
