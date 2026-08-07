import { Flag, Search, Trash2, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
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

type Panel = 'menu' | 'search' | 'complaint';

interface ChatSettingsSheetProps {
    conversationId: number;
    peerName: string;
    sellerId?: number | null;
    productId?: number | null;
    canComplain: boolean;
    onClose: () => void;
    onDeleted: () => void;
}

export default function ChatSettingsSheet({
    conversationId,
    peerName,
    sellerId,
    productId,
    canComplain,
    onClose,
    onDeleted,
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
                            onClick={deleteChat}
                            disabled={busy}
                            className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                        >
                            <Trash2 className="h-4 w-4" />
                            Delete
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
                        <p className="text-xs text-gray-500">Report {peerName} for CityShop admin review.</p>
                        <label className="block text-xs font-medium text-gray-700">
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
                        <label className="block text-xs font-medium text-gray-700">
                            Details (optional)
                            <textarea
                                value={details}
                                onChange={(e) => setDetails(e.target.value)}
                                rows={4}
                                maxLength={2000}
                                className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                                placeholder="Tell us what happened…"
                            />
                        </label>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setPanel('menu')}
                                className="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium"
                            >
                                Back
                            </button>
                            <button
                                type="submit"
                                disabled={busy}
                                className="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                {busy ? 'Submitting…' : 'Submit complaint'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
