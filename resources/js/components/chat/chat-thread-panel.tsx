import { Link, usePage } from '@inertiajs/react';
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
    Trash2,
    X,
} from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

import OnlineIndicator from '@/components/shop/online-indicator';
import ChatCallLogItem from '@/components/chat/chat-call-log-item';
import { useChat } from '@/contexts/chat-context';
import { useToastOptional } from '@/contexts/toast-context';
import { useChatVoiceCall } from '@/hooks/use-chat-voice-call';
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
    return msg.type === 'text' || msg.type === 'image' || msg.type === 'call_log';
}

export default function ChatThreadPanel() {
    const { auth } = usePage<SharedData>().props;
    const { activeConversation, messages, setMessages, setActiveConversation, showList, loading, refreshConversations } = useChat();
    const toast = useToastOptional();
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [uploadingImage, setUploadingImage] = useState(false);
    const [other, setOther] = useState(activeConversation?.other);
    const [menuMessageId, setMenuMessageId] = useState<number | null>(null);
    const [replyingTo, setReplyingTo] = useState<ChatMessage | null>(null);
    const [editingMessage, setEditingMessage] = useState<ChatMessage | null>(null);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const lastIdRef = useRef(messages.at(-1)?.id ?? 0);

    const { callState, remoteAudioRef, startCall, acceptCall, endCall, handleCallMessage } = useChatVoiceCall(
        activeConversation?.id,
        auth.user?.id,
        {
            callerName: auth.user?.name,
            onCallLog: (msg) => {
                setMessages((prev) => {
                    if (prev.some((m) => m.id === msg.id)) return prev;
                    return [...prev, msg];
                });
                lastIdRef.current = Math.max(lastIdRef.current, msg.id);
                refreshConversations();
            },
        },
    );

    const otherName =
        other?.seller_profile?.business_name ?? other?.seller_profile?.store_name ?? other?.name ?? 'Chat';
    const location = [other?.city, other?.region].filter(Boolean).join(', ');

    useEffect(() => {
        setOther(activeConversation?.other);
        lastIdRef.current = messages.at(-1)?.id ?? 0;
    }, [activeConversation, messages]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, callState]);

    useEffect(() => {
        if (!activeConversation) return;

        const poll = async () => {
            try {
                const data = await chatApi.pollConversation(activeConversation.id, lastIdRef.current);
                if (data.other) {
                    setOther(data.other);
                    setActiveConversation((prev) => (prev ? { ...prev, other: data.other! } : prev));
                }
                if (data.messages?.length) {
                    let receivedNew = false;
                    for (const msg of data.messages) {
                        if (msg.type.startsWith('call')) {
                            await handleCallMessage(msg);
                        }
                        if (
                            (msg.type === 'text' || msg.type === 'image') &&
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
                        return [...prev, ...data.messages.filter((m) => !ids.has(m.id))];
                    });
                    lastIdRef.current = Math.max(lastIdRef.current, ...data.messages.map((m) => m.id));
                }
            } catch {
                // ignore poll errors
            }
        };

        const interval = setInterval(poll, 2000);
        return () => clearInterval(interval);
    }, [activeConversation, auth.user?.id, handleCallMessage, setActiveConversation, setMessages]);

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

    const handleStartCall = async () => {
        try {
            await startCall();
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
        return msg.body;
    };

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            <audio ref={remoteAudioRef} autoPlay playsInline className="hidden" />

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
                    <p className="truncate text-sm font-semibold text-gray-900">{otherName}</p>
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
                    <button
                        type="button"
                        onClick={handleStartCall}
                        className="rounded-lg p-1.5 text-green-600 hover:bg-green-50"
                        title="Voice call"
                    >
                        <Phone className="h-4 w-4" />
                    </button>
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
                <div className="border-b border-gray-50 bg-orange-50/50 px-3 py-1.5 text-[11px]">
                    <Link href={route('products.show', activeConversation.product.slug)} className="text-orange-600 hover:underline">
                        Re: {activeConversation.product.name}
                    </Link>
                </div>
            )}

            {(callState === 'calling' || callState === 'incoming' || callState === 'active') && (
                <div className="border-b border-green-100 bg-green-50 px-3 py-2.5 text-center">
                    {callState === 'calling' && (
                        <p className="text-xs font-medium text-green-700">Calling {otherName}...</p>
                    )}
                    {callState === 'incoming' && (
                        <div className="flex flex-wrap items-center justify-center gap-2">
                            <p className="text-xs font-medium text-green-700">{otherName} is calling</p>
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
                        <p className="text-xs font-medium text-green-700">Call in progress · tap red phone to hang up</p>
                    )}
                </div>
            )}

            <div className="flex-1 overflow-y-auto bg-gray-50 px-3 py-3" onClick={() => setMenuMessageId(null)}>
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

                        return (
                            <div
                                key={msg.id}
                                className={cn('group mb-2 flex items-end gap-1', mine ? 'justify-end' : 'justify-start')}
                            >
                                <div className={cn('relative max-w-[85%]', mine ? 'order-1' : 'order-2')}>
                                    <div
                                        className={cn(
                                            'overflow-hidden rounded-2xl text-sm',
                                            isImage ? 'p-1' : 'px-3 py-2',
                                            mine ? 'bg-orange-500 text-white' : 'bg-white text-gray-900 shadow-sm',
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
                                                    isImage && 'mx-1 mt-1',
                                                )}
                                            >
                                                <p className="font-semibold">{msg.reply_to.sender_name}</p>
                                                <p className="line-clamp-2">{msg.reply_to.body}</p>
                                            </div>
                                        )}

                                        {msg.is_deleted ? (
                                            <p className="px-2 py-1">Message deleted</p>
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
                                        ) : (
                                            <p>{msg.body}</p>
                                        )}

                                        <div
                                            className={cn(
                                                'flex items-center gap-1.5 text-[10px]',
                                                isImage ? 'px-2 pb-1' : 'mt-0.5',
                                                mine ? 'text-orange-100' : 'text-gray-400',
                                            )}
                                        >
                                            <span>{formatTime(msg.created_at)}</span>
                                            {msg.edited_at && !msg.is_deleted && msg.type === 'text' && (
                                                <span>· edited</span>
                                            )}
                                            {mine && !msg.read_at && !msg.is_deleted && (
                                                <span className={mine ? 'text-orange-200' : ''}>· unread</span>
                                            )}
                                        </div>
                                    </div>

                                    {showMenu && msg.type === 'text' && (
                                        <div
                                            className={cn(
                                                'absolute z-10 mt-1 min-w-[7rem] overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg',
                                                mine ? 'right-0' : 'left-0',
                                            )}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            {!msg.is_deleted && (
                                                <button
                                                    type="button"
                                                    onClick={() => startReply(msg)}
                                                    className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                                                >
                                                    <CornerUpLeft className="h-3.5 w-3.5" />
                                                    Reply
                                                </button>
                                            )}
                                            {mine && msg.can_edit && !msg.is_deleted && (
                                                <button
                                                    type="button"
                                                    onClick={() => startEdit(msg)}
                                                    className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                    Edit
                                                </button>
                                            )}
                                            {mine && msg.can_delete && !msg.is_deleted && (
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

                                    {showMenu && msg.type === 'image' && !msg.is_deleted && (
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
                {(replyingTo || editingMessage) && (
                    <div className="flex items-start justify-between gap-2 border-b border-gray-100 px-3 py-2">
                        <div className="min-w-0 flex-1 border-l-2 border-orange-400 pl-2">
                            <p className="text-[11px] font-semibold text-orange-600">
                                {editingMessage ? 'Editing message' : `Replying to ${replyingTo?.sender.name}`}
                            </p>
                            {!editingMessage && replyingTo && (
                                <p className="truncate text-xs text-gray-500">{replyPreview(replyingTo)}</p>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={cancelComposerExtras}
                            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            aria-label="Cancel"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                )}

                <form onSubmit={sendMessage} className="flex items-center gap-1.5 p-3">
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
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-orange-500 disabled:opacity-50"
                        title="Send photo"
                    >
                        <ImagePlus className="h-4 w-4" />
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
                        className="flex-1 rounded-full border border-gray-200 px-4 py-2 text-sm focus:border-orange-300 focus:outline-none focus:ring-1 focus:ring-orange-300"
                        maxLength={2000}
                        disabled={uploadingImage}
                    />
                    <button
                        type="submit"
                        disabled={!body.trim() || sending || uploadingImage}
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50"
                    >
                        <Send className="h-4 w-4" />
                    </button>
                </form>
            </div>
        </div>
    );
}
