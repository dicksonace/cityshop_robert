import ChatSharedLinkPreview from '@/components/chat/chat-shared-link-preview';
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
                <ChatSharedLinkPreview link={preview} mine={mine} onOpen={onOpenCityShop} />
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
