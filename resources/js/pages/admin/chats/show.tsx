import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ImageIcon, Mic, Package, Phone, Video } from 'lucide-react';

import ChatVideoBubble from '@/components/chat/chat-video-bubble';
import AdminLayout from '@/layouts/admin-layout';
import { Paginated, productImageUrl } from '@/types/marketplace';

type AdminChatMessage = {
    id: number;
    body?: string | null;
    type: string;
    created_at?: string;
    image_url?: string | null;
    video_url?: string | null;
    voice_url?: string | null;
    duration_seconds?: number | null;
    product?: {
        id?: number;
        name?: string;
        slug?: string;
        price?: number;
        image_url?: string | null;
    } | null;
    transfer?: {
        amount?: number;
        currency?: string;
        note?: string | null;
    } | null;
    call_log?: {
        status?: string;
        duration_seconds?: number;
    } | null;
    is_deleted?: boolean;
    sender?: { id: number; name: string; role?: string } | null;
};

interface ChatShowProps {
    conversation: {
        id: number;
        buyer?: { id: number; name: string; email: string; mobile?: string };
        seller?: { id: number; name: string; email: string; mobile?: string };
        product?: { id: number; name: string; slug: string };
    };
    messages: Paginated<AdminChatMessage>;
    blocked?: boolean;
}

function formatMoney(amount?: number, currency = 'GHS'): string {
    if (typeof amount !== 'number') return '—';
    const prefix = currency === 'GHS' ? 'GH₵' : `${currency} `;
    return `${prefix}${amount.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function MessageBody({ message }: { message: AdminChatMessage }) {
    if (message.is_deleted) {
        return <p className="italic text-gray-500">Message deleted</p>;
    }

    if (message.type === 'image' && message.image_url) {
        return (
            <div className="space-y-2">
                <a href={message.image_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-xl">
                    <img
                        src={message.image_url}
                        alt={message.body?.trim() || 'Chat photo'}
                        className="max-h-72 w-full object-cover"
                    />
                </a>
                {message.body?.trim() ? <p className="whitespace-pre-wrap">{message.body}</p> : null}
                <a
                    href={message.image_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-xs font-medium text-orange-600 hover:underline"
                >
                    <ImageIcon className="h-3.5 w-3.5" />
                    Open photo
                </a>
            </div>
        );
    }

    if (message.type === 'video' && message.video_url) {
        return (
            <div className="min-w-[14rem] space-y-2">
                <ChatVideoBubble src={message.video_url} caption={message.body} />
                <a
                    href={message.video_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-xs font-medium text-orange-600 hover:underline"
                >
                    <Video className="h-3.5 w-3.5" />
                    Open video
                </a>
            </div>
        );
    }

    if (message.type === 'voice' && message.voice_url) {
        return (
            <div className="min-w-[14rem] space-y-2">
                <div className="flex items-center gap-2 text-xs font-medium text-gray-500">
                    <Mic className="h-3.5 w-3.5" />
                    Voice message
                    {message.duration_seconds ? <span>· {message.duration_seconds}s</span> : null}
                </div>
                <audio src={message.voice_url} controls preload="metadata" className="w-full" />
            </div>
        );
    }

    if (message.type === 'product' && message.product) {
        const product = message.product;
        return (
            <div className="min-w-[14rem] rounded-xl border border-orange-100 bg-white p-2.5">
                <div className="flex gap-2.5">
                    {product.image_url ? (
                        <img
                            src={productImageUrl(product.image_url)}
                            alt=""
                            className="h-14 w-14 shrink-0 rounded-lg object-cover"
                        />
                    ) : (
                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                            <Package className="h-5 w-5" />
                        </div>
                    )}
                    <div className="min-w-0 flex-1">
                        <p className="line-clamp-2 text-xs font-semibold text-gray-900">{product.name || 'Product'}</p>
                        {typeof product.price === 'number' && (
                            <p className="mt-1 text-sm font-bold text-orange-600">{formatMoney(product.price)}</p>
                        )}
                        {product.slug ? (
                            <Link
                                href={route('products.show', product.slug)}
                                className="mt-1 inline-block text-[11px] font-medium text-orange-600 hover:underline"
                            >
                                View product
                            </Link>
                        ) : null}
                    </div>
                </div>
            </div>
        );
    }

    if (message.type === 'transfer' && message.transfer) {
        return (
            <div className="rounded-xl border border-emerald-100 bg-emerald-50/70 px-3 py-2">
                <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Money transfer</p>
                <p className="mt-1 text-lg font-bold text-emerald-800">
                    {formatMoney(message.transfer.amount, message.transfer.currency || 'GHS')}
                </p>
                {message.transfer.note ? <p className="mt-1 text-sm text-emerald-900/80">{message.transfer.note}</p> : null}
            </div>
        );
    }

    if (message.type === 'call_log') {
        const status = message.call_log?.status ?? 'completed';
        const seconds = message.call_log?.duration_seconds;
        return (
            <p className="inline-flex items-center gap-1.5 text-gray-700">
                <Phone className="h-3.5 w-3.5" />
                Voice call · {status}
                {typeof seconds === 'number' && seconds > 0 ? ` · ${seconds}s` : ''}
            </p>
        );
    }

    if (message.type === 'image') {
        return <p className="text-gray-500">[Photo unavailable]</p>;
    }
    if (message.type === 'video') {
        return <p className="text-gray-500">[Video unavailable]</p>;
    }
    if (message.type === 'voice') {
        return <p className="text-gray-500">[Voice message unavailable]</p>;
    }

    return <p className="whitespace-pre-wrap">{message.body || '—'}</p>;
}

export default function AdminChatShow({ conversation, messages, blocked }: ChatShowProps) {
    return (
        <AdminLayout title="Chat thread" active="chats">
            <Head title="Chat oversight" />

            <Link href={route('admin.chats.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-orange-500">
                <ArrowLeft className="h-4 w-4" />
                Back to chats
            </Link>

            {blocked && (
                <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    One of these users has blocked the other — messaging and transfers are disabled.
                </div>
            )}

            <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Admin oversight view. Photos, videos, and voice notes are shown here for review.
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
                        const mediaBubble = ['image', 'video', 'product'].includes(message.type) && !message.is_deleted;

                        return (
                            <div
                                key={message.id}
                                className={`max-w-[85%] rounded-2xl text-sm ${
                                    isBuyer ? 'bg-orange-50 text-gray-900' : 'ml-auto bg-gray-100 text-gray-900'
                                } ${mediaBubble ? 'overflow-hidden p-1.5' : 'px-4 py-2'}`}
                            >
                                <p className={`text-[11px] font-medium text-gray-500 ${mediaBubble ? 'px-2 pt-1' : ''}`}>
                                    {message.sender?.name ?? 'User'}
                                    {message.type && message.type !== 'text' ? ` · ${message.type.replace('_', ' ')}` : ''}
                                    {message.created_at ? ` · ${new Date(message.created_at).toLocaleString('en-GH')}` : ''}
                                </p>
                                <div className={`mt-1 ${mediaBubble ? '' : ''}`}>
                                    <MessageBody message={message} />
                                </div>
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
