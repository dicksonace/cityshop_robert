import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    appUrl?: string;
    seo?: { defaultDescription?: string; defaultImage?: string };
    csrfToken?: string;
    quote: { message: string; author: string };
    auth: Auth;
    canShop?: boolean;
    livestreamEnabled?: boolean;
    cartCount: number;
    wishlistProductIds: number[];
    wishlistCount: number;
    unreadMessages?: number;
    unreadNotifications?: number;
    panelNavCounts?: Record<string, number>;
    sellerActivation?: {
        fee_amount: number;
        prompted_at?: string | null;
        paid_until?: string | null;
        paid_at?: string | null;
        is_active: boolean;
        needs_payment: boolean;
    } | null;
    reverb?: {
        key?: string | null;
        host?: string | null;
        port?: number | string | null;
        scheme?: string | null;
    };
    flash: { success?: string; error?: string; info?: string; sellerInviteUrl?: string };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    mobile?: string;
    role?: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    seller_profile?: import('./marketplace').SellerProfile;
    [key: string]: unknown;
}
