import { parseChatText, parseCityShopDeepLink } from '@/lib/parse-chat-text';

export default function LinkedDescription({ text }: { text: string }) {
    const segments = parseChatText(text);

    return (
        <>
            {segments.map((segment, index) => {
                if (segment.kind !== 'url') {
                    return <span key={index}>{segment.text}</span>;
                }

                const city = parseCityShopDeepLink(segment.text);
                const href = /^(https?:|cityshop:)/i.test(segment.text) ? segment.text : `https://${segment.text}`;

                return (
                    <a
                        key={index}
                        href={city?.path ?? href}
                        target={city ? undefined : '_blank'}
                        rel={city ? undefined : 'noreferrer'}
                        className="font-semibold text-green-600 underline"
                    >
                        {segment.text}
                    </a>
                );
            })}
        </>
    );
}
