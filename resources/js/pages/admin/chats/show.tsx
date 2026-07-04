import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import { Paginated } from '@/types/marketplace';

interface ChatShowProps {
    conversation: {
        id: number;
        buyer?: { id: number; name: string; email: string; mobile?: string };
        seller?: { id: number; name: string; email: string; mobile?: string };
        product?: { id: number; name: string; slug: string };
    };
    messages: Paginated<{
        id: number;
        body?: string;
        type: string;
        created_at: string;
        sender?: { id: number; name: string; role?: string };
    }>;
}

export default function AdminChatShow({ conversation, messages }: ChatShowProps) {
    return (
        <AdminLayout title="Chat thread" active="chats">
            <Head title="Chat oversight" />

            <Link href={route('admin.chats.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-orange-500">
                <ArrowLeft className="h-4 w-4" />
                Back to chats
            </Link>

            <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Admin oversight view. Messages are encrypted in the database and decrypted only inside the app.
            </div>

            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                <div className="rounded-xl bg-white p-4 shadow-sm">
                    <p className="text-xs font-medium uppercase text-gray-400">Buyer</p>
                    <p className="font-semibold text-gray-900">{conversation.buyer?.name}</p>
                    <p className="text-sm text-gray-500">{conversation.buyer?.email}</p>
                    <p className="text-sm text-gray-500">{conversation.buyer?.mobile}</p>
                    {conversation.buyer && (
                        <Link href={route('admin.buyers.show', conversation.buyer.id)} className="mt-2 inline-block text-sm text-blue-500 hover:underline">
                            View buyer profile
                        </Link>
                    )}
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm">
                    <p className="text-xs font-medium uppercase text-gray-400">Seller</p>
                    <p className="font-semibold text-gray-900">{conversation.seller?.name}</p>
                    <p className="text-sm text-gray-500">{conversation.seller?.email}</p>
                    <p className="text-sm text-gray-500">{conversation.seller?.mobile}</p>
                </div>
            </div>

            {conversation.product && (
                <p className="mb-4 text-sm text-gray-600">
                    Related product:{' '}
                    <Link href={route('products.show', conversation.product.slug)} className="text-orange-600 hover:underline">
                        {conversation.product.name}
                    </Link>
                </p>
            )}

            <div className="space-y-3 rounded-xl bg-white p-4 shadow-sm">
                {messages.data.length === 0 ? (
                    <p className="py-8 text-center text-sm text-gray-500">No messages in this conversation.</p>
                ) : (
                    messages.data.map((message) => {
                        const isBuyer = message.sender?.id === conversation.buyer?.id;
                        return (
                            <div
                                key={message.id}
                                className={`max-w-[85%] rounded-2xl px-4 py-2 text-sm ${
                                    isBuyer ? 'bg-orange-50 text-gray-900' : 'ml-auto bg-gray-100 text-gray-900'
                                }`}
                            >
                                <p className="text-[11px] font-medium text-gray-500">
                                    {message.sender?.name ?? 'User'} · {new Date(message.created_at).toLocaleString('en-GH')}
                                </p>
                                <p className="mt-1 whitespace-pre-wrap">
                                    {message.type === 'image' ? (message.body ? `[Image] ${message.body}` : '[Image]') : (message.body || '—')}
                                </p>
                            </div>
                        );
                    })
                )}
            </div>

            {messages.last_page > 1 && (
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {messages.links.map((link, i) =>
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
