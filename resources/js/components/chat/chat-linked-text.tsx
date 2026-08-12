import { firstCityShopLinkIn, parseChatText, parseCityShopDeepLink } from '@/lib/parse-chat-text';
import { cn } from '@/lib/utils';

type ChatLinkedTextProps = {
    text: string;
    mine?: boolean;
    onOpenCityShop?: (path: string) => void;
    onCopy?: (value: string, label: string) => void;
};

export default function ChatLinkedText({ text, mine = false, onOpenCityShop, onCopy }: ChatLinkedTextProps) {
    const segments = parseChatText(text);
    const preview = firstCityShopLinkIn(text);

    return (
        <div>
            {preview && (
                <button
                    type="button"
                    onClick={() => onOpenCityShop?.(preview.path)}
                    className={cn(
                        'mb-2 w-full rounded-xl px-3 py-2 text-left',
                        mine ? 'bg-white/15' : 'bg-gray-50',
                    )}
                >
                    <p className={cn('text-sm font-semibold', mine ? 'text-white' : 'text-gray-900')}>
                        {preview.kind === 'product' ? 'Open product' : preview.kind === 'store' ? 'Open store' : 'Watch live'}
                    </p>
                    <p className={cn('text-[11px]', mine ? 'text-orange-100' : 'text-gray-400')}>cityunlock.net</p>
                </button>
            )}
            <p className="whitespace-pre-wrap break-words">
                {segments.map((segment, index) => {
                    if (segment.kind === 'plain') {
                        return <span key={index}>{segment.text}</span>;
                    }

                    if (segment.kind === 'phone') {
                        return (
                            <button
                                key={index}
                                type="button"
                                className={cn('font-semibold underline', mine ? 'text-white' : 'text-orange-600')}
                                onClick={() => onCopy?.(segment.text.replace(/[\s-]/g, ''), 'Number')}
                            >
                                {segment.text}
                            </button>
                        );
                    }

                    const city = parseCityShopDeepLink(segment.text);
                    if (city) {
                        return (
                            <button
                                key={index}
                                type="button"
                                className={cn('font-semibold underline', mine ? 'text-white' : 'text-orange-600')}
                                onClick={() => onOpenCityShop?.(city.path)}
                            >
                                {segment.text}
                            </button>
                        );
                    }

                    const href = segment.text.startsWith('www.') ? `https://${segment.text}` : segment.text;
                    return (
                        <a
                            key={index}
                            href={href}
                            target="_blank"
                            rel="noreferrer"
                            className={cn('font-semibold underline', mine ? 'text-white' : 'text-orange-600')}
                        >
                            {segment.text}
                        </a>
                    );
                })}
            </p>
        </div>
    );
}
