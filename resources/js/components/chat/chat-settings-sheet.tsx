import { Eraser, Flag, Search, Trash2, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
import type { ChatClearRequest } from '@/lib/chat-api';
import type { ChatMessage } from '@/types/chat';

const reasons = [
    { value: 'scam', label: 'Scam or fraud' },
    { value: 'counterfeit', label: 'Counterfeit or fake products' },
    { value: 'harassment', label: 'Harassment or abuse' },
    { value: 'poor_service', label: 'Poor service or unresponsive seller' },
    { value: 'prohibited_items', label: 'Prohibited or illegal items' },
    { value: 'fake_listings', label: 'Misleading or fake listings' },
    { value: 'other', label: 'Other' },
];

type Panel = 'menu' | 'search' | 'complaint' | 'clear';

interface ChatSettingsSheetProps {
    conversationId: number;
    peerName: string;
    sellerId?: number | null;
    productId?: number | null;
    canComplain: boolean;
    isGroup?: boolean;
    clearRequest?: ChatClearRequest | null;
    onClose: () => void;
    onDeleted: () => void;
    onCleared: (messages: ChatMessage[], clearRequest?: ChatClearRequest | null) => void;
    onClearRequestChange?: (clearRequest: ChatClearRequest | null) => void;
}

export default function ChatSettingsSheet({
    conversationId,
    peerName,
    sellerId,
    productId,
    canComplain,
    isGroup = false,
    clearRequest = null,
    onClose,
    onDeleted,
    onCleared,
    onClearRequestChange,
}: ChatSettingsSheetProps) {
    const [panel, setPanel] = useState<Panel>('menu');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ChatMessage[]>([]);
    const [searching, setSearching] = useState(false);
    const [reason, setReason] = useState('scam');
    const [details, setDetails] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const title = useMemo(() => {
        if (panel === 'search') return 'Search chat history';
        if (panel === 'complaint') return 'Make a complaint';
        if (panel === 'clear') return 'Clear chat history';
        return 'Chat Settings';
    }, [panel]);

    const runSearch = async () => {
        const q = query.trim();
        if (!q) {
            setResults([]);
            return;
        }
        setSearching(true);
        setError(null);
        try {
            setResults(await chatApi.searchMessages(conversationId, q));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Search failed');
        } finally {
            setSearching(false);
        }
    };

    const deleteChat = async () => {
        if (!window.confirm(`Delete chat with ${peerName} from your inbox?`)) return;
        setBusy(true);
        setError(null);
        try {
            await chatApi.deleteConversation(conversationId);
            onDeleted();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not delete chat');
        } finally {
            setBusy(false);
        }
    };

    const clearForMe = async () => {
        if (
            !window.confirm(
                `Clear your chat history with ${peerName}? They will still see the messages on their side.`,
            )
        ) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            const data = await chatApi.clearChatHistory(conversationId);
            onCleared(data.messages ?? [], data.clear_request ?? null);
            onClose();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not clear chat');
        } finally {
            setBusy(false);
        }
    };

    const requestBoth = async () => {
        if (
            !window.confirm(
                `Ask ${peerName} to clear this chat for both of you? Nothing is removed until they accept.`,
            )
        ) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            const data = await chatApi.requestClearBoth(conversationId);
            onClearRequestChange?.(data.clear_request ?? null);
            setPanel('menu');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not send clear request');
        } finally {
            setBusy(false);
        }
    };

    const respondBoth = async (accept: boolean) => {
        if (!clearRequest) return;
        setBusy(true);
        setError(null);
        try {
            const data = await chatApi.respondClearBoth(conversationId, clearRequest.id, accept);
            if (accept && data.messages) {
                onCleared(data.messages, data.clear_request ?? null);
                onClose();
            } else {
                onClearRequestChange?.(data.clear_request ?? null);
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not respond');
        } finally {
            setBusy(false);
        }
    };

    const submitComplaint = async (e: FormEvent) => {
        e.preventDefault();
        if (!sellerId) return;
        setBusy(true);
        setError(null);
        try {
            await chatApi.reportSeller({
                seller_id: sellerId,
                reason,
                details: details.trim() || undefined,
                product_id: productId ?? undefined,
            });
            onClose();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Could not submit complaint');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="absolute inset-0 z-20 flex flex-col bg-white">
            <div className="flex items-center justify-between border-b border-gray-100 px-3 py-2.5">
                <p className="text-sm font-semibold text-gray-900">{title}</p>
                <button type="button" onClick={onClose} className="rounded-lg p-1.5 hover:bg-gray-100" aria-label="Close">
                    <X className="h-4 w-4 text-gray-500" />
                </button>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto p-3">
                {error && <p className="mb-3 text-xs text-red-600">{error}</p>}

                {panel === 'menu' && (
                    <div className="space-y-1">
                        <button
                            type="button"
                            onClick={() => setPanel('search')}
                            className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-gray-800 hover:bg-gray-50"
                        >
                            <Search className="h-4 w-4 text-orange-600" />
                            Search chat history
                        </button>
                        <button
                            type="button"
                            onClick={() => setPanel('clear')}
                            className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-gray-800 hover:bg-gray-50"
                        >
                            <Eraser className="h-4 w-4 text-orange-600" />
                            Clear chat history
                        </button>
                        {clearRequest?.direction === 'incoming' && clearRequest.status === 'pending' && (
                            <div className="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
                                <p className="text-xs font-semibold text-amber-900">
                                    {clearRequest.from_name || peerName} asked to clear this chat for both of you.
                                </p>
                                <div className="mt-2 flex gap-2">
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => void respondBoth(true)}
                                        className="flex-1 rounded-lg bg-orange-500 px-2 py-2 text-xs font-bold text-white hover:bg-orange-600 disabled:opacity-50"
                                    >
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => void respondBoth(false)}
                                        className="flex-1 rounded-lg border border-gray-200 bg-white px-2 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                        )}
                        {clearRequest?.direction === 'outgoing' && clearRequest.status === 'pending' && (
                            <p className="mt-2 rounded-xl border border-sky-100 bg-sky-50 px-3 py-2 text-xs text-sky-900">
                                Waiting for {peerName} to accept clearing for both of you.
                            </p>
                        )}
                        <button
                            type="button"
                            onClick={deleteChat}
                            disabled={busy}
                            className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                        >
                            <Trash2 className="h-4 w-4" />
                            Delete from inbox
                        </button>
                        {canComplain && (
                            <button
                                type="button"
                                onClick={() => setPanel('complaint')}
                                className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                <Flag className="h-4 w-4" />
                                Make a complaint
                            </button>
                        )}
                    </div>
                )}

                {panel === 'clear' && (
                    <div className="space-y-3">
                        <p className="text-xs leading-relaxed text-gray-500">
                            Clearing is soft — messages stay stored for support, but disappear from your chat view.
                            New messages after you clear will still appear.
                        </p>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => void clearForMe()}
                            className="flex w-full items-center gap-3 rounded-xl border border-orange-200 bg-orange-50 px-3 py-3 text-left text-sm font-semibold text-orange-900 hover:bg-orange-100 disabled:opacity-50"
                        >
                            <Eraser className="h-4 w-4" />
                            Clear for me only
                        </button>
                        {!isGroup && (
                            <button
                                type="button"
                                disabled={busy || clearRequest?.direction === 'outgoing'}
                                onClick={() => void requestBoth()}
                                className="flex w-full items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-3 text-left text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:opacity-50"
                            >
                                <Eraser className="h-4 w-4 text-gray-500" />
                                Request clear for both
                            </button>
                        )}
                        <button type="button" onClick={() => setPanel('menu')} className="text-xs text-gray-500 hover:underline">
                            Back
                        </button>
                    </div>
                )}

                {panel === 'search' && (
                    <div className="space-y-3">
                        <div className="flex gap-2">
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && void runSearch()}
                                placeholder={`Search messages with ${peerName}`}
                                className="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm"
                            />
                            <button
                                type="button"
                                onClick={() => void runSearch()}
                                className="rounded-lg bg-orange-500 px-3 py-2 text-xs font-semibold text-white hover:bg-orange-600"
                            >
                                {searching ? '…' : 'Search'}
                            </button>
                        </div>
                        <button type="button" onClick={() => setPanel('menu')} className="text-xs text-gray-500 hover:underline">
                            Back
                        </button>
                        {results.length === 0 ? (
                            <p className="text-xs text-gray-400">No results yet</p>
                        ) : (
                            <ul className="space-y-2">
                                {results.map((m) => (
                                    <li key={m.id} className="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">
                                        {m.body || m.type}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                {panel === 'complaint' && (
                    <form onSubmit={submitComplaint} className="space-y-3">
                        <label className="block text-xs font-semibold text-gray-700">
                            Reason
                            <select
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                            >
                                {reasons.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-xs font-semibold text-gray-700">
                            Details (optional)
                            <textarea
                                value={details}
                                onChange={(e) => setDetails(e.target.value)}
                                rows={4}
                                className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                            />
                        </label>
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setPanel('menu')} className="flex-1 rounded-lg border px-3 py-2 text-xs font-semibold">
                                Back
                            </button>
                            <button
                                type="submit"
                                disabled={busy}
                                className="flex-1 rounded-lg bg-orange-500 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                            >
                                Submit
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
