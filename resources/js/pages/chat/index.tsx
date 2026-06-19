import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MapPin, MessageCircle } from 'lucide-react';

import OnlineIndicator from '@/components/shop/online-indicator';
import ShopLayout from '@/layouts/shop-layout';

interface ConversationItem {
    id: number;
    product?: { id: number; name: string; slug: string } | null;
    other: {
        id: number;
        name: string;
        online: boolean;
        city?: string;
        region?: string;
        seller_profile?: { business_name?: string; store_name?: string; slug?: string } | null;
    };
    latest_message?: { body: string; type: string; created_at?: string; sender_id: number } | null;
    unread_count: number;
    last_message_at?: string;
}

interface ChatIndexProps {
    conversations: ConversationItem[];
}

function formatTime(value?: string): string {
    if (!value) return '';
    const d = new Date(value);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString('en-GH', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString('en-GH', { month: 'short', day: 'numeric' });
}

export default function ChatIndex({ conversations }: ChatIndexProps) {
    return (
        <ShopLayout>
            <Head title="Messages" />
            <div className="mx-auto max-w-3xl px-4 py-8">
                <div className="mb-6 flex items-center gap-3">
                    <Link href={route('home')} className="rounded-lg p-2 hover:bg-gray-100">
                        <ArrowLeft className="h-5 w-5 text-gray-600" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Messages</h1>
                        <p className="text-sm text-gray-500">Chat with buyers and sellers on CityShop</p>
                    </div>
                </div>

                {conversations.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-gray-200 py-16 text-center">
                        <MessageCircle className="mx-auto h-12 w-12 text-gray-300" />
                        <p className="mt-4 font-medium text-gray-700">No conversations yet</p>
                        <p className="mt-1 text-sm text-gray-500">Message a seller from their store or product page</p>
                        <Link href={route('home')} className="mt-4 inline-block text-sm text-orange-500 hover:underline">
                            Browse products
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                        {conversations.map((c) => {
                            const name = c.other.seller_profile?.business_name ?? c.other.seller_profile?.store_name ?? c.other.name;
                            const location = [c.other.city, c.other.region].filter(Boolean).join(', ');
                            const preview = c.latest_message?.type === 'text'
                                ? c.latest_message.body
                                : c.latest_message?.type?.startsWith('call')
                                  ? 'Voice call'
                                  : '';

                            return (
                                <Link
                                    key={c.id}
                                    href={route('chat.show', c.id)}
                                    className="flex items-center gap-4 border-b border-gray-50 px-4 py-4 transition-colors last:border-0 hover:bg-gray-50"
                                >
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-orange-500 text-lg font-bold text-white">
                                        {name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate font-semibold text-gray-900">{name}</p>
                                            <span className="shrink-0 text-xs text-gray-400">{formatTime(c.last_message_at)}</span>
                                        </div>
                                        <div className="mt-0.5 flex items-center gap-2">
                                            <OnlineIndicator online={c.other.online} size="sm" />
                                            {location && (
                                                <span className="flex items-center gap-0.5 truncate text-xs text-gray-400">
                                                    <MapPin className="h-3 w-3 shrink-0" />
                                                    {location}
                                                </span>
                                            )}
                                        </div>
                                        {preview && (
                                            <p className="mt-1 truncate text-sm text-gray-500">{preview}</p>
                                        )}
                                        {c.product && (
                                            <p className="mt-0.5 truncate text-xs text-orange-500">Re: {c.product.name}</p>
                                        )}
                                    </div>
                                    {c.unread_count > 0 && (
                                        <span className="flex h-6 min-w-6 items-center justify-center rounded-full bg-orange-500 px-1.5 text-xs font-bold text-white">
                                            {c.unread_count}
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
