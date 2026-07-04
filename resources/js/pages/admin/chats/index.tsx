import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { Paginated } from '@/types/marketplace';

interface ChatRow {
    id: number;
    last_message_at?: string;
    buyer?: { id: number; name: string; email: string; mobile?: string };
    seller?: { id: number; name: string; email: string };
    product?: { id: number; name: string };
    latest_message?: { body?: string; type?: string; sender?: { name: string } };
}

interface ChatsIndexProps {
    conversations: Paginated<ChatRow>;
    search?: string | null;
}

export default function AdminChatsIndex({ conversations, search }: ChatsIndexProps) {
    const [query, setQuery] = useState(search ?? '');

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get(route('admin.chats.index'), { search: query || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout title="Buyer–Seller Chats" active="chats">
            <Head title="Chats" />

            <p className="mb-4 text-sm text-gray-500">
                Monitor conversations between buyers and sellers. Message content is encrypted at rest in the database.
            </p>

            <form onSubmit={submitSearch} className="mb-6">
                <div className="relative max-w-md">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by buyer or seller name/email..."
                        className="pl-9"
                    />
                </div>
            </form>

            <div className="space-y-3">
                {conversations.data.length === 0 ? (
                    <div className="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">
                        No conversations found.
                    </div>
                ) : (
                    conversations.data.map((chat) => (
                        <Link
                            key={chat.id}
                            href={route('admin.chats.show', chat.id)}
                            className="block rounded-xl bg-white p-4 shadow-sm transition hover:ring-1 hover:ring-orange-200"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-medium text-gray-900">
                                        {chat.buyer?.name ?? 'Buyer'} ↔ {chat.seller?.name ?? 'Seller'}
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {chat.buyer?.email} · {chat.seller?.email}
                                    </p>
                                    {chat.product && (
                                        <p className="mt-1 text-xs text-orange-600">Product: {chat.product.name}</p>
                                    )}
                                </div>
                                <p className="text-xs text-gray-400">
                                    {chat.last_message_at
                                        ? new Date(chat.last_message_at).toLocaleString('en-GH')
                                        : '—'}
                                </p>
                            </div>
                            <p className="mt-2 line-clamp-1 text-sm text-gray-600">
                                {chat.latest_message?.body || (chat.latest_message?.type === 'image' ? 'Photo' : 'No messages')}
                            </p>
                        </Link>
                    ))
                )}
            </div>

            {conversations.last_page > 1 && (
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {conversations.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : null,
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
