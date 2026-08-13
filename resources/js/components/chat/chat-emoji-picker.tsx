import { useMemo, useState } from 'react';

import { EMOJI_CATEGORIES, QUICK_REACTIONS } from '@/lib/chat-emojis';
import { cn } from '@/lib/utils';

export function ChatQuickReactions({
    onPick,
    onMore,
    className,
}: {
    onPick: (emoji: string) => void;
    onMore: () => void;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-0.5 rounded-full border border-gray-100 bg-white px-1.5 py-1 shadow-lg',
                className,
            )}
            onClick={(e) => e.stopPropagation()}
        >
            {QUICK_REACTIONS.map((emoji) => (
                <button
                    key={emoji}
                    type="button"
                    onClick={() => onPick(emoji)}
                    className="flex h-8 w-8 items-center justify-center rounded-full text-lg hover:bg-gray-100"
                    aria-label={`React with ${emoji}`}
                >
                    {emoji}
                </button>
            ))}
            <button
                type="button"
                onClick={onMore}
                className="flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold text-gray-500 hover:bg-gray-100"
                aria-label="More emojis"
            >
                +
            </button>
        </div>
    );
}

export function ChatEmojiPicker({
    onPick,
    className,
}: {
    onPick: (emoji: string) => void;
    className?: string;
}) {
    const [categoryId, setCategoryId] = useState(EMOJI_CATEGORIES[0].id);
    const [query, setQuery] = useState('');
    const category = EMOJI_CATEGORIES.find((item) => item.id === categoryId) ?? EMOJI_CATEGORIES[0];
    const emojis = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return category.emojis;
        }

        return EMOJI_CATEGORIES.flatMap((item) => item.emojis).filter(
            (emoji) => emoji.includes(q) || itemLabel(emoji).includes(q),
        );
    }, [category.emojis, query]);

    return (
        <div
            className={cn(
                'w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-gray-100 bg-white shadow-xl',
                className,
            )}
            onClick={(e) => e.stopPropagation()}
        >
            <div className="border-b border-gray-100 p-2">
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search emoji"
                    className="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-orange-400"
                />
            </div>
            {!query.trim() && (
                <div className="flex gap-1 overflow-x-auto border-b border-gray-100 px-2 py-1.5">
                    {EMOJI_CATEGORIES.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => setCategoryId(item.id)}
                            className={cn(
                                'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                item.id === categoryId ? 'bg-orange-100 text-orange-700' : 'text-gray-500 hover:bg-gray-50',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            )}
            <div className="grid max-h-56 grid-cols-8 gap-0.5 overflow-y-auto p-2">
                {emojis.map((emoji, index) => (
                    <button
                        key={`${emoji}-${index}`}
                        type="button"
                        onClick={() => onPick(emoji)}
                        className="flex h-8 w-8 items-center justify-center rounded text-lg hover:bg-gray-100"
                    >
                        {emoji}
                    </button>
                ))}
            </div>
        </div>
    );
}

function itemLabel(emoji: string): string {
    return emoji;
}
