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
    csrfToken?: string;
    quote: { message: string; author: string };
    auth: Auth;
    cartCount: number;
    wishlistProductIds: number[];
    wishlistCount: number;
    unreadMessages?: number;
    unreadNotifications?: number;
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
