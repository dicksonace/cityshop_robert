import { router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CornerUpLeft,
    ImagePlus,
    MapPin,
    MessageCircle,
    MoreVertical,
    Pencil,
    Phone,
    PhoneOff,
    Send,
    Store,
    Trash2,
    Video,
    X,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

import OnlineIndicator from '@/components/shop/online-indicator';
import ChatCallLogItem from '@/components/chat/chat-call-log-item';
import ChatSettingsSheet from '@/components/chat/chat-settings-sheet';
import ChatVideoBubble from '@/components/chat/chat-video-bubble';
import { useChat } from '@/contexts/chat-context';
import { useToastOptional } from '@/contexts/toast-context';
import { useChatVoiceCall } from '@/hooks/use-chat-voice-call';
import { useConversationRealtime } from '@/hooks/use-conversation-realtime';
import * as chatApi from '@/lib/chat-api';
import { playChatReceiveSound, playChatSendSound } from '@/lib/chat-sounds';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/chat';
import { SharedData } from '@/types';
import { productImageUrl } from '@/types/marketplace';

function formatTime(value?: string): string {
    if (!value) return '';
    return new Date(value).toLocaleTimeString('en-GH', { hour: '2-digit', minute: '2-digit' });
}

function isTimelineMessage(msg: ChatMessage): boolean {
    return (
        msg.type === 'text' ||
        msg.type === 'image' ||
        msg.type === 'video' ||
        msg.type === 'voice' ||
        msg.type === 'product' ||
        msg.type === 'call_log'
    );
}

function maxMessageId(list: ChatMessage[]): number {
    return list.reduce((max, m) => (m.id > max ? m.id : max), 0);
}

export default function ChatThreadPanel() {
    const { auth } = usePage<SharedData>().props;
    const {
        activeConversation,
        messages,
        setMessages,
        setActiveConversation,
        showList,
        loading,
        refreshConversations,
        attachProduct,
        clearAttachProduct,
        closeWidget,
    } = useChat();
    const toast = useToastOptional();
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [sendingProduct, setSendingProduct] = useState(false);
    const [uploadingImage, setUploadingImage] = useState(false);
    const [other, setOther] = useState(activeConversation?.other);
    const [menuMessageId, setMenuMessageId] = useState<number | null>(null);
    const [replyingTo, setReplyingTo] = useState<ChatMessage | null>(null);
    const [editingMessage, setEditingMessage] = useState<ChatMessage | null>(null);
    const [showSettings, setShowSettings] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const messagesScrollRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const lastIdRef = useRef(messages.at(-1)?.id ?? 0);
    const lastScrolledConversationId = useRef<number | null>(null);
    const pinnedToBottomRef = useRef(true);

    const { callState, callKind, remoteAudioRef, localVideoRef, remoteVideoRef, startCall, acceptCall, endCall, handleCallMessage } =
        useChatVoiceCall(activeConversation?.id, auth.user?.id, {
            callerName: auth.user?.name,
            onCallLog: (msg) => {
                setMessages((prev) => {
                    if (prev.some((m) => m.id === msg.id)) return prev;
                    return [...prev, msg];
                });
                lastIdRef.current = Math.max(lastIdRef.current, msg.id);
                refreshConversations();
            },
        });

    const otherName =
        other?.seller_profile?.business_name ?? other?.seller_profile?.store_name ?? other?.name ?? 'Chat';
    const location = [other?.city, other?.region].filter(Boolean).join(', ');
    const storeSlug = other?.seller_profile?.slug?.trim() || '';

    const openStore = () => {
        if (!storeSlug) return;
        closeWidget();
        router.visit(route('store.show', storeSlug));
    };

    useEffect(() => {
        setOther(activeConversation?.other);
        lastIdRef.current = maxMessageId(messages);
    }, [activeConversation, messages]);

    // Jump straight to the latest message — never animate top→bottom on open/refresh.
    useEffect(() => {
        const conversationId = activeConversation?.id ?? null;
        if (conversationId !== lastScrolledConversationId.current) {
            lastScrolledConversationId.current = conversationId;
            pinnedToBottomRef.current = true;
        }

        if (!pinnedToBottomRef.current && callState === 'idle') return;

        const container = messagesScrollRef.current;
        if (!container) return;

        // Instant snap (no smooth scroll) so reload doesn't "play" the history.
        container.scrollTop = container.scrollHeight;
    }, [messages, callState, activeConversation?.id]);

    const onMessagesScroll = () => {
        const container = messagesScrollRef.current;
        if (!container) return;
        const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
        pinnedToBottomRef.current = distanceFromBottom < 80;
    };

    const ingestMessages = useCallback(
        async (incoming: ChatMessage[], { playSound }: { playSound: boolean }) => {
            if (!incoming.length) return;

            let receivedNew = false;
            for (const msg of incoming) {
                if (msg.type.startsWith('call')) {
                    await handleCallMessage(msg);
                }
                if (
                    playSound &&
                    (msg.type === 'text' ||
                        msg.type === 'image' ||
                        msg.type === 'video' ||
                        msg.type === 'voice' ||
                        msg.type === 'product') &&
                    msg.sender_id !== auth.user?.id
                ) {
                    receivedNew = true;
                }
            }
            if (receivedNew) {
                playChatReceiveSound();
            }
            setMessages((prev) => {
                const ids = new Set(prev.map((m) => m.id));
                return [...prev, ...incoming.filter((m) => !ids.has(m.id))];
            });
            lastIdRef.current = Math.max(lastIdRef.current, maxMessageId(incoming));
        },
        [auth.user?.id, handleCallMessage, setMessages],
    );

    const realtimeLive = useConversationRealtime(activeConversation?.id, (msg) => {
        void ingestMessages([msg], { playSound: true });
    });

    useEffect(() => {
        if (!activeConversation) return;

        const poll = async () => {
            try {
                const data = await chatApi.pollConversation(activeConversation.id, lastIdRef.current);
                if (data.other) {
                    setOther(data.other);
                    setActiveConversation((prev) => (prev ? { ...prev, other: data.other! } : prev));
                }
                if (data.read_message_ids?.length) {
                    const readSet = new Set(data.read_message_ids);
                    setMessages((prev) =>
                        prev.map((m) =>
                            m.sender_id === auth.user?.id && !m.read_at && readSet.has(m.id)
                                ? { ...m, read_at: new Date().toISOString() }
                                : m,
                        ),
                    );
                }
                if (data.messages?.length) {
                    await ingestMessages(data.messages, { playSound: !realtimeLive });
                }
            } catch {
                // ignore poll errors
            }
        };

        // Live Reverb carries new messages; keep a slow poll for presence + missed events.
        const interval = setInterval(poll, realtimeLive ? 15000 : 2000);
        return () => clearInterval(interval);
    }, [
        activeConversation,
        ingestMessages,
        realtimeLive,
        setActiveConversation,
    ]);

    const replaceMessage = (updated: ChatMessage) => {
        setMessages((prev) => prev.map((m) => (m.id === updated.id ? updated : m)));
    };

    const startReply = (msg: ChatMessage) => {
        setMenuMessageId(null);
        setEditingMessage(null);
        setReplyingTo(msg);
        inputRef.current?.focus();
    };

    const startEdit = (msg: ChatMessage) => {
        setMenuMessageId(null);
        setReplyingTo(null);
        setEditingMessage(msg);
        setBody(msg.body ?? '');
        inputRef.current?.focus();
    };

    const cancelComposerExtras = () => {
        setReplyingTo(null);
        setEditingMessage(null);
        setBody('');
    };

    const handleDelete = async (msg: ChatMessage) => {
        if (!activeConversation) return;
        setMenuMessageId(null);
        try {
            const updated = await chatApi.deleteChatMessage(activeConversation.id, msg.id);
            replaceMessage(updated);
            if (editingMessage?.id === msg.id) cancelComposerExtras();
            refreshConversations();
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not delete message');
        }
    };

    const sendMessage = async (e: FormEvent) => {
        e.preventDefault();
        if (!activeConversation || !body.trim() || sending) return;
        setSending(true);
        try {
            if (editingMessage) {
                const updated = await chatApi.updateChatMessage(activeConversation.id, editingMessage.id, body.trim());
                replaceMessage(updated);
                cancelComposerExtras();
            } else {
                const message = await chatApi.sendChatMessage(
                    activeConversation.id,
                    body.trim(),
                    replyingTo?.id,
                );
                setMessages((prev) => [...prev, message]);
                lastIdRef.current = Math.max(lastIdRef.current, message.id);
                setBody('');
                setReplyingTo(null);
                playChatSendSound();
            }
            refreshConversations();
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not send message');
        } finally {
            setSending(false);
        }
    };

    const handleImageSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file || !activeConversation || uploadingImage) return;

        if (!file.type.startsWith('image/')) {
            toast?.error('Please choose an image file');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            toast?.error('Image must be 5MB or smaller');
            return;
        }

        setUploadingImage(true);
        try {
            const message = await chatApi.uploadChatImage(activeConversation.id, file);
            setMessages((prev) => [...prev, message]);
            lastIdRef.current = Math.max(lastIdRef.current, message.id);
            refreshConversations();
            playChatSendSound();
            toast?.success('Photo sent');
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not send photo');
        } finally {
            setUploadingImage(false);
        }
    };

    const handleSendProduct = async () => {
        if (!activeConversation || !attachProduct || sendingProduct) return;
        setSendingProduct(true);
        try {
            const message = await chatApi.sendChatProduct(activeConversation.id, attachProduct.id);
            setMessages((prev) => [...prev, message]);
            lastIdRef.current = Math.max(lastIdRef.current, message.id);
            clearAttachProduct();
            setActiveConversation((prev) =>
                prev
                    ? {
                          ...prev,
                          product: {
                              id: attachProduct.id,
                              name: attachProduct.name,
                              slug: attachProduct.slug,
                              price: attachProduct.price,
                              image_url: attachProduct.image_url,
                          },
                      }
                    : prev,
            );
            refreshConversations();
            playChatSendSound();
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not send product');
        } finally {
            setSendingProduct(false);
        }
    };

    const handleStartCall = async (kind: 'voice' | 'video' = 'voice') => {
        try {
            await startCall(kind);
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not start call');
        }
    };

    const handleAcceptCall = async () => {
        try {
            await acceptCall();
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not join call');
        }
    };

    if (loading && !activeConversation) {
        return (
            <div className="flex flex-1 items-center justify-center text-sm text-gray-400">
                Loading chat...
            </div>
        );
    }

    if (!activeConversation) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center px-4 text-center text-gray-400">
                <MessageCircle className="h-10 w-10" />
                <p className="mt-2 text-sm">Select a conversation</p>
            </div>
        );
    }

    const timelineMessages = messages.filter(isTimelineMessage);

    const replyPreview = (msg: ChatMessage) => {
        if (msg.type === 'image') return msg.body?.trim() || 'Photo';
        if (msg.type === 'video') return msg.body?.trim() || 'Video';
        if (msg.type === 'voice') return 'Voice message';
        if (msg.type === 'product') {
            return msg.product?.name || msg.body?.trim() || 'Product';
        }
        return msg.body;
    };

    // Close the sheet first — otherwise the product page loads under the chat
    // and a seller/buyer thinks the card did nothing.
    const openProduct = (slug: string) => {
        if (!slug) return;
        closeWidget();
        router.visit(route('products.show', slug));
    };

    return (
        <div className="relative flex flex-1 flex-col overflow-hidden">
            <audio ref={remoteAudioRef} autoPlay playsInline className="hidden" />

            {showSettings && activeConversation && (
                <ChatSettingsSheet
                    conversationId={activeConversation.id}
                    peerName={otherName}
                    sellerId={other?.id}
                    productId={activeConversation.product?.id}
                    canComplain={Boolean(storeSlug && other?.id && other.id !== auth.user?.id)}
                    onClose={() => setShowSettings(false)}
                    onDeleted={() => {
                        setShowSettings(false);
                        void showList();
                        void refreshConversations();
                    }}
                />
            )}

            <div className="flex items-center gap-2 border-b border-gray-100 bg-white px-3 py-2.5">
                <button type="button" onClick={showList} className="rounded-lg p-1.5 hover:bg-gray-100">
                    <ArrowLeft className="h-4 w-4 text-gray-600" />
                </button>
                <div className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-blue-500 to-orange-500 text-sm font-bold text-white">
                    {other?.avatar ? (
                        <img src={productImageUrl(other.avatar)} alt="" className="h-full w-full object-cover" />
                    ) : (
                        otherName.charAt(0).toUpperCase()
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    {storeSlug ? (
                        <button
                            type="button"
                            onClick={openStore}
                            className="block w-full truncate text-left text-sm font-semibold text-gray-900 hover:text-orange-600"
                            title="View store"
                        >
                            {otherName}
                        </button>
                    ) : (
                        <p className="truncate text-sm font-semibold text-gray-900">{otherName}</p>
                    )}
                    <div className="flex items-center gap-1.5">
                        {other && <OnlineIndicator online={other.online} size="sm" />}
                        {location && (
                            <span className="flex items-center gap-0.5 truncate text-[10px] text-gray-400">
                                <MapPin className="h-2.5 w-2.5" />
                                {location}
                            </span>
                        )}
                    </div>
                </div>
                {callState === 'idle' ? (
                    <div className="flex items-center gap-0.5">
                        {storeSlug && (
                            <button
                                type="button"
                                onClick={openStore}
                                className="rounded-lg p-1.5 text-orange-600 hover:bg-orange-50"
                                title="View store"
                            >
                                <Store className="h-4 w-4" />
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => handleStartCall('voice')}
                            className="rounded-lg p-1.5 text-green-600 hover:bg-green-50"
                            title="Audio call"
                        >
                            <Phone className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => handleStartCall('video')}
                            className="rounded-lg p-1.5 text-sky-600 hover:bg-sky-50"
                            title="Video call"
                        >
                            <Video className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowSettings(true)}
                            className="rounded-lg p-1.5 text-gray-600 hover:bg-gray-100"
                            title="Chat settings"
                        >
                            <MoreVertical className="h-4 w-4" />
                        </button>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() =>
                            endCall(
                                callState === 'active'
                                    ? 'completed'
                                    : callState === 'calling'
                                      ? 'missed'
                                      : 'declined',
                            )
                        }
                        className="rounded-lg p-1.5 text-red-600 hover:bg-red-50"
                        title="End call"
                    >
                        <PhoneOff className="h-4 w-4" />
                    </button>
                )}
            </div>

            {activeConversation.product && (
                <div className="flex items-center gap-2 border-b border-orange-100 bg-orange-50/70 px-3 py-2">
                    {activeConversation.product.image_url ? (
                        <img
                            src={productImageUrl(activeConversation.product.image_url)}
                            alt=""
                            className="h-10 w-10 shrink-0 rounded-lg object-cover"
                        />
                    ) : (
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-500">
                            <MessageCircle className="h-4 w-4" />
                        </div>
                    )}
                    <div className="min-w-0 flex-1">
                        <button
                            type="button"
                            onClick={() => openProduct(activeConversation.product!.slug)}
                            className="block w-full truncate text-left text-xs font-semibold text-gray-900 hover:text-orange-600"
                        >
                            {activeConversation.product.name}
                        </button>
                        {typeof activeConversation.product.price === 'number' && (
                            <p className="text-[11px] font-bold text-orange-600">
                                GH₵{' '}
                                {activeConversation.product.price.toLocaleString('en-GH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                })}
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => openProduct(activeConversation.product!.slug)}
                        className="shrink-0 text-[11px] font-semibold text-orange-600 hover:underline"
                    >
                        View
                    </button>
                </div>
            )}

            {(callState === 'calling' || callState === 'incoming' || callState === 'active') && (
                <div className="border-b border-green-100 bg-green-50 px-3 py-2.5 text-center">
                    {callState === 'calling' && (
                        <p className="text-xs font-medium text-green-700">
                            {callKind === 'video' ? 'Video calling' : 'Calling'} {otherName}...
                        </p>
                    )}
                    {callState === 'incoming' && (
                        <div className="flex flex-wrap items-center justify-center gap-2">
                            <p className="text-xs font-medium text-green-700">
                                {otherName} is {callKind === 'video' ? 'video ' : ''}calling
                            </p>
                            <button
                                type="button"
                                onClick={handleAcceptCall}
                                className="rounded-full bg-green-500 px-3 py-1 text-xs font-medium text-white hover:bg-green-600"
                            >
                                Accept
                            </button>
                            <button
                                type="button"
                                onClick={() => endCall('declined')}
                                className="rounded-full bg-red-500 px-3 py-1 text-xs font-medium text-white hover:bg-red-600"
                            >
                                Decline
                            </button>
                        </div>
                    )}
                    {callState === 'active' && (
                        <p className="text-xs font-medium text-green-700">
                            {callKind === 'video' ? 'Video call' : 'Call'} in progress · tap red phone to hang up
                        </p>
                    )}
                    {callKind === 'video' && (callState === 'calling' || callState === 'active') && (
                        <div className="relative mx-auto mt-2 aspect-video max-h-48 w-full max-w-sm overflow-hidden rounded-xl bg-black">
                            <video ref={remoteVideoRef} autoPlay playsInline className="h-full w-full object-cover" />
                            <video
                                ref={localVideoRef}
                                autoPlay
                                playsInline
                                muted
                                className="absolute bottom-2 right-2 h-20 w-14 rounded-lg border border-white/40 object-cover"
                            />
                        </div>
                    )}
                </div>
            )}

            <div
                ref={messagesScrollRef}
                className="flex-1 overflow-y-auto bg-gray-50 px-3 py-3"
                onScroll={onMessagesScroll}
                onClick={() => setMenuMessageId(null)}
            >
                {timelineMessages.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center text-center text-gray-400">
                        <p className="text-xs">Say hi to {otherName}</p>
                    </div>
                ) : (
                    timelineMessages.map((msg) => {
                        if (msg.type === 'call_log' && msg.call_log) {
                            return (
                                <ChatCallLogItem
                                    key={msg.id}
                                    log={msg.call_log}
                                    viewerId={auth.user?.id ?? 0}
                                    otherName={otherName}
                                    createdAt={msg.created_at}
                                />
                            );
                        }

                        const mine = msg.sender_id === auth.user?.id;
                        const showMenu = menuMessageId === msg.id;
                        const isImage = msg.type === 'image' && msg.image_url && !msg.is_deleted;
                        const isVideo = msg.type === 'video' && msg.video_url && !msg.is_deleted;
                        const isVoice = msg.type === 'voice' && msg.voice_url && !msg.is_deleted;
                        const productCard =
                            msg.type === 'product' && !msg.is_deleted
                                ? (msg.product ??
                                  (msg.metadata?.product as ChatMessage['product'] | undefined) ??
                                  null)
                                : null;
                        // Always treat product-type rows as product bubbles so sellers
                        // never "miss" a share when metadata is thin.
                        const isProduct = msg.type === 'product' && !msg.is_deleted;

                        return (
                            <div
                                key={msg.id}
                                className={cn('group mb-2 flex items-end gap-1', mine ? 'justify-end' : 'justify-start')}
                            >
                                <div className={cn('relative max-w-[85%]', mine ? 'order-1' : 'order-2')}>
                                    <div
                                        className={cn(
                                            'overflow-hidden rounded-2xl text-sm',
                                            isImage || isVideo ? 'p-1' : isProduct ? 'p-0' : 'px-3 py-2',
                                            isProduct
                                                ? 'border border-orange-100 bg-white text-gray-900 shadow-sm'
                                                : mine
                                                  ? 'bg-orange-500 text-white'
                                                  : 'bg-white text-gray-900 shadow-sm',
                                            msg.is_deleted && 'px-3 py-2 italic opacity-70',
                                        )}
                                    >
                                        {msg.reply_to && !msg.is_deleted && (
                                            <div
                                                className={cn(
                                                    'mb-1.5 rounded-lg border-l-2 px-2 py-1 text-[11px]',
                                                    mine
                                                        ? 'border-orange-200 bg-orange-600/40 text-orange-50'
                                                        : 'border-orange-300 bg-orange-50 text-gray-600',
                                                    (isImage || isVideo) && 'mx-1 mt-1',
                                                )}
                                            >
                                                <p className="font-semibold">{msg.reply_to.sender_name}</p>
                                                {msg.reply_to.type === 'product' && msg.reply_to.product ? (
                                                    <div className="mt-1 flex items-center gap-2">
                                                        {msg.reply_to.product.image_url ? (
                                                            <img
                                                                src={productImageUrl(msg.reply_to.product.image_url)}
                                                                alt=""
                                                                className="h-8 w-8 shrink-0 rounded object-cover"
                                                            />
                                                        ) : null}
                                                        <div className="min-w-0">
                                                            <p className="line-clamp-1 font-medium">
                                                                {msg.reply_to.product.name || msg.reply_to.body}
                                                            </p>
                                                            {typeof msg.reply_to.product.price === 'number' && (
                                                                <p className="text-[10px] opacity-80">
                                                                    GH₵{' '}
                                                                    {msg.reply_to.product.price.toLocaleString('en-GH', {
                                                                        minimumFractionDigits: 2,
                                                                        maximumFractionDigits: 2,
                                                                    })}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <p className="line-clamp-2">{msg.reply_to.body}</p>
                                                )}
                                            </div>
                                        )}

                                        {msg.is_deleted ? (
                                            <p className="px-2 py-1">Message deleted</p>
                                        ) : isProduct ? (
                                            productCard?.slug ? (
                                                <button
                                                    type="button"
                                                    onClick={() => openProduct(productCard.slug)}
                                                    className="block min-w-[14rem] w-full p-2.5 text-left transition hover:bg-orange-50/60"
                                                >
                                                    <div className="flex gap-2.5">
                                                        {productCard.image_url ? (
                                                            <img
                                                                src={productImageUrl(productCard.image_url)}
                                                                alt=""
                                                                className="h-14 w-14 shrink-0 rounded-lg object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-orange-500">
                                                                <MessageCircle className="h-5 w-5" />
                                                            </div>
                                                        )}
                                                        <div className="min-w-0 flex-1">
                                                            <p className="line-clamp-2 text-xs font-semibold text-gray-900">
                                                                {productCard.name || msg.body || 'Product'}
                                                            </p>
                                                            {typeof productCard.price === 'number' && (
                                                                <p className="mt-1 text-sm font-bold text-orange-600">
                                                                    GH₵{' '}
                                                                    {productCard.price.toLocaleString('en-GH', {
                                                                        minimumFractionDigits: 2,
                                                                        maximumFractionDigits: 2,
                                                                    })}
                                                                </p>
                                                            )}
                                                            <p className="mt-1 text-[10px] font-medium text-gray-400">
                                                                Tap to view product
                                                            </p>
                                                        </div>
                                                    </div>
                                                </button>
                                            ) : (
                                                <div className="min-w-[14rem] p-2.5">
                                                    <p className="text-xs font-semibold text-gray-900">
                                                        {msg.body || 'Shared a product'}
                                                    </p>
                                                    <p className="mt-1 text-[10px] font-medium text-gray-400">
                                                        Product
                                                    </p>
                                                </div>
                                            )
                                        ) : isImage ? (
                                            <div>
                                                <a href={msg.image_url!} target="_blank" rel="noreferrer">
                                                    <img
                                                        src={msg.image_url!}
                                                        alt={msg.body || 'Shared photo'}
                                                        className="max-h-52 w-full rounded-xl object-cover"
                                                        loading="lazy"
                                                    />
                                                </a>
                                                {msg.body?.trim() && (
                                                    <p className="px-2 py-1.5 text-sm">{msg.body}</p>
                                                )}
                                            </div>
                                        ) : isVideo ? (
                                            <ChatVideoBubble src={msg.video_url!} caption={msg.body} />
                                        ) : isVoice ? (
                                            <div className={cn('min-w-[12rem]', (isImage || isVideo) && 'px-1')}>
                                                <audio src={msg.voice_url!} controls preload="metadata" className="w-full" />
                                                {msg.duration_seconds ? (
                                                    <p className={cn('mt-1 text-[11px]', mine ? 'text-orange-100' : 'text-gray-400')}>
                                                        {msg.duration_seconds}s
                                                    </p>
                                                ) : null}
                                            </div>
                                        ) : (
                                            <p>{msg.body}</p>
                                        )}

                                        <div
                                            className={cn(
                                                'flex items-center gap-1.5 text-[10px]',
                                                isImage || isVideo || isProduct ? 'px-2 pb-1.5' : 'mt-0.5',
                                                isProduct || !mine ? 'text-gray-400' : 'text-orange-100',
                                            )}
                                        >
                                            <span>{formatTime(msg.created_at)}</span>
                                            {msg.edited_at && !msg.is_deleted && msg.type === 'text' && (
                                                <span>· edited</span>
                                            )}
                                            {mine && !msg.is_deleted && (
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center',
                                                        msg.read_at
                                                            ? isProduct || !mine
                                                                ? 'text-sky-500'
                                                                : 'text-sky-100'
                                                            : isProduct || !mine
                                                              ? 'text-gray-400'
                                                              : 'text-orange-200',
                                                    )}
                                                    title={msg.read_at ? 'Read' : 'Sent'}
                                                >
                                                    {msg.read_at ? '✓✓' : '✓'}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {showMenu && ['text', 'image', 'video', 'voice', 'product'].includes(msg.type) && !msg.is_deleted && (
                                        <div
                                            className={cn(
                                                'absolute z-10 mt-1 min-w-[7rem] overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg',
                                                mine ? 'right-0' : 'left-0',
                                            )}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => startReply(msg)}
                                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                                            >
                                                <CornerUpLeft className="h-3.5 w-3.5" />
                                                Reply
                                            </button>
                                            {mine && msg.can_edit && msg.type === 'text' && (
                                                <button
                                                    type="button"
                                                    onClick={() => startEdit(msg)}
                                                    className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                    Edit
                                                </button>
                                            )}
                                            {mine && msg.can_delete && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(msg)}
                                                    className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-red-600 hover:bg-red-50"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                    Delete
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {!msg.is_deleted && (
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setMenuMessageId(showMenu ? null : msg.id);
                                        }}
                                        className={cn(
                                            'order-2 shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600',
                                            mine && 'order-0',
                                            showMenu ? 'opacity-100' : 'opacity-60 sm:opacity-0 sm:group-hover:opacity-100',
                                        )}
                                        aria-label="Message options"
                                    >
                                        <MoreVertical className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>
                        );
                    })
                )}
                <div ref={messagesEndRef} />
            </div>

            <div className="border-t border-gray-100 bg-white">
                {attachProduct && (
                    <div className="flex items-center gap-2.5 border-b border-orange-100 bg-orange-50/90 px-3 py-3">
                        {attachProduct.image_url ? (
                            <img
                                src={productImageUrl(attachProduct.image_url)}
                                alt=""
                                className="h-12 w-12 shrink-0 rounded-lg object-cover"
                            />
                        ) : (
                            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-white text-orange-500">
                                <MessageCircle className="h-5 w-5" />
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-gray-900">{attachProduct.name}</p>
                            {typeof attachProduct.price === 'number' && (
                                <p className="text-sm font-bold text-orange-600">
                                    GH₵{' '}
                                    {attachProduct.price.toLocaleString('en-GH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })}
                                </p>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={handleSendProduct}
                            disabled={sendingProduct}
                            className="shrink-0 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50"
                        >
                            {sendingProduct ? 'Sending…' : 'Send'}
                        </button>
                        <button
                            type="button"
                            onClick={clearAttachProduct}
                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-gray-500 ring-1 ring-gray-200 hover:bg-gray-50"
                            aria-label="Dismiss product"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}

                {(replyingTo || editingMessage) && (
                    <div className="flex items-center gap-3 border-b border-orange-100 bg-orange-50/80 px-3 py-3 sm:py-2.5">
                        <div className="min-w-0 flex-1 border-l-[3px] border-orange-500 pl-3">
                            <p className="text-sm font-semibold text-orange-700 sm:text-xs">
                                {editingMessage ? 'Editing message' : `Replying to ${replyingTo?.sender.name}`}
                            </p>
                            {!editingMessage && replyingTo && (
                                <div className="mt-1 flex items-center gap-2">
                                    {replyingTo.type === 'product' &&
                                        (replyingTo.product?.image_url ||
                                            (replyingTo.metadata?.product as { image_url?: string } | undefined)
                                                ?.image_url) && (
                                            <img
                                                src={productImageUrl(
                                                    replyingTo.product?.image_url ||
                                                        (
                                                            replyingTo.metadata?.product as {
                                                                image_url?: string;
                                                            }
                                                        )?.image_url,
                                                )}
                                                alt=""
                                                className="h-9 w-9 shrink-0 rounded object-cover"
                                            />
                                        )}
                                    <p className="truncate text-sm text-gray-600 sm:text-xs">
                                        {replyPreview(replyingTo)}
                                    </p>
                                </div>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={cancelComposerExtras}
                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-gray-500 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 hover:text-gray-700 sm:h-8 sm:w-8"
                            aria-label="Cancel"
                        >
                            <X className="h-5 w-5 sm:h-4 sm:w-4" />
                        </button>
                    </div>
                )}

                <form onSubmit={sendMessage} className="flex items-center gap-2 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom,0px))] sm:gap-1.5 sm:pb-3">
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                        className="hidden"
                        onChange={handleImageSelect}
                    />
                    <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={uploadingImage || sending}
                        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-orange-500 disabled:opacity-50 sm:h-9 sm:w-9"
                        title="Send photo"
                    >
                        <ImagePlus className="h-5 w-5 sm:h-4 sm:w-4" />
                    </button>
                    <input
                        ref={inputRef}
                        type="text"
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                        placeholder={
                            uploadingImage
                                ? 'Uploading photo...'
                                : editingMessage
                                  ? 'Edit your message...'
                                  : replyingTo
                                    ? 'Write a reply...'
                                    : 'Type a message...'
                        }
                        className="min-h-11 flex-1 rounded-full border border-gray-200 px-4 py-2.5 text-base focus:border-orange-300 focus:outline-none focus:ring-1 focus:ring-orange-300 sm:min-h-0 sm:py-2 sm:text-sm"
                        maxLength={2000}
                        disabled={uploadingImage}
                    />
                    <button
                        type="submit"
                        disabled={!body.trim() || sending || uploadingImage}
                        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50 sm:h-9 sm:w-9"
                    >
                        <Send className="h-5 w-5 sm:h-4 sm:w-4" />
                    </button>
                </form>
            </div>
        </div>
    );
}
