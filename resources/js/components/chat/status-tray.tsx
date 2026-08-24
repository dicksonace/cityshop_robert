import { Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { useToastOptional } from '@/contexts/toast-context';
import * as statusApi from '@/lib/status-api';
import { cn } from '@/lib/utils';
import type { StatusBundle, StatusFeed } from '@/types/status';
import { productImageUrl } from '@/types/marketplace';

import StatusViewer from './status-viewer';

const TEXT_COLORS = ['#EA580C', '#7C3AED', '#0F766E', '#BE123C', '#1D4ED8', '#365314'];

export default function StatusTray() {
    const toast = useToastOptional();
    const [feed, setFeed] = useState<StatusFeed | null>(null);
    const [viewer, setViewer] = useState<{ bundle: StatusBundle; mine: boolean } | null>(null);
    const [composer, setComposer] = useState(false);
    const [text, setText] = useState('');
    const [colorIndex, setColorIndex] = useState(0);
    const [posting, setPosting] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const load = useCallback(async () => {
        try {
            setFeed(await statusApi.fetchStatusFeed());
        } catch {
            // Status is additive; keep the chat list usable if it fails.
        }
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    const postPhoto = async (file: File) => {
        setPosting(true);
        try {
            await statusApi.postStatus({ file });
            await load();
            toast?.success('Status posted');
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not post status');
        } finally {
            setPosting(false);
        }
    };

    const postText = async () => {
        if (!text.trim() || posting) return;
        setPosting(true);
        try {
            await statusApi.postStatus({
                body: text.trim(),
                backgroundColor: TEXT_COLORS[colorIndex % TEXT_COLORS.length],
            });
            setText('');
            setComposer(false);
            await load();
            toast?.success('Status posted');
        } catch (err) {
            toast?.error(err instanceof Error ? err.message : 'Could not post status');
        } finally {
            setPosting(false);
        }
    };

    const mine = feed?.mine;
    const others = (feed?.users ?? []).filter((bundle) => bundle.items.length > 0);

    return (
        <div className="border-b border-gray-100 px-2 py-2">
            <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    e.target.value = '';
                    if (file) void postPhoto(file);
                }}
            />
            <div className="flex gap-3 overflow-x-auto pb-1">
                <button
                    type="button"
                    onClick={() => {
                        if (mine && mine.items.length > 0) {
                            setViewer({ bundle: mine, mine: true });
                        } else {
                            fileRef.current?.click();
                        }
                    }}
                    className="flex w-14 shrink-0 flex-col items-center gap-1"
                    title="My status"
                >
                    <span className="relative">
                        <span
                            className={cn(
                                'flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border-2 bg-orange-50 text-sm font-bold text-orange-600',
                                mine && mine.items.length > 0 ? 'border-gray-300' : 'border-dashed border-gray-300',
                            )}
                        >
                            {mine?.user.avatar ? (
                                <img src={productImageUrl(mine.user.avatar)} alt="" className="h-full w-full object-cover" />
                            ) : (
                                (mine?.user.name?.[0] ?? 'Y').toUpperCase()
                            )}
                        </span>
                        <span className="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full border-2 border-white bg-orange-500 text-white">
                            <Plus
                                className="h-2.5 w-2.5"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    fileRef.current?.click();
                                }}
                            />
                        </span>
                    </span>
                    <span className="w-full truncate text-center text-[10px] font-medium text-gray-600">My status</span>
                </button>
                <button
                    type="button"
                    onClick={() => setComposer(true)}
                    className="flex w-14 shrink-0 flex-col items-center gap-1 text-gray-500"
                    title="Text status"
                >
                    <span className="flex h-12 w-12 items-center justify-center rounded-full border border-dashed border-gray-300 bg-white text-lg font-bold">
                        Aa
                    </span>
                    <span className="w-full truncate text-center text-[10px] font-medium">Text</span>
                </button>
                {others.map((bundle) => (
                    <button
                        key={bundle.user.id}
                        type="button"
                        onClick={() => setViewer({ bundle, mine: false })}
                        className="flex w-14 shrink-0 flex-col items-center gap-1"
                    >
                        <span
                            className={cn(
                                'flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border-2 bg-orange-50 text-sm font-bold text-orange-600',
                                bundle.unseen_count > 0 ? 'border-orange-500' : 'border-gray-300',
                            )}
                        >
                            {bundle.user.avatar ? (
                                <img
                                    src={productImageUrl(bundle.user.avatar)}
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                (bundle.user.name?.[0] ?? '?').toUpperCase()
                            )}
                        </span>
                        <span className="w-full truncate text-center text-[10px] font-medium text-gray-600">
                            {bundle.user.name}
                        </span>
                    </button>
                ))}
            </div>
            {composer && (
                <div className="mt-2 rounded-xl p-3 text-white" style={{ backgroundColor: TEXT_COLORS[colorIndex] }}>
                    <textarea
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        placeholder="Type a status"
                        className="h-20 w-full resize-none bg-transparent text-center text-base font-semibold placeholder:text-white/70 focus:outline-none"
                    />
                    <div className="mt-2 flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => setColorIndex((i) => i + 1)}
                            className="text-xs font-semibold underline"
                        >
                            Colour
                        </button>
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setComposer(false)} className="text-xs">
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={posting || !text.trim()}
                                onClick={() => void postText()}
                                className="rounded-full bg-white px-3 py-1 text-xs font-bold text-gray-900 disabled:opacity-50"
                            >
                                Post
                            </button>
                        </div>
                    </div>
                </div>
            )}
            {viewer && (
                <StatusViewer
                    bundle={viewer.bundle}
                    isMine={viewer.mine}
                    onClose={() => {
                        setViewer(null);
                        void load();
                    }}
                />
            )}
        </div>
    );
}
