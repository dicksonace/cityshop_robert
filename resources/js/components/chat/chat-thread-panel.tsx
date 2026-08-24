import { router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    CornerUpLeft,
    Forward,
    ImagePlus,
    FilePlus,
    MapPin,
    MessageCircle,
    Mic,
    MoreVertical,
    Pencil,
    Phone,
    PhoneOff,
    Send,
    Square,
    Store,
    Trash2,
    Video,
    X,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

import OnlineIndicator from '@/components/shop/online-indicator';
import ChatCallLogItem from '@/components/chat/chat-call-log-item';
import { ChatEmojiPicker, ChatQuickReactions } from '@/components/chat/chat-emoji-picker';
import ChatFileBubble from '@/components/chat/chat-file-bubble';
import ChatLinkedText from '@/components/chat/chat-linked-text';
import ChatSettingsSheet from '@/components/chat/chat-settings-sheet';
import ChatTransferBubble from '@/components/chat/chat-transfer-bubble';
import ChatTransferSheet from '@/components/chat/chat-transfer-sheet';
import ChatVideoBubble from '@/components/chat/chat-video-bubble';
import ChatVoiceBubble from '@/components/chat/chat-voice-bubble';
import { useChat } from '@/contexts/chat-context';
import { useToastOptional } from '@/contexts/toast-context';
import { useChatVoiceCall } from '@/hooks/use-chat-voice-call';
import { useConversationRealtime } from '@/hooks/use-conversation-realtime';
import * as chatApi from '@/lib/chat-api';
import { playChatReceiveSound, playChatSendSound, playMoneyReceivedSound } from '@/lib/chat-sounds';
import { cn } from '@/lib/utils';
import { canEditChatMessage, type ChatMessage } from '@/types/chat';
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
        msg.type === 'transfer' ||
        msg.type === 'file' ||
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
    const [pendingImage, setPendingImage] = useState<File | null>(null);
    const [pendingPreview, setPendingPreview] = useState<string | null>(null);
    const [viewOnce, setViewOnce] = useState(false);
    const [viewOnceMedia, setViewOnceMedia] = useState<{ src: string; video: boolean } | null>(null);
    const [uploadingFile, setUploadingFile] = useState(false);
    const [uploadingVoice, setUploadingVoice] = useState(false);
    const [recordingVoice, setRecordingVoice] = useState(false);
    const [voiceSeconds, setVoiceSeconds] = useState(0);
    const [showTransfer, setShowTransfer] = useState(false);
    const [other, setOther] = useState(activeConversation?.other);
    const [menuMessageId, setMenuMessageId] = useState<number | null>(null);
    const [emojiPickerMessageId, setEmojiPickerMessageId] = useState<number | null>(null);
    const [replyingTo, setReplyingTo] = useState<ChatMessage | null>(null);
    const [editingMessage, setEditingMessage] = useState<ChatMessage | null>(null);
    const [showSettings, setShowSettings] = useState(false);
    const [forwardingMessage, setForwardingMessage] = useState<ChatMessage | null>(null);
    const [forwardMemberIds, setForwardMemberIds] = useState<number[]>([]);
    const [forwardTargets, setForwardTargets] = useState<chatApi.ForwardTarget[]>([]);
    const [forwardTargetsLoading, setForwardTargetsLoading] = useState(false);
    const [forwarding, setForwarding] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const messagesScrollRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const docFileInputRef = useRef<HTMLInputElement>(null);
    const mediaRecorderRef = useRef<MediaRecorder | null>(null);
    const voiceChunksRef = useRef<Blob[]>([]);
    const voiceStartedAtRef = useRef<number>(0);
    const voiceTimerRef = useRef<number | null>(null);
    const lastIdRef = useRef(messages.at(-1)?.id ?? 0);
    const updatedAfterRef = useRef(new Date().toISOString());
    const announcedSoundIdsRef = useRef<Set<number>>(new Set());
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
            onCallError: (message) => toast?.error(message),
        });

    const otherName =
        other?.seller_profile?.business_name ?? other?.seller_profile?.store_name ?? other?.name ?? 'Chat';
    const location = [other?.city, other?.region].filter(Boolean).join(', ');
    const storeSlug = other?.seller_profile?.slug?.trim() || other?.store_slug?.trim() || '';
    const isGroup = Boolean(activeConversation?.is_group);
    const canComplain = Boolean(
        !isGroup &&
            (activeConversation?.can_complain ||
                (activeConversation?.buyer_id != null &&
                    activeConversation.buyer_id === auth.user?.id &&
                    activeConversation.seller_id != null &&
                    activeConversation.seller_id !== auth.user?.id)),
    );
    const complaintSellerId = activeConversation?.seller_id ?? (canComplain ? other?.id : null);

    const openStore = () => {
        if (!storeSlug) return;
        closeWidget();
        router.visit(route('store.show', storeSlug));
    };

    useEffect(() => {
        setOther(activeConversation?.other);
        lastIdRef.current = maxMessageId(messages);
        // Keep announced ids aligned with what's on screen so poll gap-fill
        // cannot re-chime the same messages every few seconds.
        announcedSoundIdsRef.current = new Set(messages.map((m) => m.id));
        if (activeConversation?.is_group) {
            setShowTransfer(false);
        }
    }, [activeConversation?.id, activeConversation?.other, activeConversation?.is_group, messages]);

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

            for (const msg of incoming) {
                if (msg.type.startsWith('call')) {
                    await handleCallMessage(msg);
                }
            }

            let receivedNew = false;
            let receivedMoney = false;
            setMessages((prev) => {
                const next = [...prev];
                const indexById = new Map(prev.map((m, index) => [m.id, index]));
                for (const msg of incoming) {
                    const existingIndex = indexById.get(msg.id);
                    const isChatContent =
                        msg.type === 'text' ||
                        msg.type === 'image' ||
                        msg.type === 'video' ||
                        msg.type === 'voice' ||
                        msg.type === 'product' ||
                        msg.type === 'transfer' ||
                        msg.type === 'file';
                    if (
                        playSound &&
                        existingIndex === undefined &&
                        isChatContent &&
                        msg.sender_id !== auth.user?.id &&
                        !announcedSoundIdsRef.current.has(msg.id)
                    ) {
                        receivedNew = true;
                        if (msg.type === 'transfer') {
                            receivedMoney = true;
                        }
                        announcedSoundIdsRef.current.add(msg.id);
                    }
                    if (existingIndex !== undefined) {
                        next[existingIndex] = { ...next[existingIndex], ...msg };
                    } else {
                        indexById.set(msg.id, next.length);
                        next.push(msg);
                    }
                }
                return next;
            });

            if (receivedMoney) {
                playMoneyReceivedSound();
            } else if (receivedNew) {
                playChatReceiveSound();
            }
            lastIdRef.current = Math.max(lastIdRef.current, maxMessageId(incoming));
        },
        [auth.user?.id, handleCallMessage, setMessages],
    );

    const realtimeLive = useConversationRealtime(activeConversation?.id, (msg) => {
        void ingestMessages([msg], { playSound: true });
    });

    const conversationId = activeConversation?.id;

    useEffect(() => {
        if (!conversationId) return;

        const poll = async () => {
            try {
                const data = await chatApi.pollConversation(
                    conversationId,
                    lastIdRef.current,
                    updatedAfterRef.current,
                );
                if (data.other) {
                    setOther(data.other);
                    setActiveConversation((prev) =>
                        prev
                            ? {
                                  ...prev,
                                  other: data.other!,
                                  is_group: data.is_group ?? prev.is_group,
                              }
                            : prev,
                    );
                } else if (typeof data.is_group === 'boolean') {
                    setActiveConversation((prev) => (prev ? { ...prev, is_group: data.is_group } : prev));
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
                if (data.updated?.length) {
                    await ingestMessages(data.updated, { playSound: false });
                }
                if (data.messages?.length) {
                    await ingestMessages(data.messages, { playSound: !realtimeLive });
                }
                updatedAfterRef.current = new Date().toISOString();
            } catch {
                // ignore poll errors
            }
        };

        // Live Reverb carries chat text; ICE is poll-only (broadcasting ICE
        // used to exhaust PHP workers and take the whole site down). Never back
        // off to 15s — a "live" flag used to mean key present, not delivering.
        const inCall = callState !== 'idle';
        void poll();
        const interval = setInterval(poll, inCall ? 1000 : 2000);
        return () => clearInterval(interval);
    }, [
        conversationId,
        callState,
        ingestMessages,
        realtimeLive,
        setActiveConversation,
        auth.user?.id,
    ]);

    const replaceMessage = (updated: ChatMessage) => {
        setMessages((prev) => prev.map((m) => (m.id === updated.id ? updated : m)));
    };

    const handleReact = async (msg: ChatMessage, emoji: string) => {
        if (!activeConversation || msg.is_deleted) return;
        setMenuMessageId(null);
        setEmojiPickerMessageId(null);
        try {
            const updated = await chatApi.reactToChatMessage(activeConversation.id, msg.id, emoji);
            replaceMessage(updated);
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not add emoji');
        }
    };

    const reactionIsMine = (msg: ChatMessage, emoji: string): boolean => {
        const reaction = (msg.reactions ?? []).find((item) => item.emoji === emoji);
        if (!reaction) return false;
        if (reaction.mine) return true;
        return Boolean(auth.user?.id && reaction.user_ids?.includes(auth.user.id));
    };

    const startReply = (msg: ChatMessage) => {
        setMenuMessageId(null);
        setEmojiPickerMessageId(null);
        setEditingMessage(null);
        setReplyingTo(msg);
        inputRef.current?.focus();
    };

    const startForward = (msg: ChatMessage) => {
        setMenuMessageId(null);
        setForwardingMessage(msg);
        setForwardMemberIds([]);
        setForwardTargets([]);

        if (activeConversation?.is_group) {
            return;
        }

        setForwardTargetsLoading(true);
        void chatApi
            .fetchForwardTargets()
            .then((targets) => {
                setForwardTargets(targets);
                if (targets.length === 0) {
                    toast?.error('Join a group chat first to forward messages to members.');
                    setForwardingMessage(null);
                }
            })
            .catch((error) => {
                toast?.error(error instanceof Error ? error.message : 'Could not load forward targets.');
                setForwardingMessage(null);
            })
            .finally(() => setForwardTargetsLoading(false));
    };

    const handleForward = async () => {
        if (!activeConversation || !forwardingMessage || forwardMemberIds.length === 0 || forwarding) return;
        setForwarding(true);
        try {
            const result = await chatApi.forwardChatMessage(
                activeConversation.id,
                forwardingMessage.id,
                forwardMemberIds,
            );
            toast?.success(result.message);
            setForwardingMessage(null);
            setForwardMemberIds([]);
            setForwardTargets([]);
            void refreshConversations();
        } catch (error) {
            toast?.error(error instanceof Error ? error.message : 'Could not forward message.');
        } finally {
            setForwarding(false);
        }
    };

    const startEdit = (msg: ChatMessage) => {
        if (!canEditChatMessage(msg, true)) return;
        setMenuMessageId(null);
        setEmojiPickerMessageId(null);
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
        if (pendingImage) {
            await sendPendingImage();
            return;
        }
        if (!activeConversation || !body.trim() || sending) return;
        setSending(true);
        try {
            if (editingMessage) {
                if (!canEditChatMessage(editingMessage, true)) {
                    toast?.error('You can only edit a message within 2 minutes of sending.');
                    cancelComposerExtras();
                    return;
                }
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

        if (pendingPreview) {
            URL.revokeObjectURL(pendingPreview);
        }
        setPendingImage(file);
        setPendingPreview(URL.createObjectURL(file));
        setViewOnce(false);
    };

    const clearPendingImage = () => {
        if (pendingPreview) {
            URL.revokeObjectURL(pendingPreview);
        }
        setPendingImage(null);
        setPendingPreview(null);
        setViewOnce(false);
    };

    const sendPendingImage = async () => {
        if (!pendingImage || !activeConversation || uploadingImage) return;
        setUploadingImage(true);
        try {
            const message = await chatApi.uploadChatImage(
                activeConversation.id,
                pendingImage,
                body.trim() || undefined,
                viewOnce,
            );
            setMessages((prev) => [...prev, message]);
            lastIdRef.current = Math.max(lastIdRef.current, message.id);
            setBody('');
            clearPendingImage();
            refreshConversations();
            playChatSendSound();
            toast?.success(viewOnce ? 'View once photo sent' : 'Photo sent');
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not send photo');
        } finally {
            setUploadingImage(false);
        }
    };

    const openViewOnceMedia = async (msg: ChatMessage) => {
        if (!activeConversation || msg.view_once_opened || msg.sender_id === auth.user?.id) {
            return;
        }
        try {
            const opened = await chatApi.openViewOnce(activeConversation.id, msg.id);
            replaceMessage(opened.message);
            if (opened.video_url) {
                setViewOnceMedia({ src: opened.video_url, video: true });
            } else if (opened.image_url) {
                setViewOnceMedia({ src: opened.image_url, video: false });
            }
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'This was already opened');
        }
    };

    const handleDocFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file || !activeConversation || uploadingFile) return;

        if (file.size > 20 * 1024 * 1024) {
            toast?.error('File must be 20MB or smaller');
            return;
        }

        setUploadingFile(true);
        try {
            const message = await chatApi.uploadChatFile(activeConversation.id, file);
            setMessages((prev) => [...prev, message]);
            lastIdRef.current = Math.max(lastIdRef.current, message.id);
            refreshConversations();
            playChatSendSound();
            toast?.success('File sent');
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not send file');
        } finally {
            setUploadingFile(false);
        }
    };

    const stopVoiceTimer = () => {
        if (voiceTimerRef.current != null) {
            window.clearInterval(voiceTimerRef.current);
            voiceTimerRef.current = null;
        }
    };

    const cancelVoiceRecording = () => {
        stopVoiceTimer();
        const recorder = mediaRecorderRef.current;
        mediaRecorderRef.current = null;
        voiceChunksRef.current = [];
        if (recorder && recorder.state !== 'inactive') {
            recorder.ondataavailable = null;
            recorder.onstop = null;
            try {
                recorder.stop();
            } catch {
                // ignore
            }
            recorder.stream.getTracks().forEach((t) => t.stop());
        }
        setRecordingVoice(false);
        setVoiceSeconds(0);
    };

    const finishVoiceRecording = async () => {
        const recorder = mediaRecorderRef.current;
        if (!recorder || !activeConversation) {
            cancelVoiceRecording();
            return;
        }

        stopVoiceTimer();
        const startedAt = voiceStartedAtRef.current;
        const mimeType = recorder.mimeType || 'audio/webm';

        const blob = await new Promise<Blob | null>((resolve) => {
            recorder.onstop = () => {
                const chunks = voiceChunksRef.current;
                voiceChunksRef.current = [];
                resolve(chunks.length ? new Blob(chunks, { type: mimeType }) : null);
            };
            try {
                recorder.stop();
            } catch {
                resolve(null);
            }
        });

        recorder.stream.getTracks().forEach((t) => t.stop());
        mediaRecorderRef.current = null;
        setRecordingVoice(false);
        setVoiceSeconds(0);

        if (!blob || blob.size < 200) {
            toast?.error('Voice note was too short');
            return;
        }

        const durationSeconds = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
        setUploadingVoice(true);
        try {
            const message = await chatApi.uploadChatVoice(activeConversation.id, blob, durationSeconds);
            setMessages((prev) => [...prev, message]);
            lastIdRef.current = Math.max(lastIdRef.current, message.id);
            refreshConversations();
            playChatSendSound();
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not send voice note');
        } finally {
            setUploadingVoice(false);
        }
    };

    const startVoiceRecording = async () => {
        if (!activeConversation || recordingVoice || uploadingVoice || uploadingImage || uploadingFile || sending) {
            return;
        }
        if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
            toast?.error('Voice notes are not supported in this browser');
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? 'audio/webm;codecs=opus'
                : MediaRecorder.isTypeSupported('audio/webm')
                  ? 'audio/webm'
                  : MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')
                    ? 'audio/ogg;codecs=opus'
                    : '';
            const recorder = mimeType
                ? new MediaRecorder(stream, { mimeType })
                : new MediaRecorder(stream);
            voiceChunksRef.current = [];
            recorder.ondataavailable = (event) => {
                if (event.data.size > 0) voiceChunksRef.current.push(event.data);
            };
            mediaRecorderRef.current = recorder;
            voiceStartedAtRef.current = Date.now();
            setVoiceSeconds(0);
            setRecordingVoice(true);
            recorder.start(250);
            voiceTimerRef.current = window.setInterval(() => {
                setVoiceSeconds(Math.floor((Date.now() - voiceStartedAtRef.current) / 1000));
            }, 250);
        } catch {
            toast?.error('Microphone permission is required for voice notes');
        }
    };

    useEffect(() => {
        return () => {
            cancelVoiceRecording();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeConversation?.id]);

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
        if (msg.view_once) {
            return msg.view_once_opened ? 'Opened' : msg.type === 'video' ? 'View once video' : 'View once photo';
        }
        if (msg.type === 'image') return msg.body?.trim() || 'Photo';
        if (msg.type === 'video') return msg.body?.trim() || 'Video';
        if (msg.type === 'voice') return 'Voice message';
        if (msg.type === 'product') {
            return msg.product?.name || msg.body?.trim() || 'Product';
        }
        if (msg.type === 'transfer') {
            return msg.body?.trim() || 'Money transfer';
        }
        if (msg.type === 'file') {
            return msg.file_name || msg.body?.trim() || 'File';
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

    const openCityShopPath = (path: string) => {
        if (!path) return;
        closeWidget();
        if (path.startsWith('/products/')) {
            router.visit(route('products.show', path.replace('/products/', '')));
            return;
        }
        if (path.startsWith('/stores/')) {
            router.visit(route('store.show', path.replace('/stores/', '')));
            return;
        }
        router.visit(path);
    };

    const copyChatValue = async (value: string, label: string) => {
        try {
            await navigator.clipboard.writeText(value);
            toast?.success(`${label} copied`);
        } catch {
            toast?.error(`Could not copy ${label.toLowerCase()}`);
        }
    };

    return (
        <div className="relative flex flex-1 flex-col overflow-hidden">
            <audio ref={remoteAudioRef} autoPlay playsInline className="hidden" />

            {showSettings && activeConversation && (
                <ChatSettingsSheet
                    conversationId={activeConversation.id}
                    peerName={otherName}
                    sellerId={complaintSellerId}
                    productId={activeConversation.product?.id}
                    canComplain={canComplain && Boolean(complaintSellerId)}
                    onClose={() => setShowSettings(false)}
                    onDeleted={() => {
                        setShowSettings(false);
                        void showList();
                        void refreshConversations();
                    }}
                />
            )}

            {forwardingMessage && (
                <div className="absolute inset-0 z-30 flex flex-col bg-white">
                    <div className="flex items-center justify-between border-b border-gray-100 px-3 py-3">
                        <p className="font-semibold text-gray-900">
                            {activeConversation?.is_group
                                ? 'Forward to members'
                                : 'Forward to people from your groups'}
                        </p>
                        <button
                            type="button"
                            onClick={() => {
                                setForwardingMessage(null);
                                setForwardMemberIds([]);
                                setForwardTargets([]);
                            }}
                            className="rounded-full p-1 text-gray-400 hover:bg-gray-100"
                            aria-label="Close"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="flex-1 overflow-y-auto p-2">
                        {forwardTargetsLoading ? (
                            <p className="px-2 py-4 text-sm text-gray-500">Loading…</p>
                        ) : (
                            (activeConversation?.is_group
                                ? (activeConversation?.participants ?? [])
                                      .filter((member) => member.id !== auth.user?.id)
                                      .map((member) => ({
                                          id: member.id,
                                          name: member.name,
                                      }))
                                : forwardTargets
                            ).map((member) => {
                                const checked = forwardMemberIds.includes(member.id);
                                return (
                                    <label
                                        key={member.id}
                                        className="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-50"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() =>
                                                setForwardMemberIds((prev) =>
                                                    checked
                                                        ? prev.filter((id) => id !== member.id)
                                                        : [...prev, member.id],
                                                )
                                            }
                                            className="h-4 w-4 rounded border-gray-300 text-orange-500"
                                        />
                                        <span className="text-sm font-medium text-gray-900">{member.name}</span>
                                    </label>
                                );
                            })
                        )}
                    </div>
                    <div className="border-t border-gray-100 p-3">
                        <button
                            type="button"
                            disabled={forwardMemberIds.length === 0 || forwarding || forwardTargetsLoading}
                            onClick={() => void handleForward()}
                            className="w-full rounded-lg bg-orange-500 py-2.5 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50"
                        >
                            {forwardMemberIds.length === 0
                                ? 'Choose members'
                                : `Send to ${forwardMemberIds.length} ${forwardMemberIds.length === 1 ? 'member' : 'members'}`}
                        </button>
                    </div>
                </div>
            )}

            <div className="flex items-center gap-2 border-b border-[#005C4B] bg-[#008069] px-3 py-2.5 text-white">
                <button type="button" onClick={showList} className="rounded-lg p-1.5 hover:bg-white/10">
                    <ArrowLeft className="h-4 w-4 text-white" />
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
                            className="block w-full truncate text-left text-sm font-semibold text-white hover:text-emerald-100"
                            title="View store"
                        >
                            {otherName}
                        </button>
                    ) : (
                        <p className="truncate text-sm font-semibold text-white">{otherName}</p>
                    )}
                    <div className="flex items-center gap-1.5">
                        {other && (
                            <OnlineIndicator
                                online={other.online}
                                lastSeenAt={other.last_seen_at}
                                isGroup={activeConversation?.is_group || other.is_group}
                                onlineCount={other.online_count}
                                size="sm"
                            />
                        )}
                        {location && (
                            <span className="flex items-center gap-0.5 truncate text-[10px] text-emerald-100">
                                <MapPin className="h-2.5 w-2.5" />
                                {location}
                            </span>
                        )}
                    </div>
                </div>
                {callState === 'idle' ? (
                    <div className="flex items-center gap-0.5">
                        {storeSlug && !isGroup && (
                            <button
                                type="button"
                                onClick={openStore}
                                className="rounded-lg p-1.5 text-white hover:bg-white/10"
                                title="View store"
                            >
                                <Store className="h-4 w-4" />
                            </button>
                        )}
                        {!isGroup && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => handleStartCall('voice')}
                                    className="rounded-lg p-1.5 text-white hover:bg-white/10"
                                    title="Audio call"
                                >
                                    <Phone className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStartCall('video')}
                                    className="rounded-lg p-1.5 text-white hover:bg-white/10"
                                    title="Video call"
                                >
                                    <Video className="h-4 w-4" />
                                </button>
                            </>
                        )}
                        <button
                            type="button"
                            onClick={() => setShowSettings(true)}
                            className="rounded-lg p-1.5 text-white hover:bg-white/10"
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
                                      ? 'cancelled'
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
                className="flex-1 overflow-y-auto px-3 py-3"
                style={{
                    backgroundColor: '#ECE5DD',
                    backgroundImage:
                        'radial-gradient(#d5ccc4 0.7px, transparent 0.7px), radial-gradient(#d5ccc4 0.7px, transparent 0.7px)',
                    backgroundSize: '28px 28px',
                    backgroundPosition: '0 0, 14px 14px',
                }}
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
                        const isViewOnce = Boolean(msg.view_once) && (msg.type === 'image' || msg.type === 'video') && !msg.is_deleted;
                        const isImage = msg.type === 'image' && msg.image_url && !msg.is_deleted && !msg.view_once;
                        const isVideo = msg.type === 'video' && msg.video_url && !msg.is_deleted && !msg.view_once;
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
                        const transferCard =
                            msg.type === 'transfer' && !msg.is_deleted
                                ? (msg.transfer ??
                                  (msg.metadata?.transfer as ChatMessage['transfer'] | undefined) ??
                                  null)
                                : null;
                        const isTransfer = msg.type === 'transfer' && !msg.is_deleted;
                        const fileUrl =
                            msg.type === 'file' && !msg.is_deleted
                                ? msg.file_url ||
                                  (typeof msg.metadata?.file_url === 'string' ? msg.metadata.file_url : null)
                                : null;
                        const isFile = msg.type === 'file' && !msg.is_deleted && !!fileUrl;

                        return (
                            <div
                                key={msg.id}
                                className={cn('group mb-2 flex items-end gap-1', mine ? 'justify-end' : 'justify-start')}
                            >
                                <div className={cn('relative max-w-[85%]', mine ? 'order-1' : 'order-2')}>
                                    <div
                                        className={cn(
                                            'overflow-hidden rounded-2xl text-sm',
                                            isImage || isVideo || isViewOnce
                                                ? 'p-1'
                                                : isProduct || isTransfer || isFile
                                                  ? 'p-0'
                                                  : 'px-3 py-2',
                                            isTransfer
                                                ? 'border border-green-100 bg-white text-gray-900 shadow-sm'
                                                : isFile
                                                  ? 'border border-blue-100 bg-white text-gray-900 shadow-sm'
                                                  : isProduct
                                                    ? 'border border-orange-100 bg-white text-gray-900 shadow-sm'
                                                    : mine
                                                      ? 'bg-[#DCF8C6] text-[#111B21] shadow-sm'
                                                      : 'bg-white text-[#111B21] shadow-sm',
                                            msg.is_deleted && 'px-3 py-2 italic opacity-70',
                                        )}
                                    >
                                        {msg.reply_to && !msg.is_deleted && (
                                            <div
                                                className={cn(
                                                    'mb-1.5 rounded-lg border-l-2 px-2 py-1 text-[11px]',
                                                    mine
                                                        ? 'border-[#06CF9C] bg-[#c9e9b6] text-[#111B21]'
                                                        : 'border-sky-400 bg-sky-50 text-gray-600',
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
                                        ) : isFile && fileUrl ? (
                                            <ChatFileBubble
                                                url={fileUrl}
                                                name={msg.file_name || msg.body}
                                                size={msg.file_size}
                                            />
                                        ) : isTransfer ? (
                                            transferCard ? (
                                                <ChatTransferBubble
                                                    transfer={transferCard}
                                                    mine={mine}
                                                    createdAt={msg.created_at}
                                                />
                                            ) : (
                                                <div className="min-w-[14rem] p-3">
                                                    <p className="text-sm font-semibold text-gray-900">
                                                        {msg.body || 'Money transfer'}
                                                    </p>
                                                    <p className="mt-1 text-xs font-bold text-orange-600">View receipt</p>
                                                </div>
                                            )
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
                                        ) : isViewOnce ? (
                                            <button
                                                type="button"
                                                onClick={() => void openViewOnceMedia(msg)}
                                                className="flex w-52 items-center gap-2.5 rounded-xl bg-[#111B21] px-3 py-3 text-left text-white"
                                            >
                                                <span className="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white/70 text-sm font-black">
                                                    {msg.view_once_opened ? '✓' : '1'}
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-sm font-semibold">
                                                        {msg.view_once_opened
                                                            ? 'Opened'
                                                            : msg.type === 'video'
                                                              ? 'View once video'
                                                              : 'View once photo'}
                                                    </span>
                                                    {!msg.view_once_opened && msg.sender_id !== auth.user?.id && (
                                                        <span className="text-[11px] text-white/70">Tap to open</span>
                                                    )}
                                                </span>
                                            </button>
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
                                                    <div className="px-2 py-1.5 text-sm">
                                                        <ChatLinkedText
                                                            text={msg.body}
                                                            mine={mine}
                                                            onOpenCityShop={openCityShopPath}
                                                            onCopy={copyChatValue}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        ) : isVideo ? (
                                            <ChatVideoBubble src={msg.video_url!} caption={msg.body} />
                                        ) : isVoice ? (
                                            <ChatVoiceBubble
                                                src={msg.voice_url!}
                                                durationSeconds={msg.duration_seconds}
                                                mine={mine}
                                            />
                                        ) : (
                                            <ChatLinkedText
                                                text={msg.body ?? ''}
                                                mine={mine}
                                                onOpenCityShop={openCityShopPath}
                                                onCopy={copyChatValue}
                                            />
                                        )}

                                        <div
                                            className={cn(
                                                'flex items-center gap-1.5 text-[10px]',
                                                isImage || isVideo || isProduct || isTransfer || isFile
                                                    ? 'px-2 pb-1.5'
                                                    : 'mt-0.5',
                                                isProduct || isTransfer || isFile || !mine
                                                    ? 'text-gray-400'
                                                    : 'text-[#667781]',
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
                                                            ? isProduct || isTransfer || isFile || !mine
                                                            ? 'text-sky-500'
                                                            : 'text-[#53BDEB]'
                                                            : isProduct || isTransfer || isFile || !mine
                                                              ? 'text-gray-400'
                                                              : 'text-[#8696A0]',
                                                    )}
                                                    title={msg.read_at ? 'Read' : 'Sent'}
                                                >
                                                    {msg.read_at ? '✓✓' : '✓'}
                                                </span>
                                            )}
                                        </div>
                                        {(msg.reactions?.length ?? 0) > 0 && !msg.is_deleted && (
                                            <div className={cn('flex flex-wrap gap-1 pb-1', isImage || isVideo || isProduct || isTransfer || isFile ? 'px-2' : '')}>
                                                {msg.reactions!.map((reaction) => (
                                                    <button
                                                        key={reaction.emoji}
                                                        type="button"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            void handleReact(msg, reaction.emoji);
                                                        }}
                                                        className={cn(
                                                            'inline-flex items-center gap-1 rounded-full border px-1.5 py-0.5 text-[11px]',
                                                            reactionIsMine(msg, reaction.emoji)
                                                                ? 'border-orange-300 bg-orange-50 text-orange-800'
                                                                : 'border-gray-200 bg-white text-gray-700',
                                                        )}
                                                    >
                                                        <span>{reaction.emoji}</span>
                                                        <span>{reaction.count}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {(showMenu || emojiPickerMessageId === msg.id) &&
                                        ['text', 'image', 'video', 'voice', 'product', 'file', 'transfer'].includes(msg.type) &&
                                        !msg.is_deleted && (
                                        <div
                                            className={cn(
                                                'absolute z-20 mt-1 flex flex-col gap-1',
                                                mine ? 'right-0 items-end' : 'left-0 items-start',
                                            )}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <ChatQuickReactions
                                                onPick={(emoji) => void handleReact(msg, emoji)}
                                                onMore={() =>
                                                    setEmojiPickerMessageId((current) => (current === msg.id ? null : msg.id))
                                                }
                                            />
                                            {emojiPickerMessageId === msg.id && (
                                                <ChatEmojiPicker onPick={(emoji) => void handleReact(msg, emoji)} />
                                            )}
                                            {showMenu && (
                                        <div
                                            className="min-w-[7rem] overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => startReply(msg)}
                                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                                            >
                                                <CornerUpLeft className="h-3.5 w-3.5" />
                                                Reply
                                            </button>
                                            {!msg.view_once && (
                                            <button
                                                type="button"
                                                onClick={() => startForward(msg)}
                                                className="flex w-full flex-col items-start gap-0 px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50"
                                            >
                                                <span className="inline-flex items-center gap-2">
                                                    <Forward className="h-3.5 w-3.5" />
                                                    Forward to members
                                                </span>
                                                {!isGroup && (
                                                    <span className="pl-5 text-[10px] font-normal text-gray-400">
                                                        People from your groups
                                                    </span>
                                                )}
                                            </button>
                                            )}
                                            {mine && canEditChatMessage(msg, mine) && (
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
                                    )}
                                </div>

                                {!msg.is_deleted && (
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setEmojiPickerMessageId(null);
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

            <div className="border-t border-[#d5ccc4] bg-[#F0EFEA] pb-[env(safe-area-inset-bottom,0px)]">
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

                {pendingImage && (
                    <div className="flex items-center gap-2 border-t border-gray-200 bg-white px-3 py-2">
                        {pendingPreview && (
                            <img src={pendingPreview} alt="" className="h-12 w-12 rounded-lg object-cover" />
                        )}
                        <p className="min-w-0 flex-1 truncate text-xs text-gray-500">
                            {viewOnce ? 'View once photo ready' : 'Photo ready — add a caption if you want'}
                        </p>
                        <button
                            type="button"
                            onClick={clearPendingImage}
                            className="text-xs text-gray-500 underline"
                        >
                            Remove
                        </button>
                    </div>
                )}

                <form onSubmit={sendMessage} className="flex min-w-0 items-center gap-1 bg-[#F0EFEA] p-2.5 sm:gap-1.5 sm:p-3">
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                        className="hidden"
                        onChange={handleImageSelect}
                    />
                    <input
                        ref={docFileInputRef}
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.rtf,.odt,.ods"
                        className="hidden"
                        onChange={handleDocFileSelect}
                    />
                    <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={uploadingImage || uploadingFile || uploadingVoice || sending || recordingVoice}
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-orange-500 disabled:opacity-50 sm:h-9 sm:w-9"
                        title="Send photo"
                    >
                        <ImagePlus className="h-5 w-5 sm:h-4 sm:w-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => docFileInputRef.current?.click()}
                        disabled={uploadingImage || uploadingFile || uploadingVoice || sending || recordingVoice}
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-orange-500 disabled:opacity-50 sm:h-9 sm:w-9"
                        title="Send file"
                    >
                        <FilePlus className="h-5 w-5 sm:h-4 sm:w-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => (recordingVoice ? void finishVoiceRecording() : void startVoiceRecording())}
                        disabled={uploadingImage || uploadingFile || uploadingVoice || sending || !activeConversation}
                        className={cn(
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full disabled:opacity-50 sm:h-9 sm:w-9',
                            recordingVoice
                                ? 'bg-red-500 text-white hover:bg-red-600'
                                : 'text-gray-500 hover:bg-gray-100 hover:text-orange-500',
                        )}
                        title={recordingVoice ? 'Send voice note' : 'Record voice note'}
                    >
                        {recordingVoice ? <Square className="h-4 w-4 sm:h-3.5 sm:w-3.5" /> : <Mic className="h-5 w-5 sm:h-4 sm:w-4" />}
                    </button>
                    {!isGroup && (
                        <button
                            type="button"
                            onClick={() => setShowTransfer(true)}
                            disabled={uploadingImage || uploadingFile || uploadingVoice || sending || recordingVoice || !activeConversation}
                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-emerald-600 disabled:opacity-50 sm:h-9 sm:w-9"
                            title="Transfer money"
                        >
                            <ArrowLeftRight className="h-5 w-5 sm:h-4 sm:w-4" />
                        </button>
                    )}
                    {recordingVoice ? (
                        <div className="flex min-h-10 min-w-0 flex-1 items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
                            <span className="h-2 w-2 animate-pulse rounded-full bg-red-500" />
                            Recording {Math.floor(voiceSeconds / 60)}:{String(voiceSeconds % 60).padStart(2, '0')}
                            <button
                                type="button"
                                onClick={cancelVoiceRecording}
                                className="ml-auto text-xs font-medium text-red-600 underline"
                            >
                                Cancel
                            </button>
                        </div>
                    ) : (
                        <div className="flex min-w-0 flex-1 items-center gap-1.5">
                            {pendingImage && (
                                <button
                                    type="button"
                                    onClick={() => setViewOnce((v) => !v)}
                                    className={cn(
                                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-sm font-black',
                                        viewOnce
                                            ? 'border-orange-500 bg-orange-500 text-white'
                                            : 'border-gray-400 text-gray-600',
                                    )}
                                    title="View once"
                                    aria-label="View once"
                                >
                                    1
                                </button>
                            )}
                            <input
                                ref={inputRef}
                                type="text"
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                                placeholder={
                                    pendingImage
                                        ? 'Add a caption…'
                                        : uploadingImage
                                        ? 'Uploading photo...'
                                        : uploadingFile
                                          ? 'Uploading file...'
                                          : uploadingVoice
                                            ? 'Sending voice note...'
                                            : editingMessage
                                              ? 'Edit your message...'
                                              : replyingTo
                                                ? 'Write a reply...'
                                                : 'Type a message...'
                                }
                                className="min-h-10 min-w-0 flex-1 rounded-full border border-gray-200 px-3 py-2 text-base focus:border-orange-300 focus:outline-none focus:ring-1 focus:ring-orange-300 sm:min-h-0 sm:px-4 sm:py-2 sm:text-sm"
                                maxLength={2000}
                                disabled={uploadingImage || uploadingFile || uploadingVoice}
                            />
                            <button
                                type="submit"
                                disabled={
                                    pendingImage
                                        ? uploadingImage
                                        : !body.trim() || sending || uploadingImage || uploadingFile || uploadingVoice
                                }
                                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white hover:bg-[#1ebe5d] disabled:bg-[#8ee2b0] disabled:opacity-100 sm:h-9 sm:w-9"
                                title="Send"
                                aria-label="Send"
                            >
                                <Send className="h-5 w-5 sm:h-4 sm:w-4" />
                            </button>
                        </div>
                    )}
                    {recordingVoice && (
                        <button
                            type="button"
                            onClick={() => void finishVoiceRecording()}
                            disabled={uploadingVoice}
                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 disabled:opacity-50 sm:h-9 sm:w-9"
                            title="Send voice note"
                            aria-label="Send voice note"
                        >
                            <Send className="h-5 w-5 sm:h-4 sm:w-4" />
                        </button>
                    )}
                </form>
            </div>

            {!isGroup && (
                <ChatTransferSheet
                    open={showTransfer}
                    conversationId={activeConversation?.id ?? null}
                    fallbackRecipientName={otherName}
                    fallbackRecipientAvatar={other?.avatar}
                    onOpenChange={setShowTransfer}
                    onSent={(message) => {
                        setMessages((prev) => {
                            if (prev.some((m) => m.id === message.id)) return prev;
                            return [...prev, message];
                        });
                        lastIdRef.current = Math.max(lastIdRef.current, message.id);
                        playChatSendSound();
                        refreshConversations();
                    }}
                />
            )}
            {viewOnceMedia && (
                <div
                    className="fixed inset-0 z-[130] flex items-center justify-center bg-black/90 p-4"
                    onClick={() => setViewOnceMedia(null)}
                >
                    {viewOnceMedia.video ? (
                        <video
                            src={viewOnceMedia.src}
                            controls
                            autoPlay
                            className="max-h-[90vh] max-w-full"
                            onClick={(e) => e.stopPropagation()}
                        />
                    ) : (
                        <img
                            src={viewOnceMedia.src}
                            alt="View once"
                            className="max-h-[90vh] max-w-full object-contain"
                            onClick={(e) => e.stopPropagation()}
                        />
                    )}
                </div>
            )}
        </div>
    );
}
