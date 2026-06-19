import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, MapPin, MessageCircle, Phone, PhoneOff, Send, Store } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

import OnlineIndicator from '@/components/shop/online-indicator';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

interface ChatMessage {
    id: number;
    sender_id: number;
    type: string;
    body: string;
    metadata?: Record<string, unknown> | null;
    created_at?: string;
    sender: { id: number; name: string };
}

interface ConversationData {
    id: number;
    product?: { id: number; name: string; slug: string } | null;
    other: {
        id: number;
        name: string;
        online: boolean;
        last_seen_at?: string;
        city?: string;
        region?: string;
        seller_profile?: {
            business_name?: string;
            store_name?: string;
            slug?: string;
            business_address?: string;
        } | null;
    };
}

interface ChatShowProps {
    conversation: ConversationData;
    messages: ChatMessage[];
}

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export default function ChatShow({ conversation, messages: initialMessages }: ChatShowProps) {
    const { auth } = usePage<SharedData>().props;
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [other, setOther] = useState(conversation.other);
    const [callState, setCallState] = useState<'idle' | 'calling' | 'incoming' | 'active'>('idle');
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const lastIdRef = useRef(initialMessages.at(-1)?.id ?? 0);
    const pcRef = useRef<RTCPeerConnection | null>(null);
    const localStreamRef = useRef<MediaStream | null>(null);
    const remoteAudioRef = useRef<HTMLAudioElement>(null);
    const processedCallIds = useRef<Set<number>>(new Set());

    const otherName = other.seller_profile?.business_name ?? other.seller_profile?.store_name ?? other.name;
    const location = [other.city, other.region].filter(Boolean).join(', ');

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    const sendSignal = useCallback(async (type: string, body = '', metadata?: Record<string, unknown>) => {
        await fetch(route('chat.signal', conversation.id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ type, body, metadata }),
        });
    }, [conversation.id]);

    const endCall = useCallback(async () => {
        if (callState !== 'idle') {
            await sendSignal('call_end');
        }
        pcRef.current?.close();
        pcRef.current = null;
        localStreamRef.current?.getTracks().forEach((t) => t.stop());
        localStreamRef.current = null;
        if (remoteAudioRef.current) remoteAudioRef.current.srcObject = null;
        setCallState('idle');
    }, [callState, sendSignal]);

    const handleCallSignal = useCallback(async (msg: ChatMessage) => {
        if (processedCallIds.current.has(msg.id)) return;
        processedCallIds.current.add(msg.id);

        if (msg.type === 'call_end') {
            await endCall();
            return;
        }

        if (msg.type === 'call_offer' && msg.sender_id !== auth.user.id) {
            setCallState('incoming');
            return;
        }

        if (!pcRef.current) return;

        if (msg.type === 'call_offer' && msg.metadata?.sdp) {
            await pcRef.current.setRemoteDescription(new RTCSessionDescription(msg.metadata.sdp as RTCSessionDescriptionInit));
            const answer = await pcRef.current.createAnswer();
            await pcRef.current.setLocalDescription(answer);
            await sendSignal('call_answer', '', { sdp: answer });
            setCallState('active');
        }

        if (msg.type === 'call_answer' && msg.metadata?.sdp) {
            await pcRef.current.setRemoteDescription(new RTCSessionDescription(msg.metadata.sdp as RTCSessionDescriptionInit));
            setCallState('active');
        }

        if (msg.type === 'call_ice' && msg.metadata?.candidate) {
            try {
                await pcRef.current.addIceCandidate(new RTCIceCandidate(msg.metadata.candidate as RTCIceCandidateInit));
            } catch {
                // ignore stale candidates
            }
        }
    }, [auth.user.id, endCall, sendSignal]);

    useEffect(() => {
        const poll = async () => {
            try {
                const res = await fetch(route('chat.poll', { conversation: conversation.id, after: lastIdRef.current }), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (data.other) setOther(data.other);
                if (data.messages?.length) {
                    setMessages((prev) => {
                        const ids = new Set(prev.map((m) => m.id));
                        const added = data.messages.filter((m: ChatMessage) => !ids.has(m.id));
                        return [...prev, ...added];
                    });
                    for (const msg of data.messages as ChatMessage[]) {
                        lastIdRef.current = Math.max(lastIdRef.current, msg.id);
                        if (msg.type.startsWith('call')) {
                            await handleCallSignal(msg);
                        }
                    }
                }
            } catch {
                // silent poll failure
            }
        };

        const interval = setInterval(poll, 2000);
        return () => clearInterval(interval);
    }, [conversation.id, handleCallSignal]);

    const sendMessage = async (e: FormEvent) => {
        e.preventDefault();
        if (!body.trim() || sending) return;
        setSending(true);
        try {
            const res = await fetch(route('chat.messages.store', conversation.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ body: body.trim() }),
            });
            const data = await res.json();
            if (data.message) {
                setMessages((prev) => [...prev, data.message]);
                lastIdRef.current = Math.max(lastIdRef.current, data.message.id);
                setBody('');
            }
        } finally {
            setSending(false);
        }
    };

    const startCall = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            localStreamRef.current = stream;
            const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
            pcRef.current = pc;
            stream.getTracks().forEach((track) => pc.addTrack(track, stream));
            pc.ontrack = (e) => {
                if (remoteAudioRef.current) remoteAudioRef.current.srcObject = e.streams[0];
            };
            pc.onicecandidate = (e) => {
                if (e.candidate) {
                    sendSignal('call_ice', '', { candidate: e.candidate.toJSON() });
                }
            };
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await sendSignal('call_offer', 'Voice call', { sdp: offer });
            setCallState('calling');
        } catch {
            alert('Could not access microphone. Please allow microphone access to make calls.');
        }
    };

    const acceptCall = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            localStreamRef.current = stream;
            const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
            pcRef.current = pc;
            stream.getTracks().forEach((track) => pc.addTrack(track, stream));
            pc.ontrack = (e) => {
                if (remoteAudioRef.current) remoteAudioRef.current.srcObject = e.streams[0];
            };
            pc.onicecandidate = (e) => {
                if (e.candidate) {
                    sendSignal('call_ice', '', { candidate: e.candidate.toJSON() });
                }
            };
            setCallState('active');
        } catch {
            alert('Could not access microphone.');
        }
    };

    return (
        <ShopLayout>
            <Head title={`Chat with ${otherName}`} />
            <audio ref={remoteAudioRef} autoPlay playsInline />

            <div className="mx-auto flex h-[calc(100vh-8rem)] max-w-3xl flex-col px-4 py-4">
                {/* Header */}
                <div className="flex items-center gap-3 rounded-t-2xl border border-b-0 border-gray-100 bg-white px-4 py-3 shadow-sm">
                    <Link href={route('chat.index')} className="rounded-lg p-1.5 hover:bg-gray-100">
                        <ArrowLeft className="h-5 w-5 text-gray-600" />
                    </Link>
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-orange-500 font-bold text-white">
                        {otherName.charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold text-gray-900">{otherName}</p>
                        <div className="flex items-center gap-2">
                            <OnlineIndicator online={other.online} />
                            {location && (
                                <span className="flex items-center gap-0.5 text-xs text-gray-400">
                                    <MapPin className="h-3 w-3" />
                                    {location}
                                </span>
                            )}
                        </div>
                    </div>
                    {callState === 'idle' ? (
                        <button type="button" onClick={startCall} className="rounded-lg p-2 text-green-600 hover:bg-green-50" title="Voice call">
                            <Phone className="h-5 w-5" />
                        </button>
                    ) : (
                        <button type="button" onClick={endCall} className="rounded-lg p-2 text-red-600 hover:bg-red-50" title="End call">
                            <PhoneOff className="h-5 w-5" />
                        </button>
                    )}
                </div>

                {/* Location / product context */}
                {(other.seller_profile?.business_address || conversation.product) && (
                    <div className="border-x border-gray-100 bg-gray-50 px-4 py-2 text-xs text-gray-500">
                        {other.seller_profile?.business_address && (
                            <p className="flex items-center gap-1">
                                <Store className="h-3 w-3" />
                                {other.seller_profile.business_address}
                            </p>
                        )}
                        {conversation.product && (
                            <Link href={route('products.show', conversation.product.slug)} className="mt-0.5 block text-orange-500 hover:underline">
                                Re: {conversation.product.name}
                            </Link>
                        )}
                    </div>
                )}

                {/* Call overlay */}
                {(callState === 'calling' || callState === 'incoming' || callState === 'active') && (
                    <div className="border-x border-gray-100 bg-green-50 px-4 py-3 text-center">
                        {callState === 'calling' && <p className="text-sm font-medium text-green-700">Calling {otherName}...</p>}
                        {callState === 'incoming' && (
                            <div className="flex items-center justify-center gap-3">
                                <p className="text-sm font-medium text-green-700">{otherName} is calling</p>
                                <button type="button" onClick={acceptCall} className="rounded-full bg-green-500 px-4 py-1.5 text-sm text-white hover:bg-green-600">
                                    Accept
                                </button>
                                <button type="button" onClick={endCall} className="rounded-full bg-red-500 px-4 py-1.5 text-sm text-white hover:bg-red-600">
                                    Decline
                                </button>
                            </div>
                        )}
                        {callState === 'active' && <p className="text-sm font-medium text-green-700">Call in progress</p>}
                    </div>
                )}

                {/* Messages */}
                <div className="flex-1 overflow-y-auto border-x border-gray-100 bg-white px-4 py-4">
                    {messages.filter((m) => m.type === 'text').length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center text-center text-gray-400">
                            <MessageCircle className="h-10 w-10" />
                            <p className="mt-2 text-sm">Start the conversation with {otherName}</p>
                        </div>
                    ) : (
                        messages
                            .filter((m) => m.type === 'text')
                            .map((msg) => {
                                const mine = msg.sender_id === auth.user.id;
                                return (
                                    <div key={msg.id} className={`mb-3 flex ${mine ? 'justify-end' : 'justify-start'}`}>
                                        <div
                                            className={`max-w-[75%] rounded-2xl px-4 py-2 text-sm ${
                                                mine ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-900'
                                            }`}
                                        >
                                            <p>{msg.body}</p>
                                            {msg.created_at && (
                                                <p className={`mt-1 text-[10px] ${mine ? 'text-orange-100' : 'text-gray-400'}`}>
                                                    {new Date(msg.created_at).toLocaleTimeString('en-GH', { hour: '2-digit', minute: '2-digit' })}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                    )}
                    <div ref={messagesEndRef} />
                </div>

                {/* Input */}
                <form onSubmit={sendMessage} className="flex items-center gap-2 rounded-b-2xl border border-t-0 border-gray-100 bg-white px-4 py-3 shadow-sm">
                    <input
                        type="text"
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                        placeholder="Type a message..."
                        className="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-orange-300 focus:outline-none focus:ring-1 focus:ring-orange-300"
                        maxLength={2000}
                    />
                    <button
                        type="submit"
                        disabled={!body.trim() || sending}
                        className="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500 text-white transition-colors hover:bg-orange-600 disabled:opacity-50"
                    >
                        <Send className="h-4 w-4" />
                    </button>
                </form>
            </div>
        </ShopLayout>
    );
}
