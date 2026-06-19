import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, MapPin, MessageCircle, Send } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

import OnlineIndicator from '@/components/shop/online-indicator';
import { useChat } from '@/contexts/chat-context';
import { useToastOptional } from '@/contexts/toast-context';
import * as chatApi from '@/lib/chat-api';
import type { ChatMessage } from '@/types/chat';
import { SharedData } from '@/types';

export default function ChatThreadPanel() {
    const { auth } = usePage<SharedData>().props;
    const { activeConversation, messages, setMessages, setActiveConversation, showList, loading, refreshConversations } = useChat();
    const toast = useToastOptional();
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [other, setOther] = useState(activeConversation?.other);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const lastIdRef = useRef(messages.at(-1)?.id ?? 0);

    const otherName =
        other?.seller_profile?.business_name ?? other?.seller_profile?.store_name ?? other?.name ?? 'Chat';
    const location = [other?.city, other?.region].filter(Boolean).join(', ');

    useEffect(() => {
        setOther(activeConversation?.other);
        lastIdRef.current = messages.at(-1)?.id ?? 0;
    }, [activeConversation, messages]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

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
    }, [activeConversation, setActiveConversation, setMessages]);

    const sendMessage = async (e: FormEvent) => {
        e.preventDefault();
        if (!activeConversation || !body.trim() || sending) return;
        setSending(true);
        try {
            const message = await chatApi.sendChatMessage(activeConversation.id, body.trim());
            setMessages((prev) => [...prev, message]);
            lastIdRef.current = Math.max(lastIdRef.current, message.id);
            setBody('');
            refreshConversations();
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not send message');
        } finally {
            setSending(false);
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

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            <div className="flex items-center gap-2 border-b border-gray-100 bg-white px-3 py-2.5">
                <button type="button" onClick={showList} className="rounded-lg p-1.5 hover:bg-gray-100">
                    <ArrowLeft className="h-4 w-4 text-gray-600" />
                </button>
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-orange-500 text-sm font-bold text-white">
                    {otherName.charAt(0).toUpperCase()}
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
            </div>

            {activeConversation.product && (
                <div className="border-b border-gray-50 bg-orange-50/50 px-3 py-1.5 text-[11px]">
                    <Link href={route('products.show', activeConversation.product.slug)} className="text-orange-600 hover:underline">
                        Re: {activeConversation.product.name}
                    </Link>
                </div>
            )}

            <div className="flex-1 overflow-y-auto bg-gray-50 px-3 py-3">
                {messages.filter((m: ChatMessage) => m.type === 'text').length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center text-center text-gray-400">
                        <p className="text-xs">Say hi to {otherName}</p>
                    </div>
                ) : (
                    messages
                        .filter((m) => m.type === 'text')
                        .map((msg) => {
                            const mine = msg.sender_id === auth.user?.id;
                            return (
                                <div key={msg.id} className={`mb-2 flex ${mine ? 'justify-end' : 'justify-start'}`}>
                                    <div
                                        className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm ${
                                            mine ? 'bg-orange-500 text-white' : 'bg-white text-gray-900 shadow-sm'
                                        }`}
                                    >
                                        <p>{msg.body}</p>
                                        {msg.created_at && (
                                            <p className={`mt-0.5 text-[10px] ${mine ? 'text-orange-100' : 'text-gray-400'}`}>
                                                {new Date(msg.created_at).toLocaleTimeString('en-GH', {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            );
                        })
                )}
                <div ref={messagesEndRef} />
            </div>

            <form onSubmit={sendMessage} className="flex items-center gap-2 border-t border-gray-100 bg-white p-3">
                <input
                    type="text"
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    placeholder="Type a message..."
                    className="flex-1 rounded-full border border-gray-200 px-4 py-2 text-sm focus:border-orange-300 focus:outline-none focus:ring-1 focus:ring-orange-300"
                    maxLength={2000}
                />
                <button
                    type="submit"
                    disabled={!body.trim() || sending}
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50"
                >
                    <Send className="h-4 w-4" />
                </button>
            </form>
        </div>
    );
}
