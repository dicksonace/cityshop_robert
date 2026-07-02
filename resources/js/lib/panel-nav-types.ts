import { LucideIcon } from 'lucide-react';

export interface PanelNavSubItem {
    key: string;
    label: string;
    href: string;
    badgeKey?: string;
    mobile?: boolean;
    /** Highlight when the pathname matches but no sibling query params are present. */
    defaultOnPath?: boolean;
}

export interface PanelNavGroup {
    key: string;
    label: string;
    icon: LucideIcon;
    items: PanelNavSubItem[];
    defaultOpen?: boolean;
}

export type PanelNavCounts = Record<string, number>;

export interface PanelNavContext {
    /** Section key used when a page does not map cleanly to a sub-item href. */
    section: string;
}
